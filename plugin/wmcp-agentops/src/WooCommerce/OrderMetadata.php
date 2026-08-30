<?php

/**
 * HPOS-safe workflow provenance on WooCommerce orders and line items.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WooCommerce;

use WPWebMCP\AgentOps\Demo\DemoMode;

final class OrderMetadata
{
    public function __construct(private readonly SessionCorrelator $correlator)
    {
    }

    /**
     * Classic checkout hook: woocommerce_checkout_create_order.
     *
     * @param array<string, mixed> $data Checkout data.
     */
    public function classic_order(object $order, array $data = array()): void
    {
        unset($data);
        $this->apply_order($order);
    }

    /**
     * Store API hook: woocommerce_store_api_checkout_update_order_meta.
     */
    public function store_api_order(object $order): void
    {
        $this->apply_order($order);
    }

    /**
     * Shared by classic checkout and Store API because both use
     * WC_Checkout::create_order_line_items().
     *
     * @param array<string, mixed> $values Cart item data.
     */
    public function line_item(object $item, string $cart_item_key, array $values, object $order): void
    {
        unset($cart_item_key, $order);
        $provenance = $values[SessionCorrelator::CART_ITEM_KEY] ?? null;
        if (! is_array($provenance) || ! method_exists($item, 'update_meta_data')) {
            return;
        }

        $workflow_id = isset($provenance['workflow_id']) ? (string) $provenance['workflow_id'] : '';
        if (1 !== preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $workflow_id)) {
            return;
        }

        $item->update_meta_data('_wmcp_workflow_id', $workflow_id);
        $item->update_meta_data('_wmcp_added_by_agent', true === ($provenance['added_by_agent'] ?? false) ? 'yes' : 'no');
        $item->update_meta_data('_wmcp_modified_by_agent', true === ($provenance['modified_by_agent'] ?? false) ? 'yes' : 'no');
        $item->update_meta_data('_wmcp_cart_mutation', sanitize_key((string) ($provenance['last_mutation'] ?? '')));
        $item->update_meta_data('_wmcp_first_touch_at', sanitize_text_field((string) ($provenance['first_touch_at'] ?? '')));
        $item->update_meta_data('_wmcp_last_touch_at', sanitize_text_field((string) ($provenance['last_touch_at'] ?? '')));
    }

    public function apply_order(object $order): void
    {
        if (! method_exists($order, 'update_meta_data')) {
            return;
        }

        $provenance = $this->correlator->order_provenance();
        if (is_array($provenance)) {
            $workflow_id = (string) ($provenance['workflow_id'] ?? '');
            if (1 === preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $workflow_id)) {
                $order->update_meta_data('_wmcp_workflow_id', $workflow_id);
                $order->update_meta_data('_wmcp_protocol', sanitize_key((string) ($provenance['protocol'] ?? 'webmcp')));
                $order->update_meta_data('_wmcp_first_touch_at', sanitize_text_field((string) ($provenance['first_touch_at'] ?? '')));
                $order->update_meta_data('_wmcp_last_touch_at', sanitize_text_field((string) ($provenance['last_touch_at'] ?? '')));
                $order->update_meta_data('_wmcp_tools_used', array_values(array_slice(array_unique(array_map('sanitize_key', (array) ($provenance['tools_used'] ?? array()))), 0, 20)));
                $order->update_meta_data('_wmcp_demo_session_hash', (string) ($provenance['demo_session_hash'] ?? ''));
                $order->update_meta_data('_wmcp_wc_session_hash', (string) ($provenance['wc_session_hash'] ?? ''));
                $order->update_meta_data('_wmcp_cart_product_ids', array_values(array_unique(array_map('intval', (array) ($provenance['cart_product_ids'] ?? array())))));
                $order->update_meta_data('_wmcp_checkout_handoff', true === ($provenance['checkout_handoff'] ?? false) ? 'yes' : 'no');
            }
        }

        if (DemoMode::enabled()) {
            $order->update_meta_data('_wmcp_demo_order', 'yes');
        }
    }

    /**
     * @param array<string, mixed> $attribution Selected attribution.
     */
    public function write_attribution(object $order, array $attribution): void
    {
        if (! method_exists($order, 'update_meta_data')) {
            return;
        }

        $order->update_meta_data('_wmcp_workflow_id', (string) $attribution['workflow_id']);
        $order->update_meta_data('_wmcp_attribution_class', (string) $attribution['attribution_class']);
        $order->update_meta_data('_wmcp_attribution_confidence', (string) $attribution['confidence']);
        $order->update_meta_data('_wmcp_attribution_version', (string) $attribution['rule_version']);
        $order->update_meta_data('_wmcp_first_touch_at', (string) $attribution['first_touch_at']);
        $order->update_meta_data('_wmcp_last_touch_at', (string) $attribution['last_touch_at']);

        if (method_exists($order, 'save_meta_data')) {
            $order->save_meta_data();
        }
    }

    public function clear_attribution(object $order): void
    {
        if (! method_exists($order, 'delete_meta_data')) {
            return;
        }

        foreach (array('_wmcp_attribution_class', '_wmcp_attribution_confidence', '_wmcp_attribution_version') as $key) {
            $order->delete_meta_data($key);
        }
        if (method_exists($order, 'save_meta_data')) {
            $order->save_meta_data();
        }
    }

    public function workflow_id(object $order): ?string
    {
        if (! method_exists($order, 'get_meta')) {
            return null;
        }

        $workflow_id = strtoupper((string) $order->get_meta('_wmcp_workflow_id', true));

        return 1 === preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $workflow_id) ? $workflow_id : null;
    }
}
