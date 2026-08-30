<?php

/**
 * Correlates the active WebMCP workflow with the current Woo session and cart.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WooCommerce;

use WPWebMCP\AgentOps\Analytics\WorkflowService;
use WPWebMCP\AgentOps\Privacy\ActorHasher;
use WPWebMCP\AgentOps\Support\Clock;
use WPWebMCP\AgentOps\WebMCP\ExecutionContext;

final class SessionCorrelator
{
    public const SESSION_KEY = 'wmcp_agentops_provenance';
    public const CART_ITEM_KEY = 'wmcp_agentops_provenance';

    public function __construct(
        private readonly CartSession $cart_session,
        private readonly WorkflowService $workflows,
        private readonly ActorHasher $hasher
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $context = ExecutionContext::current();
        if (! is_array($context) || ! isset($context['workflow_id']) || ! is_string($context['workflow_id'])) {
            return null;
        }

        $workflow_id = strtoupper($context['workflow_id']);
        if (1 !== preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $workflow_id)) {
            return null;
        }

        $demo_session_hash = $this->demo_session_hash($context);
        $customer_id       = $this->cart_session->customer_id();
        // Read tools can be the first Woo touch. Persist the generated guest
        // session cookie now so the next cart request resolves the same hash.
        $this->cart_session->persist();
        if ('' === $customer_id && null === $demo_session_hash) {
            return null;
        }

        $wc_session_hash = '' !== $customer_id
            ? $this->hasher->hex($customer_id)
            : $demo_session_hash;
        if (! $this->workflows->attach_woocommerce_session($workflow_id, $wc_session_hash)) {
            return null;
        }

        return array(
            'workflow_id'       => $workflow_id,
            'wc_session_hash'    => $wc_session_hash,
            'demo_session_hash'  => $demo_session_hash,
            'protocol'           => isset($context['protocol']) && is_string($context['protocol']) ? $context['protocol'] : 'webmcp',
            'tool_name'          => isset($context['tool_name']) && is_string($context['tool_name']) ? $context['tool_name'] : '',
            'request_id'         => isset($context['request_id']) && is_string($context['request_id']) ? $context['request_id'] : null,
        );
    }

    /**
     * @param list<int> $product_ids Product IDs touched.
     * @return array<string, mixed>|null
     */
    public function touch(string $activity, array $product_ids = array()): ?array
    {
        $current = $this->current();
        if (null === $current) {
            return null;
        }

        $session   = $this->cart_session->session();
        $stored    = method_exists($session, 'get') ? $session->get(self::SESSION_KEY, array()) : array();
        $stored    = is_array($stored) ? $stored : array();
        $workflows = isset($stored['workflows']) && is_array($stored['workflows']) ? $stored['workflows'] : array();
        $workflow  = isset($workflows[$current['workflow_id']]) && is_array($workflows[$current['workflow_id']])
            ? $workflows[$current['workflow_id']]
            : array();
        $now       = Clock::mysql();

        $workflow['workflow_id']      = $current['workflow_id'];
        $workflow['protocol']         = $current['protocol'];
        $workflow['demo_session_hash'] = $current['demo_session_hash'];
        $workflow['wc_session_hash']  = $current['wc_session_hash'];
        $workflow['first_touch_at']   = (string) ($workflow['first_touch_at'] ?? $now);
        $workflow['last_touch_at']    = $now;
        $workflow['tools_used']       = $this->append((array) ($workflow['tools_used'] ?? array()), (string) $current['tool_name'], 20);
        $workflow['product_ids']      = $this->append_ids((array) ($workflow['product_ids'] ?? array()), $product_ids, 50);
        $workflow['last_activity']    = $activity;

        if (in_array($activity, array('cart_add', 'cart_update'), true)) {
            $workflow['cart_product_ids'] = $this->append_ids((array) ($workflow['cart_product_ids'] ?? array()), $product_ids, 50);
        }
        if ('checkout_handoff' === $activity) {
            $workflow['checkout_handoff'] = true;
        }

        $workflows[$current['workflow_id']] = $workflow;
        if (10 < count($workflows)) {
            uasort(
                $workflows,
                static fn (array $left, array $right): int => strcmp((string) ($right['last_touch_at'] ?? ''), (string) ($left['last_touch_at'] ?? ''))
            );
            $workflows = array_slice($workflows, 0, 10, true);
        }

        $stored = array(
            'current_workflow_id' => $current['workflow_id'],
            'wc_session_hash'     => $current['wc_session_hash'],
            'revision'            => ((int) ($stored['revision'] ?? 0)) + 1,
            'workflows'           => $workflows,
        );

        if (method_exists($session, 'set')) {
            $session->set(self::SESSION_KEY, $stored);
        }

        return $workflow;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function order_provenance(): ?array
    {
        $session = $this->cart_session->session();
        $stored  = method_exists($session, 'get') ? $session->get(self::SESSION_KEY, array()) : array();
        if (! is_array($stored) || ! isset($stored['current_workflow_id'], $stored['workflows']) || ! is_array($stored['workflows'])) {
            return null;
        }

        $workflow_id = (string) $stored['current_workflow_id'];
        $workflow    = $stored['workflows'][$workflow_id] ?? null;

        return is_array($workflow) ? $workflow : null;
    }

    /**
     * @param array<string, mixed>|null $existing Existing cart-line provenance.
     * @return array<string, mixed>|null
     */
    public function line_provenance(string $mutation, ?array $existing = null): ?array
    {
        $current = $this->current();
        if (null === $current) {
            return $existing;
        }

        $now      = Clock::mysql();
        $existing = is_array($existing) ? $existing : array();

        return array(
            'workflow_id'      => $current['workflow_id'],
            'first_touch_at'   => (string) ($existing['first_touch_at'] ?? $now),
            'last_touch_at'    => $now,
            'added_by_agent'   => true === ($existing['added_by_agent'] ?? false) || 'add' === $mutation,
            'modified_by_agent' => in_array($mutation, array('add', 'update'), true),
            'last_mutation'    => $mutation,
            'request_id'       => $current['request_id'],
        );
    }

    /**
     * @param array<string, mixed> $context Execution context.
     */
    private function demo_session_hash(array $context): ?string
    {
        $hash = $context['demo_session_hash'] ?? ($context['session_hash_hex'] ?? null);
        if (is_string($hash) && 1 === preg_match('/\A[a-f0-9]{64}\z/', strtolower($hash))) {
            return strtolower($hash);
        }

        return null;
    }

    /**
     * @param list<string> $values Existing values.
     * @return list<string>
     */
    private function append(array $values, string $value, int $limit): array
    {
        if ('' !== $value) {
            $values[] = $value;
        }

        return array_slice(array_values(array_unique(array_filter(array_map('strval', $values)))), -$limit);
    }

    /**
     * @param list<int> $existing Existing IDs.
     * @param list<int> $new New IDs.
     * @return list<int>
     */
    private function append_ids(array $existing, array $new, int $limit): array
    {
        $values = array_values(array_unique(array_filter(array_map('intval', array_merge($existing, $new)))));

        return array_slice($values, -$limit);
    }
}
