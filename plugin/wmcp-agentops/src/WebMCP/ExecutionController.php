<?php

/**
 * Secure, observable execution gateway for first-party WebMCP tools.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPWebMCP\AgentOps\Abilities\ToolCatalog;
use WPWebMCP\AgentOps\Analytics\EventRecorder;
use WPWebMCP\AgentOps\Analytics\WorkflowService;
use WPWebMCP\AgentOps\Contract\EventName;
use WPWebMCP\AgentOps\Contract\Versions;
use WPWebMCP\AgentOps\Demo\DemoMode;
use WPWebMCP\AgentOps\Demo\DemoSession;
use WPWebMCP\AgentOps\Guidance\AgentGuide;
use WPWebMCP\AgentOps\Policy\PolicyEngine;
use WPWebMCP\AgentOps\Policy\RateLimiter;
use WPWebMCP\AgentOps\Support\Json;
use WPWebMCP\AgentOps\Support\Logger;

final class ExecutionController
{
    private const EXECUTION_LEASE_SECONDS = 300;

    public function __construct(
        private readonly ToolCatalog $catalog,
        private readonly DemoSession $sessions,
        private readonly OriginValidator $origins,
        private readonly CsrfToken $csrf,
        private readonly ManifestService $manifests,
        private readonly PolicyEngine $policy,
        private readonly RateLimiter $rate_limiter,
        private readonly WorkflowService $workflows,
        private readonly EventRecorder $events,
        private readonly IdempotencyStore $idempotency,
        private readonly RestResponseFactory $responses,
        private readonly ?AgentGuide $guide = null
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $tool_name  = sanitize_key((string) $request->get_param('tool_name'));
        $definition = $this->catalog->find($tool_name);

        if (null === $definition) {
            return $this->error_response('unknown_tool', 'The requested tool is not exposed.', 404);
        }

        if (! DemoMode::enabled()) {
            return $this->error_response('demo_mode_disabled', 'Public tool execution is unavailable.', 403);
        }

        if (! $this->origins->validate($request)) {
            return $this->error_response('origin_denied', 'The request origin is not allowed.', 403);
        }

        $session = $this->sessions->peek();
        if (null === $session) {
            return $this->error_response(
                'session_required',
                'Start a same-origin demo session before executing a tool.',
                401,
                true
            );
        }
        $surface = (string) $definition['surface'];
        $token   = (string) $request->get_header('x-wmcp-csrf');
        if (! $this->csrf->verify($token, $session['hash_hex'], $surface)) {
            return $this->error_response(
                'csrf_invalid',
                'The session token is missing, expired, or invalid.',
                403,
                true,
                'Refresh the available site tools and try again.'
            );
        }

        $payload = $request->get_json_params();
        if (! is_array($payload)) {
            return $this->error_response('invalid_request', 'The request body must be a JSON object.', 400);
        }

        $envelope_error = $this->validate_envelope($payload);
        if (null !== $envelope_error) {
            return $envelope_error;
        }

        $workflow = $this->workflows->current($session['hash_hex'], $surface);
        if (! hash_equals((string) $workflow['id'], (string) $payload['workflow_id'])) {
            return $this->error_response('workflow_scope_denied', 'The workflow does not belong to this demo session.', 403);
        }

        $workflow_id = (string) $workflow['id'];
        $request_id  = (string) $payload['request_id'];
        $input       = $payload['input'];
        if (! is_array($input) || strlen(Json::encode($input)) > (int) $definition['max_input_bytes']) {
            return $this->error_response('input_too_large', 'The tool input is too large.', 413);
        }

        $fingerprint = hash('sha256', $tool_name . '|' . Json::encode($input));
        $existing    = $this->idempotency->get($session['hash_hex'], $workflow_id, $request_id);
        if (
            null !== $existing
            && (! isset($existing['fingerprint']) || ! hash_equals((string) $existing['fingerprint'], $fingerprint))
        ) {
            return $this->error_response(
                'request_id_conflict',
                'This request identifier was already used for a different tool or input.',
                409,
                false,
                'Create a new request identifier.'
            );
        }

        // Replays are accepted only while the current manifest and policy still
        // authorize the same operation. The cached response is not authority.
        $current_revision = $this->manifests->revision($surface, $session['hash_hex']);
        $revision_current = hash_equals($current_revision, (string) $payload['manifest_revision']);
        $decision         = $this->policy->evaluate($definition, $surface, $session['hash_hex']);

        if (null !== $existing) {
            if (! $revision_current) {
                return $this->error_response(
                    'manifest_stale',
                    'The available tool catalog changed.',
                    409,
                    true,
                    'Refresh the available site tools and retry.'
                );
            }
            if (! $decision['allowed']) {
                return $this->error_response(
                    (string) $decision['code'],
                    $this->policy_message((string) $decision['code']),
                    (int) $decision['http_status'],
                    false,
                    'Refresh the available site tools.'
                );
            }

            return $this->replay_or_conflict($existing, $fingerprint);
        }

        if (! $this->idempotency->begin($session['hash_hex'], $workflow_id, $request_id, $tool_name, $fingerprint)) {
            $existing = $this->idempotency->get($session['hash_hex'], $workflow_id, $request_id);
            return null !== $existing
                ? $this->replay_or_conflict($existing, $fingerprint)
                : $this->error_response('request_in_progress', 'An identical request is already being processed.', 409, true);
        }

        try {
            $started = $this->events->start(
                $workflow_id,
                $request_id,
                $definition,
                array()
            );
        } catch (Throwable $exception) {
            $this->idempotency->release($session['hash_hex'], $workflow_id, $request_id);
            $this->log_failure($request_id, $tool_name, 'event_start_failed', $exception);
            return $this->error_response('event_ledger_unavailable', 'The workflow ledger is temporarily unavailable.', 503, true);
        }

        if (! $revision_current) {
            return $this->finish_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_DENIED,
                'manifest_stale',
                'The available tool catalog changed.',
                409,
                true,
                'Refresh the available site tools and retry.',
                $current_revision
            );
        }

        if (! $decision['allowed']) {
            $status = (int) $decision['http_status'];
            return $this->finish_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_DENIED,
                (string) $decision['code'],
                $this->policy_message((string) $decision['code']),
                $status,
                false,
                'Refresh the available site tools.',
                $current_revision
            );
        }

        $rate = $this->rate_limiter->consume(
            $session['hash_hex'],
            $tool_name,
            (int) $definition['rate_limit'],
            (int) $definition['rate_window']
        );
        if (! $rate['allowed']) {
            $response = $this->finish_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_DENIED,
                'rate_limit_exceeded',
                'Too many requests were made for this tool.',
                429,
                true,
                'Wait and try again.',
                $current_revision
            );
            $response->header('Retry-After', (string) $rate['retry_after']);
            return $response;
        }

        $ability = function_exists('wp_get_ability') ? wp_get_ability((string) $definition['ability_id']) : null;
        if (null === $ability || ! is_callable(array($ability, 'execute'))) {
            return $this->finish_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_FAILED,
                'ability_unavailable',
                'The WordPress ability is temporarily unavailable.',
                503,
                true,
                'Refresh the page and try again.',
                $current_revision
            );
        }

        if (! $this->idempotency->mark_executing($session['hash_hex'], $workflow_id, $request_id)) {
            $this->log_state_failure($request_id, $tool_name, 'execution_guard_failed');
            return $this->finish_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_FAILED,
                'execution_guard_unavailable',
                'The tool could not establish a durable execution guard.',
                503,
                true,
                'Wait briefly, then refresh the available site tools.',
                $current_revision
            );
        }

        $context_entered = false;
        $page_url        = (string) $request->get_header('x-wmcp-page-url');
        if (! $this->origins->same_origin_url($page_url)) {
            $referer  = (string) $request->get_header('referer');
            $page_url = $this->origins->same_origin_url($referer) ? $referer : home_url('/');
        }

        try {
            ExecutionContext::enter(
                array(
                    'authorized'       => true,
                    'tool_name'        => $tool_name,
                    'surface'          => $surface,
                    'workflow_id'      => (string) $workflow['id'],
                    'request_id'       => $request_id,
                    'session_hash_hex' => $session['hash_hex'],
                    'page_url'         => $page_url,
                )
            );
            $context_entered = true;

            $result = $ability->execute($input);
        } catch (ToolException $exception) {
            return $this->finish_tool_exception(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                $exception,
                $current_revision
            );
        } catch (Throwable $exception) {
            $this->log_failure($request_id, $tool_name, 'execution_failed', $exception);
            $write_started = false === (bool) ($definition['read_only'] ?? false);
            return $this->finish_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_FAILED,
                $write_started ? 'tool_outcome_unknown' : 'tool_execution_failed',
                $write_started
                    ? 'The tool stopped after a write began, so its final effect could not be confirmed.'
                    : 'The tool could not complete the request.',
                500,
                ! $write_started,
                $write_started
                    ? 'Check the current website state before starting another action.'
                    : 'Retry once, then use the normal website controls.',
                $current_revision,
                true
            );
        } finally {
            if ($context_entered) {
                ExecutionContext::leave();
            }
        }

        $executed_persisted = $this->idempotency->mark_executed(
            $session['hash_hex'],
            $workflow_id,
            $request_id
        );
        if (! $executed_persisted) {
            // The earlier executing marker is intentionally retained. It is
            // sufficient to prevent a second invocation while finalization is
            // attempted.
            $this->log_state_failure($request_id, $tool_name, 'ability_return_state_failed');
        }

        if (is_wp_error($result)) {
            return $this->finish_wp_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                $result,
                $current_revision,
                true
            );
        }

        if (! is_array($result)) {
            return $this->finish_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_FAILED,
                'invalid_tool_output',
                'The tool returned an invalid result.',
                500,
                false,
                '',
                $current_revision,
                true
            );
        }

        try {
            $encoded_result = Json::encode($result);
        } catch (Throwable $exception) {
            return $this->post_execution_failure_response(
                $session['hash_hex'],
                $workflow_id,
                $request_id,
                $tool_name,
                $current_revision,
                'response_serialization_failed',
                $exception
            );
        }

        if (strlen($encoded_result) > (int) $definition['max_output_bytes']) {
            return $this->finish_error(
                $session['hash_hex'],
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_FAILED,
                'output_too_large',
                'The tool result exceeded the safe response limit.',
                500,
                false,
                '',
                $current_revision,
                true
            );
        }

        try {
            $revision = $this->manifests->revision($surface, $session['hash_hex']);
            $terminal = $this->events->terminal(
                (string) $workflow['id'],
                $request_id,
                EventName::TOOL_CALL_SUCCEEDED,
                array(
                    'status'      => 'success',
                    'duration_ms' => $this->duration_ms($started),
                    'http_status' => 200,
                )
            );
            if (! isset($terminal['event_id']) || ! is_string($terminal['event_id'])) {
                throw new RuntimeException('The terminal event did not return an event identifier.');
            }

            $body = array(
                'ok'                => true,
                'event_id'          => $terminal['event_id'],
                'workflow_id'       => (string) $workflow['id'],
                'manifest_revision' => $revision,
                'tool'              => array('name' => $tool_name, 'version' => (string) $definition['version']),
                'result'            => $result,
                'evidence'          => isset($result['evidence']) && is_array($result['evidence']) ? $result['evidence'] : array(),
                'ui'                => array('event' => $this->ui_event($tool_name), 'revision' => $revision),
                'next_actions'      => null === $this->guide
                    ? array()
                    : $this->guide->next_actions($tool_name, $result, (string) $terminal['event_id']),
            );
        } catch (Throwable $exception) {
            return $this->post_execution_failure_response(
                $session['hash_hex'],
                $workflow_id,
                $request_id,
                $tool_name,
                $current_revision,
                'terminal_event_failed',
                $exception
            );
        }

        if (! $this->idempotency->complete($session['hash_hex'], $workflow_id, $request_id, 200, $body)) {
            return $this->post_execution_failure_response(
                $session['hash_hex'],
                $workflow_id,
                $request_id,
                $tool_name,
                $revision,
                'response_cache_failed',
                null,
                $terminal['event_id']
            );
        }

        return $this->responses->make($body);
    }

    /**
     * @param array<string, mixed> $payload Request envelope.
     */
    private function validate_envelope(array $payload): ?WP_REST_Response
    {
        $allowed  = array('schema_version', 'workflow_id', 'manifest_revision', 'request_id', 'input');
        $required = $allowed;

        if (array() !== array_diff(array_keys($payload), $allowed) || array() !== array_diff($required, array_keys($payload))) {
            return $this->error_response('invalid_request', 'The request envelope contains missing or unknown fields.', 400);
        }

        if (Versions::SCHEMA !== $payload['schema_version']) {
            return $this->error_response('unsupported_schema_version', 'The request schema version is unsupported.', 409, true);
        }

        if (! is_string($payload['workflow_id']) || 1 !== preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $payload['workflow_id'])) {
            return $this->error_response('invalid_workflow_id', 'The workflow identifier is invalid.', 400);
        }

        if (! is_string($payload['manifest_revision']) || 1 !== preg_match('/\Arev_[a-f0-9]{24}\z/', $payload['manifest_revision'])) {
            return $this->error_response('invalid_manifest_revision', 'The manifest revision is invalid.', 400);
        }

        if (
            ! is_string($payload['request_id'])
            || 1 !== preg_match(
                '/\A(?:req_[0-9a-f]{32}|[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\z/i',
                $payload['request_id']
            )
        ) {
            return $this->error_response('invalid_request_id', 'The request identifier is invalid.', 400);
        }

        if (! is_array($payload['input'])) {
            return $this->error_response('invalid_input', 'Tool input must be a JSON object.', 400);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $existing Stored idempotency state.
     */
    private function replay_or_conflict(array $existing, string $fingerprint): WP_REST_Response
    {
        if (! isset($existing['fingerprint']) || ! hash_equals((string) $existing['fingerprint'], $fingerprint)) {
            return $this->error_response(
                'request_id_conflict',
                'This request identifier was already used for a different tool or input.',
                409,
                false,
                'Create a new request identifier.'
            );
        }

        if ('complete' === ($existing['state'] ?? null) && isset($existing['body'], $existing['status']) && is_array($existing['body'])) {
            $body             = $existing['body'];
            $body['replayed'] = true;
            return $this->responses->make($body, (int) $existing['status']);
        }

        if ('pending' === ($existing['state'] ?? null)) {
            return $this->error_response('request_in_progress', 'An identical request is already being processed.', 409, true);
        }

        if ('executing' === ($existing['state'] ?? null)) {
            $started_at = isset($existing['execution_started_at']) ? (int) $existing['execution_started_at'] : 0;
            if (0 < $started_at && time() - $started_at <= self::EXECUTION_LEASE_SECONDS) {
                return $this->error_response('request_in_progress', 'An identical request is already being processed.', 409, true);
            }

            return $this->error_response(
                'request_outcome_unknown',
                'This request began execution, but no replayable result is available.',
                409,
                false,
                'Check the current website state before starting another action.'
            );
        }

        return $this->error_response(
            'request_result_unavailable',
            'This request already crossed the execution boundary, but its saved response is unavailable.',
            409,
            false,
            'Check the current website state before starting another action.'
        );
    }

    /**
     * @param array<string, mixed> $workflow Workflow row.
     * @param array<string, mixed> $definition Tool definition.
     * @param array<string, mixed> $started Start event result.
     */
    private function finish_error(
        string $session_hash_hex,
        array $workflow,
        array $definition,
        string $request_id,
        array $started,
        string $terminal_event,
        string $code,
        string $message,
        int $status,
        bool $retryable,
        string $recovery,
        string $manifest_revision,
        bool $post_execution = false
    ): WP_REST_Response {
        try {
            $terminal = $this->events->terminal(
                (string) $workflow['id'],
                $request_id,
                $terminal_event,
                array(
                    'status'      => EventName::TOOL_CALL_DENIED === $terminal_event ? 'denied' : 'failed',
                    'duration_ms' => $this->duration_ms($started),
                    'error_code'  => $code,
                    'http_status' => $status,
                )
            );
            if (! isset($terminal['event_id']) || ! is_string($terminal['event_id'])) {
                throw new RuntimeException('The terminal event did not return an event identifier.');
            }
        } catch (Throwable $exception) {
            $this->log_failure($request_id, (string) $definition['name'], 'terminal_event_failed', $exception);
            if ($post_execution) {
                return $this->post_execution_failure_response(
                    $session_hash_hex,
                    (string) $workflow['id'],
                    $request_id,
                    (string) $definition['name'],
                    $manifest_revision,
                    'terminal_event_failed',
                    $exception
                );
            }

            $this->idempotency->release($session_hash_hex, (string) $workflow['id'], $request_id);
            return $this->error_response(
                'event_ledger_unavailable',
                'The workflow ledger is temporarily unavailable.',
                503,
                true,
                'Wait briefly, then retry with a new request identifier.'
            );
        }

        if ($post_execution && false === (bool) ($definition['read_only'] ?? false)) {
            $retryable = false;
            $recovery  = 'Check the current website state before starting another action.';
        }

        $body = array(
            'ok'                => false,
            'error'             => array(
                'code'      => $code,
                'message'   => $message,
                'retryable' => $retryable,
                'recovery'  => $recovery,
            ),
            'workflow_id'       => (string) $workflow['id'],
            'event_id'          => $terminal['event_id'],
            'manifest_revision' => $manifest_revision,
        );

        if (
            ! $this->idempotency->complete(
                $session_hash_hex,
                (string) $workflow['id'],
                $request_id,
                $status,
                $body
            )
        ) {
            $this->log_state_failure($request_id, (string) $definition['name'], 'response_cache_failed');
            if ($post_execution) {
                return $this->post_execution_failure_response(
                    $session_hash_hex,
                    (string) $workflow['id'],
                    $request_id,
                    (string) $definition['name'],
                    $manifest_revision,
                    'response_cache_failed',
                    null,
                    $terminal['event_id']
                );
            }

            $this->idempotency->release($session_hash_hex, (string) $workflow['id'], $request_id);
            return $this->error_response(
                'response_cache_unavailable',
                'The tool response could not be saved safely.',
                503,
                true,
                'Wait briefly, then retry with a new request identifier.'
            );
        }

        return $this->responses->make($body, $status);
    }

    /**
     * @param array<string, mixed> $workflow Workflow row.
     * @param array<string, mixed> $definition Tool definition.
     * @param array<string, mixed> $started Start event result.
     */
    private function finish_tool_exception(
        string $session_hash_hex,
        array $workflow,
        array $definition,
        string $request_id,
        array $started,
        ToolException $exception,
        string $manifest_revision
    ): WP_REST_Response {
        return $this->finish_error(
            $session_hash_hex,
            $workflow,
            $definition,
            $request_id,
            $started,
            403 === $exception->http_status() ? EventName::TOOL_CALL_DENIED : EventName::TOOL_CALL_FAILED,
            $exception->error_code(),
            $exception->getMessage(),
            $exception->http_status(),
            $exception->retryable(),
            $exception->recovery(),
            $manifest_revision,
            true
        );
    }

    /**
     * @param array<string, mixed> $workflow Workflow row.
     * @param array<string, mixed> $definition Tool definition.
     * @param array<string, mixed> $started Start event result.
     */
    private function finish_wp_error(
        string $session_hash_hex,
        array $workflow,
        array $definition,
        string $request_id,
        array $started,
        WP_Error $error,
        string $manifest_revision,
        bool $post_execution = true
    ): WP_REST_Response {
        $validation_codes = array('rest_invalid_param', 'rest_invalid_type', 'rest_property_required', 'invalid_input', 'ability_invalid_input');
        $permission_codes = array('ability_invalid_permissions', 'ability_permission_denied', 'rest_forbidden');
        $core_code        = (string) $error->get_error_code();
        $data             = $error->get_error_data();

        if (is_array($data) && true === ($data['wmcp_safe'] ?? false)) {
            return $this->finish_error(
                $session_hash_hex,
                $workflow,
                $definition,
                $request_id,
                $started,
                403 === (int) $data['status'] ? EventName::TOOL_CALL_DENIED : EventName::TOOL_CALL_FAILED,
                $core_code,
                $error->get_error_message(),
                isset($data['status']) ? (int) $data['status'] : 500,
                true === ($data['retryable'] ?? false),
                isset($data['recovery']) ? (string) $data['recovery'] : '',
                $manifest_revision,
                $post_execution
            );
        }

        if (in_array($core_code, $validation_codes, true)) {
            return $this->finish_error(
                $session_hash_hex,
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_FAILED,
                'invalid_input',
                'The tool input did not match its schema.',
                400,
                false,
                '',
                $manifest_revision,
                $post_execution
            );
        }

        if (in_array($core_code, $permission_codes, true)) {
            return $this->finish_error(
                $session_hash_hex,
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_DENIED,
                'permission_denied',
                'The tool is not permitted in this context.',
                403,
                false,
                '',
                $manifest_revision,
                $post_execution
            );
        }

        if ('ability_invalid_output' === $core_code) {
            return $this->finish_error(
                $session_hash_hex,
                $workflow,
                $definition,
                $request_id,
                $started,
                EventName::TOOL_CALL_FAILED,
                'invalid_tool_output',
                'The tool returned an invalid result.',
                500,
                false,
                '',
                $manifest_revision,
                $post_execution
            );
        }

        return $this->finish_error(
            $session_hash_hex,
            $workflow,
            $definition,
            $request_id,
            $started,
            EventName::TOOL_CALL_FAILED,
            'tool_execution_failed',
            'The tool could not complete the request.',
            500,
            true,
            'Retry once, then use the normal website controls.',
            $manifest_revision,
            $post_execution
        );
    }

    private function post_execution_failure_response(
        string $session_hash_hex,
        string $workflow_id,
        string $request_id,
        string $tool_name,
        string $manifest_revision,
        string $failure_code,
        ?Throwable $exception = null,
        ?string $event_id = null
    ): WP_REST_Response {
        if (null === $exception) {
            $this->log_state_failure($request_id, $tool_name, $failure_code);
        } else {
            $this->log_failure($request_id, $tool_name, $failure_code, $exception);
        }

        if (! $this->idempotency->seal($session_hash_hex, $workflow_id, $request_id, $failure_code)) {
            // A failed seal does not release the durable executing or executed
            // marker that was persisted before this point.
            $this->log_state_failure($request_id, $tool_name, 'request_seal_failed');
        }

        $body = array(
            'ok'                => false,
            'error'             => array(
                'code'      => 'request_result_unavailable',
                'message'   => 'The tool crossed the execution boundary, but its response could not be finalized.',
                'retryable' => false,
                'recovery'  => 'Check the current website state before starting another action. '
                    . 'Reusing this request ID will not execute it again.',
            ),
            'workflow_id'       => $workflow_id,
            'manifest_revision' => $manifest_revision,
        );
        if (null !== $event_id) {
            $body['event_id'] = $event_id;
        }

        return $this->responses->make($body, 500);
    }

    private function error_response(
        string $code,
        string $message,
        int $status,
        bool $retryable = false,
        string $recovery = ''
    ): WP_REST_Response {
        return $this->responses->make(
            array(
                'ok'    => false,
                'error' => array(
                    'code'      => $code,
                    'message'   => $message,
                    'retryable' => $retryable,
                    'recovery'  => $recovery,
                ),
            ),
            $status
        );
    }

    /**
     * @param array<string, mixed> $started Start event result.
     */
    private function duration_ms(array $started): int
    {
        $started_ns = isset($started['started_monotonic_ns']) ? (int) $started['started_monotonic_ns'] : hrtime(true);

        return max(0, (int) round((hrtime(true) - $started_ns) / 1000000));
    }

    private function policy_message(string $code): string
    {
        return match ($code) {
            'kill_switch_active' => 'WebMCP execution is temporarily paused by the site operator.',
            'tool_disabled' => 'This tool is disabled for the current scope.',
            'webmcp_disabled' => 'WebMCP tools are disabled for this site.',
            default => 'This tool is not permitted in the current context.',
        };
    }

    private function ui_event(string $tool_name): string
    {
        return match ($tool_name) {
            'search_products' => 'products_found',
            'get_product' => 'product_viewed',
            'compare_products' => 'products_compared',
            'get_store_policy' => 'policy_viewed',
            'get_cart' => 'cart_read',
            'add_to_cart', 'remove_from_cart', 'update_cart_quantity' => 'cart_changed',
            'checkout_handoff' => 'checkout_ready',
            'report_capability_gap' => 'capability_gap_recorded',
            'report_agent_feedback' => 'agent_feedback_recorded',
            'get_agent_guide' => 'agent_guide_loaded',
            'set_tool_enabled' => 'manifest_invalidated',
            default => str_starts_with($tool_name, 'get_agent_') || in_array(
                $tool_name,
                array('query_agent_workflows', 'explain_agent_workflow', 'get_tool_health', 'get_capability_gaps', 'get_opportunity_signals'),
                true
            )
                ? 'dashboard_updated'
                : 'tool_completed',
        };
    }

    private function log_failure(string $request_id, string $tool_name, string $code, Throwable $exception): void
    {
        Logger::error(
            $code,
            array(
                'request_id' => $request_id,
                'tool_name'  => $tool_name,
                'exception'  => get_class($exception),
            )
        );
    }

    private function log_state_failure(string $request_id, string $tool_name, string $code): void
    {
        Logger::error(
            $code,
            array(
                'request_id' => $request_id,
                'tool_name'  => $tool_name,
            )
        );
    }
}
