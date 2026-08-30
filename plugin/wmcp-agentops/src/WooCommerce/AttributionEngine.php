<?php

/**
 * Idempotent one-primary-workflow attribution per Woo order/rule version.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WooCommerce;

use RuntimeException;
use WPWebMCP\AgentOps\Contract\EventName;
use WPWebMCP\AgentOps\Contract\Versions;
use WPWebMCP\AgentOps\Demo\DemoMode;
use WPWebMCP\AgentOps\Support\Clock;
use WPWebMCP\AgentOps\Support\Json;

final class AttributionEngine
{
    private object $database;

    public function __construct(
        private readonly AttributionRules $rules,
        private readonly RevenueSnapshot $revenue,
        private readonly OrderMetadata $metadata,
        ?object $database = null
    ) {
        if (null === $database) {
            global $wpdb;
            $database = $wpdb;
        }

        if (! is_object($database)) {
            throw new RuntimeException('A wpdb-compatible database object is required.');
        }

        $this->database = $database;
    }

    /**
     * @param int|object $order_or_id Woo order or ID.
     * @return array<string, mixed>|null
     */
    public function recompute($order_or_id): ?array
    {
        $order = is_object($order_or_id) ? $order_or_id : wc_get_order((int) $order_or_id);
        if (! is_object($order) || ! method_exists($order, 'get_id') || 1 > (int) $order->get_id()) {
            return null;
        }

        $order_id    = (int) $order->get_id();
        $product_ids = $this->purchased_product_ids($order);
        $selected    = $this->rules->select_primary($this->candidates($order, $product_ids), $product_ids);

        if (null === $selected) {
            $this->delete_link($order_id);
            $this->metadata->clear_attribution($order);

            return null;
        }

        $snapshot = $this->revenue->from_order($order);
        if ($snapshot['gross_value'] <= 0.0) {
            $this->delete_link($order_id);
            $this->metadata->clear_attribution($order);

            return null;
        }

        $now      = Clock::mysql();
        $row      = array(
            'order_id'                => $order_id,
            'workflow_id'             => (string) $selected['workflow_id'],
            'attribution_class'       => (string) $selected['attribution_class'],
            'confidence'              => (string) $selected['confidence'],
            'rule_version'            => Versions::ATTRIBUTION,
            'first_touch_at'          => $this->timestamp((string) $selected['first_touch_at'], $now),
            'last_touch_at'           => $this->timestamp((string) $selected['last_touch_at'], $now),
            'gross_value'             => number_format($snapshot['gross_value'], 6, '.', ''),
            'refund_value'            => number_format($snapshot['refund_value'], 6, '.', ''),
            'net_value'               => number_format($snapshot['net_value'], 6, '.', ''),
            'currency'                => $this->currency($snapshot['currency']),
            'matched_products_json'   => Json::encode($selected['matched_products']),
            'evidence_event_ids_json' => Json::encode($selected['evidence_event_ids']),
            'updated_at'              => $now,
        );

        $this->upsert($row, $now);
        $this->metadata->write_attribution($order, $row);

        return $row;
    }

    public function remove_for_order(int $order_id): void
    {
        if ($order_id > 0) {
            $this->delete_link($order_id);
        }
    }

    /**
     * @param list<int> $purchased_product_ids Purchased product IDs.
     * @return list<array<string, mixed>>
     */
    private function candidates(object $order, array $purchased_product_ids): array
    {
        $by_workflow = array();
        $workflow_id = $this->metadata->workflow_id($order);
        $wc_hash     = method_exists($order, 'get_meta') ? strtolower((string) $order->get_meta('_wmcp_wc_session_hash', true)) : '';
        $demo_hash   = method_exists($order, 'get_meta') ? strtolower((string) $order->get_meta('_wmcp_demo_session_hash', true)) : '';
        $created_at  = $this->order_created_at($order);

        foreach ($this->event_rows($workflow_id, $wc_hash, $demo_hash, $created_at) as $event) {
            $id = strtoupper((string) ($event['workflow_id'] ?? ''));
            if (1 !== preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $id)) {
                continue;
            }

            $candidate = $by_workflow[$id] ?? $this->empty_candidate($id, (string) ($event['occurred_at'] ?? $created_at));
            $products  = $this->decode_ids((string) ($event['product_ids_json'] ?? ''));
            $name      = (string) ($event['event_name'] ?? '');

            if (in_array($name, array(EventName::PRODUCT_SEARCHED, EventName::PRODUCT_VIEWED, EventName::PRODUCTS_COMPARED), true)) {
                $candidate['influence_product_ids'] = $this->merge_ids($candidate['influence_product_ids'], $products);
            }
            if (EventName::CHECKOUT_HANDOFF === $name) {
                $candidate['checkout_handoff'] = true;
            }

            $event_id = (string) ($event['event_id'] ?? '');
            if ('' !== $event_id) {
                $candidate['evidence_event_ids'][] = $event_id;
            }
            $candidate['first_touch_at'] = min((string) $candidate['first_touch_at'], (string) $event['occurred_at']);
            $candidate['last_touch_at']  = max((string) $candidate['last_touch_at'], (string) $event['occurred_at']);
            $by_workflow[$id]            = $candidate;
        }

        if (null !== $workflow_id) {
            $candidate = $by_workflow[$workflow_id] ?? $this->empty_candidate($workflow_id, $created_at);
            if (method_exists($order, 'get_meta')) {
                $candidate['checkout_handoff'] = 'yes' === (string) $order->get_meta('_wmcp_checkout_handoff', true) || $candidate['checkout_handoff'];
                $meta_first = $this->timestamp((string) $order->get_meta('_wmcp_first_touch_at', true), $candidate['first_touch_at']);
                $meta_last  = $this->timestamp((string) $order->get_meta('_wmcp_last_touch_at', true), $candidate['last_touch_at']);
                $candidate['first_touch_at'] = min($candidate['first_touch_at'], $meta_first);
                $candidate['last_touch_at']  = max($candidate['last_touch_at'], $meta_last);
            }
            $by_workflow[$workflow_id] = $candidate;
        }

        if (method_exists($order, 'get_items')) {
            foreach ((array) $order->get_items('line_item') as $item) {
                if (! is_object($item) || ! method_exists($item, 'get_meta')) {
                    continue;
                }

                $id = strtoupper((string) $item->get_meta('_wmcp_workflow_id', true));
                $agent_changed_line = 'yes' === (string) $item->get_meta('_wmcp_added_by_agent', true)
                    || 'yes' === (string) $item->get_meta('_wmcp_modified_by_agent', true);
                if (
                    1 !== preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $id)
                    || ! $agent_changed_line
                    || (DemoMode::enabled() && ! $this->workflow_in_demo_scope($id, $demo_hash))
                ) {
                    continue;
                }

                $candidate = $by_workflow[$id] ?? $this->empty_candidate($id, $created_at);
                $item_id   = method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0;
                $candidate['cart_product_ids'] = $this->merge_ids($candidate['cart_product_ids'], array($item_id));
                $item_first = $this->timestamp((string) $item->get_meta('_wmcp_first_touch_at', true), $candidate['first_touch_at']);
                $item_last  = $this->timestamp((string) $item->get_meta('_wmcp_last_touch_at', true), $candidate['last_touch_at']);
                $candidate['first_touch_at'] = min($candidate['first_touch_at'], $item_first);
                $candidate['last_touch_at']  = max($candidate['last_touch_at'], $item_last);
                $by_workflow[$id]              = $candidate;
            }
        }

        foreach ($by_workflow as &$candidate) {
            $candidate['cart_product_ids']      = array_values(array_intersect($candidate['cart_product_ids'], $purchased_product_ids));
            $candidate['influence_product_ids'] = array_values(array_intersect($candidate['influence_product_ids'], $purchased_product_ids));
            $candidate['evidence_event_ids']    = array_values(array_unique($candidate['evidence_event_ids']));
        }
        unset($candidate);

        return array_values($by_workflow);
    }

    private function workflow_in_demo_scope(string $workflow_id, string $demo_hash): bool
    {
        if (1 !== preg_match('/\A[a-f0-9]{64}\z/', $demo_hash)) {
            return false;
        }

        $found = $this->database->get_var(
            $this->database->prepare(
                "SELECT id FROM {$this->workflows_table()} WHERE id = %s AND demo_session_hash = %s LIMIT 1",
                $workflow_id,
                $demo_hash
            )
        );

        return is_string($found) && hash_equals($workflow_id, $found);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function event_rows(?string $workflow_id, string $wc_hash, string $demo_hash, string $created_at): array
    {
        $where = array();
        $args  = array();
        if (1 === preg_match('/\A[a-f0-9]{64}\z/', $wc_hash)) {
            $where[] = 'w.wc_session_hash = %s';
            $args[]  = $wc_hash;
        }
        if (null !== $workflow_id) {
            $where[] = 'e.workflow_id = %s';
            $args[]  = $workflow_id;
        }
        if (array() === $where) {
            return array();
        }

        $events = array(EventName::PRODUCT_SEARCHED, EventName::PRODUCT_VIEWED, EventName::PRODUCTS_COMPARED, EventName::CART_CHANGED, EventName::CHECKOUT_HANDOFF);
        $quoted = implode(',', array_fill(0, count($events), '%s'));
        $args   = array_merge($events, $args);
        $demo_scope = '';
        if (DemoMode::enabled()) {
            if (1 !== preg_match('/\A[a-f0-9]{64}\z/', $demo_hash)) {
                return array();
            }
            $demo_scope = ' AND w.demo_session_hash = %s';
            $args[]     = $demo_hash;
        }
        $args[] = $created_at;
        $sql    = $this->database->prepare(
            "SELECT e.event_id, e.workflow_id, e.event_name, e.product_ids_json, e.properties_json, e.occurred_at
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE e.event_name IN ({$quoted})
               AND (" . implode(' OR ', $where) . ")
               {$demo_scope}
               AND e.occurred_at <= %s
             ORDER BY e.occurred_at ASC, e.id ASC",
            ...$args
        );
        $rows = $this->database->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    /**
     * @return list<int>
     */
    private function purchased_product_ids(object $order): array
    {
        $ids = array();
        if (! method_exists($order, 'get_items')) {
            return $ids;
        }

        foreach ((array) $order->get_items('line_item') as $item) {
            if (is_object($item) && method_exists($item, 'get_product_id')) {
                $ids[] = (int) $item->get_product_id();
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param array<string, mixed> $row Attribution row.
     */
    private function upsert(array $row, string $now): void
    {
        $existing = $this->database->get_var(
            $this->database->prepare(
                "SELECT id FROM {$this->links_table()} WHERE order_id = %d AND rule_version = %s LIMIT 1",
                $row['order_id'],
                $row['rule_version']
            )
        );

        if (null !== $existing) {
            $updated = $this->database->update(
                $this->links_table(),
                $row,
                array('id' => (int) $existing)
            );
            if (false === $updated) {
                throw new RuntimeException('The WooCommerce attribution row could not be updated.');
            }

            return;
        }

        $insert = $row;
        $insert['created_at'] = $now;
        if (false === $this->database->insert($this->links_table(), $insert)) {
            $existing = $this->database->get_var(
                $this->database->prepare(
                    "SELECT id FROM {$this->links_table()} WHERE order_id = %d AND rule_version = %s LIMIT 1",
                    $row['order_id'],
                    $row['rule_version']
                )
            );
            if (null === $existing || false === $this->database->update($this->links_table(), $row, array('id' => (int) $existing))) {
                throw new RuntimeException('The WooCommerce attribution row could not be saved.');
            }
        }
    }

    private function delete_link(int $order_id): void
    {
        $this->database->delete($this->links_table(), array('order_id' => $order_id, 'rule_version' => Versions::ATTRIBUTION));
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_candidate(string $workflow_id, string $timestamp): array
    {
        return array(
            'workflow_id'          => $workflow_id,
            'cart_product_ids'     => array(),
            'influence_product_ids' => array(),
            'checkout_handoff'     => false,
            'first_touch_at'       => $timestamp,
            'last_touch_at'        => $timestamp,
            'evidence_event_ids'   => array(),
        );
    }

    /**
     * @return list<int>
     */
    private function decode_ids(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_unique(array_filter(array_map('intval', $decoded)))) : array();
    }

    /**
     * @param list<int> $left Existing IDs.
     * @param list<int> $right New IDs.
     * @return list<int>
     */
    private function merge_ids(array $left, array $right): array
    {
        return array_values(array_unique(array_filter(array_map('intval', array_merge($left, $right)))));
    }

    private function order_created_at(object $order): string
    {
        $created = method_exists($order, 'get_date_created') ? $order->get_date_created() : null;

        return is_object($created) && method_exists($created, 'getTimestamp')
            ? gmdate('Y-m-d H:i:s', (int) $created->getTimestamp())
            : Clock::mysql();
    }

    private function timestamp(string $value, string $fallback): string
    {
        return 1 === preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value) ? $value : $fallback;
    }

    private function currency(string $currency): string
    {
        $currency = strtoupper($currency);

        if (1 !== preg_match('/\A[A-Z]{3}\z/', $currency)) {
            throw new RuntimeException('The WooCommerce order currency is invalid.');
        }

        return $currency;
    }

    private function workflows_table(): string
    {
        return $this->database->prefix . 'wmcp_workflows';
    }

    private function events_table(): string
    {
        return $this->database->prefix . 'wmcp_events';
    }

    private function links_table(): string
    {
        return $this->database->prefix . 'wmcp_order_links';
    }
}
