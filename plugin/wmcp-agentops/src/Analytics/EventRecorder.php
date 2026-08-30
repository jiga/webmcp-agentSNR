<?php

/**
 * Idempotent workflow-event persistence.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Analytics;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use WPWebMCP\AgentOps\Contract\EventName;
use WPWebMCP\AgentOps\Support\Clock;
use WPWebMCP\AgentOps\Support\IdGenerator;
use WPWebMCP\AgentOps\Support\Json;

final class EventRecorder
{
    private object $database;

    private EventSchema $schema;

    /** @var callable(): string */
    private $clock;

    /** @var callable(): string */
    private $event_id_generator;

    /** @var callable(): int */
    private $monotonic_clock;

    /**
     * @param object|null       $database           wpdb-compatible database object.
     * @param callable():string $clock              UTC DATETIME provider.
     * @param callable():string $event_id_generator Event ID provider.
     * @param callable():int    $monotonic_clock    Monotonic nanosecond provider.
     */
    public function __construct(
        ?object $database = null,
        ?EventSchema $schema = null,
        ?callable $clock = null,
        ?callable $event_id_generator = null,
        ?callable $monotonic_clock = null
    ) {
        if (null === $database) {
            global $wpdb;
            $database = $wpdb;
        }

        if (! is_object($database)) {
            throw new InvalidArgumentException('A wpdb-compatible database object is required.');
        }

        $this->database           = $database;
        $this->schema             = $schema ?? new EventSchema();
        $this->clock              = $clock ?? static fn (): string => Clock::mysql();
        $this->event_id_generator = $event_id_generator ?? static fn (): string => IdGenerator::event();
        $this->monotonic_clock    = $monotonic_clock ?? static fn (): int => hrtime(true);
    }

    /**
     * Record exactly one tool-call start for a workflow/request pair.
     *
     * @param array<string, mixed> $tool       Tool metadata.
     * @param array<string, mixed> $properties Allowlisted operational properties.
     * @return array<string, mixed>
     */
    public function start(string $workflow_id, string $request_id, array $tool, array $properties = array()): array
    {
        $workflow_id = $this->schema->workflow_id($workflow_id);
        $request_id  = $this->schema->request_id($request_id);
        $tool        = $this->schema->tool($tool);
        $dedupe_key  = $this->schema->dedupe_key('tool-start', $workflow_id, $request_id);
        $existing    = $this->find_by_dedupe($dedupe_key);

        if (null !== $existing) {
            $result             = $this->public_event($existing);
            $result['replayed'] = true;
            $result['started_monotonic_ns'] = null;
            $result['terminal'] = $this->terminal_state($workflow_id, $request_id);

            return $result;
        }

        $started_monotonic = ($this->monotonic_clock)();
        $occurred_at       = $this->now();
        $event_id          = $this->new_event_id();
        $event             = $this->insert_event(
            array(
                'event_id'        => $event_id,
                'workflow_id'     => $workflow_id,
                'event_name'      => EventName::TOOL_CALL_STARTED,
                'request_id'      => $request_id,
                'step_number'     => $this->next_step($workflow_id),
                'tool_name'       => $tool['name'],
                'tool_version'    => $tool['version'],
                'risk_class'      => $tool['risk_class'],
                'outcome'         => null,
                'duration_ms'     => null,
                'error_code'      => null,
                'http_status'     => null,
                'product_ids_json' => null,
                'currency'        => null,
                'value'           => null,
                'properties_json' => $this->encode_properties(EventName::TOOL_CALL_STARTED, $properties),
                'dedupe_key'      => $dedupe_key,
                'occurred_at'     => $occurred_at,
                'created_at'      => $occurred_at,
            )
        );

        $replayed = $event['event_id'] !== $event_id;
        if (! $replayed) {
            $this->touch_workflow($workflow_id, $occurred_at, true);
        }

        $result                         = $this->public_event($event);
        $result['replayed']             = $replayed;
        $result['started_monotonic_ns'] = $replayed ? null : $started_monotonic;
        $result['terminal']             = $this->terminal_state($workflow_id, $request_id);

        return $result;
    }

    /**
     * Record the sole terminal outcome for a workflow/request pair.
     *
     * All terminal event names share one dedupe key, so a late success cannot be
     * appended after a failure/cancellation/denial (or vice versa).
     *
     * @param array<string, mixed> $outcome    Duration, stable error, HTTP, and commerce facts.
     * @param array<string, mixed> $properties Allowlisted operational properties.
     * @return array<string, mixed>
     */
    public function terminal(
        string $workflow_id,
        string $request_id,
        string $terminal_event,
        array $outcome = array(),
        array $properties = array()
    ): array {
        $workflow_id   = $this->schema->workflow_id($workflow_id);
        $request_id    = $this->schema->request_id($request_id);
        $terminal_event = $this->schema->terminal_event($terminal_event);
        $dedupe_key    = $this->schema->dedupe_key('tool-terminal', $workflow_id, $request_id);
        $existing      = $this->find_by_dedupe($dedupe_key);

        if (null !== $existing) {
            $result             = $this->public_event($existing);
            $result['replayed'] = true;

            return $result;
        }

        $start = $this->find_by_dedupe($this->schema->dedupe_key('tool-start', $workflow_id, $request_id));
        if (null === $start) {
            throw new LogicException('A terminal tool event requires its start event.');
        }

        $occurred_at = $this->now();
        if (! array_key_exists('duration_ms', $outcome)) {
            $outcome['duration_ms'] = max(0, strtotime($occurred_at . ' UTC') - strtotime((string) $start['occurred_at'] . ' UTC')) * 1000;
        }
        $normalized = $this->schema->outcome($outcome, $terminal_event);
        $event_id   = $this->new_event_id();
        $event      = $this->insert_event(
            array(
                'event_id'         => $event_id,
                'workflow_id'      => $workflow_id,
                'event_name'       => $terminal_event,
                'request_id'       => $request_id,
                'step_number'      => (int) $start['step_number'],
                'tool_name'        => $start['tool_name'],
                'tool_version'     => $start['tool_version'],
                'risk_class'       => $start['risk_class'],
                'outcome'          => $normalized['outcome'],
                'duration_ms'      => $normalized['duration_ms'],
                'error_code'       => $normalized['error_code'],
                'http_status'      => $normalized['http_status'],
                'product_ids_json' => $this->encode_product_ids($normalized['product_ids']),
                'currency'         => $normalized['currency'],
                'value'            => $normalized['value'],
                'properties_json'  => $this->encode_properties($terminal_event, $properties),
                'dedupe_key'       => $dedupe_key,
                'occurred_at'      => $occurred_at,
                'created_at'       => $occurred_at,
            )
        );

        $replayed = $event['event_id'] !== $event_id;
        if (! $replayed) {
            $this->touch_workflow($workflow_id, $occurred_at, false);
        }

        $result             = $this->public_event($event);
        $result['replayed'] = $replayed;

        return $result;
    }

    /**
     * Return the already-resolved terminal outcome, if present.
     *
     * @return array<string, mixed>|null
     */
    public function terminal_state(string $workflow_id, string $request_id): ?array
    {
        $workflow_id = $this->schema->workflow_id($workflow_id);
        $request_id  = $this->schema->request_id($request_id);
        $event       = $this->find_by_dedupe($this->schema->dedupe_key('tool-terminal', $workflow_id, $request_id));

        if (null === $event) {
            return null;
        }

        $result             = $this->public_event($event);
        $result['replayed'] = true;

        return $result;
    }

    /**
     * Record a non-invocation event. Supply a stable dedupe seed for hook/retry safety.
     *
     * @param array<string, mixed> $data Event columns and properties.
     * @return array<string, mixed>
     */
    public function record(string $workflow_id, string $event_name, array $data = array(), ?string $dedupe_seed = null): array
    {
        $workflow_id = $this->schema->workflow_id($workflow_id);
        $event_name  = $this->schema->event_name($event_name);
        if (EventName::TOOL_CALL_STARTED === $event_name || in_array($event_name, EventName::tool_terminal(), true)) {
            throw new InvalidArgumentException('Tool invocation events must use start() and terminal().');
        }

        $request_id = null;
        if (isset($data['request_id']) && null !== $data['request_id']) {
            if (! is_string($data['request_id'])) {
                throw new InvalidArgumentException('Request ID must be a UUID string.');
            }
            $request_id = $this->schema->request_id($data['request_id']);
        }

        $tool = array('name' => null, 'version' => null, 'risk_class' => null);
        if (isset($data['tool'])) {
            if (! is_array($data['tool'])) {
                throw new InvalidArgumentException('Tool metadata must be an object.');
            }
            $tool = $this->schema->tool($data['tool']);
        }

        if (isset($data['outcome']) && ! is_array($data['outcome'])) {
            throw new InvalidArgumentException('Event outcome must be an object.');
        }
        $outcome = isset($data['outcome']) ? $data['outcome'] : array();
        foreach (array('status', 'duration_ms', 'error_code', 'http_status', 'product_ids', 'currency', 'value') as $outcome_key) {
            if (array_key_exists($outcome_key, $data)) {
                $outcome[$outcome_key] = $data[$outcome_key];
            }
        }
        $outcome = $this->schema->outcome($outcome);

        $occurred_at = isset($data['occurred_at']) && is_string($data['occurred_at'])
            ? $this->schema->mysql_timestamp($data['occurred_at'])
            : $this->now();
        $event_id = $this->new_event_id();

        if (null === $dedupe_seed) {
            $dedupe_seed = $event_id;
        } elseif ('' === trim($dedupe_seed) || strlen($dedupe_seed) > 500) {
            throw new InvalidArgumentException('Dedupe seed must contain between 1 and 500 characters.');
        }

        $dedupe_key = $this->schema->dedupe_key('event:' . $event_name, $workflow_id, $dedupe_seed);
        $existing   = $this->find_by_dedupe($dedupe_key);
        if (null !== $existing) {
            $result             = $this->public_event($existing);
            $result['replayed'] = true;

            return $result;
        }

        if (isset($data['properties']) && ! is_array($data['properties'])) {
            throw new InvalidArgumentException('Event properties must be an object.');
        }
        $properties = isset($data['properties']) ? $data['properties'] : array();
        $event      = $this->insert_event(
            array(
                'event_id'         => $event_id,
                'workflow_id'      => $workflow_id,
                'event_name'       => $event_name,
                'request_id'       => $request_id,
                'step_number'      => $this->next_step($workflow_id),
                'tool_name'        => $tool['name'],
                'tool_version'     => $tool['version'],
                'risk_class'       => $tool['risk_class'],
                'outcome'          => $outcome['outcome'],
                'duration_ms'      => $outcome['duration_ms'],
                'error_code'       => $outcome['error_code'],
                'http_status'      => $outcome['http_status'],
                'product_ids_json' => $this->encode_product_ids($outcome['product_ids']),
                'currency'         => $outcome['currency'],
                'value'            => $outcome['value'],
                'properties_json'  => $this->encode_properties($event_name, $properties),
                'dedupe_key'       => $dedupe_key,
                'occurred_at'      => $occurred_at,
                'created_at'       => $this->now(),
            )
        );

        $replayed = $event['event_id'] !== $event_id;
        if (! $replayed) {
            $this->touch_workflow($workflow_id, $occurred_at, false);
        }

        $result             = $this->public_event($event);
        $result['replayed'] = $replayed;

        return $result;
    }

    /**
     * @param array<string, mixed> $row Event database row.
     * @return array<string, mixed>
     */
    private function insert_event(array $row): array
    {
        $result = $this->database->insert(
            $this->events_table(),
            $row,
            array(
                '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            )
        );

        if (false !== $result) {
            return $row;
        }

        $existing = $this->find_by_dedupe((string) $row['dedupe_key']);
        if (null !== $existing) {
            return $existing;
        }

        throw new RuntimeException('The analytics event could not be recorded.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_by_dedupe(string $dedupe_key): ?array
    {
        $sql = $this->database->prepare(
            "SELECT event_id, workflow_id, event_name, request_id, step_number, tool_name, tool_version, risk_class, outcome, duration_ms, error_code, http_status, product_ids_json, currency, value, properties_json, dedupe_key, occurred_at
             FROM {$this->events_table()}
             WHERE dedupe_key = %s
             LIMIT 1",
            $dedupe_key
        );
        $row = $this->database->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private function next_step(string $workflow_id): int
    {
        $sql = $this->database->prepare(
            "SELECT COALESCE(MAX(step_number), 0) + 1
             FROM {$this->events_table()}
             WHERE workflow_id = %s",
            $workflow_id
        );

        return max(1, (int) $this->database->get_var($sql));
    }

    private function touch_workflow(string $workflow_id, string $occurred_at, bool $increment_tool_count): void
    {
        $increment = $increment_tool_count ? ', tool_count = tool_count + 1' : '';
        $sql       = $this->database->prepare(
            "UPDATE {$this->workflows_table()}
             SET last_event_at = %s, updated_at = %s{$increment}
             WHERE id = %s",
            $occurred_at,
            $this->now(),
            $workflow_id
        );
        $this->database->query($sql);
    }

    /**
     * @param array<string, mixed> $properties Properties before redaction.
     */
    private function encode_properties(string $event_name, array $properties): ?string
    {
        $properties = $this->schema->properties($event_name, $properties);

        return array() === $properties ? null : Json::encode($properties);
    }

    /**
     * @param list<int> $product_ids Product IDs.
     */
    private function encode_product_ids(array $product_ids): ?string
    {
        return array() === $product_ids ? null : Json::encode($product_ids);
    }

    /**
     * @param array<string, mixed> $row Database row.
     * @return array<string, mixed>
     */
    private function public_event(array $row): array
    {
        return array(
            'event_id'     => (string) $row['event_id'],
            'workflow_id'  => (string) $row['workflow_id'],
            'request_id'   => null === $row['request_id'] ? null : (string) $row['request_id'],
            'event_name'   => (string) $row['event_name'],
            'step_number'  => (int) $row['step_number'],
            'tool_name'    => null === $row['tool_name'] ? null : (string) $row['tool_name'],
            'outcome'      => null === $row['outcome'] ? null : (string) $row['outcome'],
            'duration_ms'  => null === $row['duration_ms'] ? null : (int) $row['duration_ms'],
            'error_code'   => null === $row['error_code'] ? null : (string) $row['error_code'],
            'http_status'  => null === $row['http_status'] ? null : (int) $row['http_status'],
            'occurred_at'  => (string) $row['occurred_at'],
        );
    }

    private function new_event_id(): string
    {
        return $this->schema->event_id(($this->event_id_generator)());
    }

    private function now(): string
    {
        return $this->schema->mysql_timestamp(($this->clock)());
    }

    private function events_table(): string
    {
        return $this->database->prefix . 'wmcp_events';
    }

    private function workflows_table(): string
    {
        return $this->database->prefix . 'wmcp_workflows';
    }
}
