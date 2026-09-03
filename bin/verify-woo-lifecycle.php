<?php

/**
 * Real WooCommerce CRUD lifecycle acceptance for WP-CLI.
 *
 * The script creates disposable orders through public WooCommerce APIs and
 * verifies the plugin's paid-only attribution, refund recomputation,
 * cancellation removal, and human re-add boundary in the active storage mode.
 *
 * @package WPWebMCP\AgentSNR\Acceptance
 */

use Automattic\WooCommerce\Utilities\OrderUtil;
use WPWebMCP\AgentSNR\Analytics\EventRecorder;
use WPWebMCP\AgentSNR\Analytics\WorkflowService;
use WPWebMCP\AgentSNR\Contract\EventName;
use WPWebMCP\AgentSNR\Contract\Versions;

if (! defined('WP_CLI') || ! WP_CLI) {
    throw new RuntimeException('Run this acceptance script with WP-CLI.');
}

if (! function_exists('wc_create_order') || ! class_exists(OrderUtil::class)) {
    WP_CLI::error('WooCommerce is not available.');
}

/**
 * @throws RuntimeException When an acceptance condition is false.
 */
function wmcp_accept(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @return array<string, mixed>|null
 */
function wmcp_link(int $order_id): ?array
{
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT order_id, workflow_id, attribution_class, confidence, gross_value, refund_value, net_value, currency
             FROM {$wpdb->prefix}wmcp_order_links
             WHERE order_id = %d AND rule_version = %s
             LIMIT 1",
            $order_id,
            Versions::ATTRIBUTION
        ),
        ARRAY_A
    );

    return is_array($row) ? $row : null;
}

/**
 * @return array<string, mixed>
 */
function wmcp_workflow(WorkflowService $workflows, EventRecorder $events, string $demo_hash, int $product_id): array
{
    $workflow = $workflows->current($demo_hash, 'storefront');
    $events->record(
        (string) $workflow['id'],
        EventName::PRODUCT_SEARCHED,
        array('product_ids' => array($product_id)),
        'acceptance-search'
    );
    $events->record(
        (string) $workflow['id'],
        EventName::CART_CHANGED,
        array('product_ids' => array($product_id)),
        'acceptance-cart'
    );
    $events->record(
        (string) $workflow['id'],
        EventName::CHECKOUT_HANDOFF,
        array('product_ids' => array($product_id)),
        'acceptance-handoff'
    );

    return $workflow;
}

/**
 * @return WC_Order
 */
function wmcp_order(
    WC_Product $product,
    ?string $workflow_id,
    ?string $demo_hash,
    bool $agent_line,
    bool $checkout_handoff = true
): WC_Order {
    $order = wc_create_order(array('status' => 'pending', 'created_via' => 'wmcp_acceptance'));
    wmcp_accept($order instanceof WC_Order, 'WooCommerce did not create the acceptance order.');

    $item = new WC_Order_Item_Product();
    $item->set_product($product);
    $item->set_quantity(1);
    $item->set_subtotal((float) $product->get_price());
    $item->set_total((float) $product->get_price());

    $now = gmdate('Y-m-d H:i:s');
    if (null !== $workflow_id && $agent_line) {
        $item->update_meta_data('_wmcp_workflow_id', $workflow_id);
        $item->update_meta_data('_wmcp_added_by_agent', 'yes');
        $item->update_meta_data('_wmcp_modified_by_agent', 'yes');
        $item->update_meta_data('_wmcp_cart_mutation', 'add');
        $item->update_meta_data('_wmcp_first_touch_at', $now);
        $item->update_meta_data('_wmcp_last_touch_at', $now);
    }
    $order->add_item($item);

    if (null !== $workflow_id && null !== $demo_hash) {
        $order->update_meta_data('_wmcp_workflow_id', $workflow_id);
        $order->update_meta_data('_wmcp_protocol', 'webmcp');
        $order->update_meta_data('_wmcp_first_touch_at', $now);
        $order->update_meta_data('_wmcp_last_touch_at', $now);
        $order->update_meta_data('_wmcp_tools_used', array('search_products', 'add_to_cart', 'prepare_checkout'));
        $order->update_meta_data('_wmcp_demo_session_hash', $demo_hash);
        $order->update_meta_data('_wmcp_cart_product_ids', array((int) $product->get_id()));
        $order->update_meta_data('_wmcp_checkout_handoff', $checkout_handoff ? 'yes' : 'no');
    }
    $order->update_meta_data('_wmcp_demo_order', 'yes');
    $order->set_currency('USD');
    $order->calculate_totals();
    $order->save();

    /**
     * Programmatic CRUD does not run WC_Checkout, so fire the documented
     * observation hook explicitly. This exercises the same plugin callback
     * used after a human classic-checkout submission.
     */
    do_action('woocommerce_checkout_order_processed', $order->get_id(), array(), $order);

    return $order;
}

/**
 * @param list<string> $workflow_ids
 * @param list<int>    $order_ids
 */
function wmcp_cleanup(array $workflow_ids, array $order_ids): void
{
    global $wpdb;

    foreach (array_reverse($order_ids) as $order_id) {
        $order = wc_get_order($order_id);
        if ($order instanceof WC_Abstract_Order) {
            wmcp_accept(false !== $order->delete(true), 'A disposable acceptance order could not be deleted.');
        }
        wmcp_accept(! (wc_get_order($order_id) instanceof WC_Abstract_Order), 'A disposable acceptance order remained after deletion.');
        wmcp_accept(null === wmcp_link($order_id), 'An attribution link remained after its acceptance order was deleted.');
    }

    foreach ($workflow_ids as $workflow_id) {
        foreach (array('wmcp_capability_gaps', 'wmcp_order_links', 'wmcp_events') as $suffix) {
            wmcp_accept(
                false !== $wpdb->delete($wpdb->prefix . $suffix, array('workflow_id' => $workflow_id)),
                'A disposable acceptance child row could not be deleted.'
            );
        }
        wmcp_accept(
            false !== $wpdb->delete($wpdb->prefix . 'wmcp_workflows', array('id' => $workflow_id)),
            'A disposable acceptance workflow could not be deleted.'
        );
        $remaining = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wmcp_workflows WHERE id = %s",
                $workflow_id
            )
        );
        wmcp_accept(0 === $remaining, 'A disposable acceptance workflow remained after cleanup.');
    }
}

$orders    = array();
$workflows = array();
$failure   = null;
$success_message = null;

try {
    global $wpdb;

    $product_id = (int) wc_get_product_id_by_sku('wmcp-raintrail-20');
    $product    = wc_get_product($product_id);
    wmcp_accept($product instanceof WC_Product, 'The deterministic demo product was not seeded.');

    $price            = (float) $product->get_price();
    $events           = new EventRecorder($wpdb);
    $workflow_service = new WorkflowService($wpdb, $events);

    // Paid direct order: created is excluded, paid is linked, refunds recompute.
    $direct_hash     = hash('sha256', 'direct-' . wp_generate_uuid4());
    $direct_workflow = wmcp_workflow($workflow_service, $events, $direct_hash, $product_id);
    $workflows[]     = (string) $direct_workflow['id'];
    $direct_order    = wmcp_order($product, (string) $direct_workflow['id'], $direct_hash, true);
    $orders[]        = $direct_order->get_id();

    wmcp_accept(null === wmcp_link($direct_order->get_id()), 'An unpaid order was attributed as revenue.');
    $direct_order->payment_complete('wmcp-acceptance-direct');
    $direct_order = wc_get_order($direct_order->get_id());
    wmcp_accept($direct_order instanceof WC_Order, 'The paid direct order could not be reloaded.');

    $link = wmcp_link($direct_order->get_id());
    wmcp_accept(is_array($link), 'The paid direct order did not receive an attribution link.');
    wmcp_accept('direct' === $link['attribution_class'], 'The direct order received the wrong attribution class.');
    wmcp_accept('high' === $link['confidence'], 'The direct order received the wrong confidence.');
    wmcp_accept(abs((float) $link['gross_value'] - $price) < 0.0001, 'Paid gross revenue is incorrect.');
    wmcp_accept(abs((float) $link['refund_value']) < 0.0001, 'A new paid order has a non-zero refund.');

    $partial_amount = min(20.0, max(0.01, $price / 2));
    $partial_refund = wc_create_refund(
        array(
            'amount'         => $partial_amount,
            'reason'         => 'WP WebMCP partial-refund acceptance',
            'order_id'       => $direct_order->get_id(),
            'refund_payment' => false,
            'restock_items'  => false,
        )
    );
    wmcp_accept($partial_refund instanceof WC_Order_Refund, 'WooCommerce did not create the partial refund.');

    $link = wmcp_link($direct_order->get_id());
    wmcp_accept(is_array($link), 'Partial refund removed the paid attribution link.');
    wmcp_accept(abs((float) $link['refund_value'] - $partial_amount) < 0.0001, 'Partial refund value is incorrect.');
    wmcp_accept(abs((float) $link['net_value'] - ($price - $partial_amount)) < 0.0001, 'Partial-refund net revenue is incorrect.');

    $full_refund = wc_create_refund(
        array(
            'amount'         => $price - $partial_amount,
            'reason'         => 'WP WebMCP full-refund acceptance',
            'order_id'       => $direct_order->get_id(),
            'refund_payment' => false,
            'restock_items'  => false,
        )
    );
    wmcp_accept($full_refund instanceof WC_Order_Refund, 'WooCommerce did not create the final refund.');

    $link = wmcp_link($direct_order->get_id());
    wmcp_accept(is_array($link), 'Full refund removed the historical paid attribution link.');
    wmcp_accept(abs((float) $link['refund_value'] - $price) < 0.0001, 'Full refund value is incorrect.');
    wmcp_accept(abs((float) $link['net_value']) < 0.0001, 'Fully refunded net revenue is not zero.');

    $refund_events = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wmcp_events WHERE workflow_id = %s AND event_name = %s",
            (string) $direct_workflow['id'],
            EventName::ORDER_REFUNDED
        )
    );
    wmcp_accept($refund_events >= 2, 'Refund lifecycle events were not recorded.');
    do_action('woocommerce_order_refunded', $direct_order->get_id(), $partial_refund->get_id());
    do_action('woocommerce_order_refunded', $direct_order->get_id(), $partial_refund->get_id());
    $refund_events_after_replay = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wmcp_events WHERE workflow_id = %s AND event_name = %s",
            (string) $direct_workflow['id'],
            EventName::ORDER_REFUNDED
        )
    );
    wmcp_accept($refund_events === $refund_events_after_replay, 'Repeated refund callbacks duplicated analytics events.');

    // A previously paid order must disappear from attribution when cancelled.
    $cancel_hash     = hash('sha256', 'cancel-' . wp_generate_uuid4());
    $cancel_workflow = wmcp_workflow($workflow_service, $events, $cancel_hash, $product_id);
    $workflows[]     = (string) $cancel_workflow['id'];
    $cancel_order    = wmcp_order($product, (string) $cancel_workflow['id'], $cancel_hash, true);
    $orders[]        = $cancel_order->get_id();
    $cancel_order->payment_complete('wmcp-acceptance-cancel');
    wmcp_accept(is_array(wmcp_link($cancel_order->get_id())), 'The cancellation fixture was not linked after payment.');
    $cancel_order->update_status('cancelled', 'WP WebMCP cancellation acceptance');
    wmcp_accept(null === wmcp_link($cancel_order->get_id()), 'A cancelled order remained in attributed revenue.');

    // Historical search/cart/handoff evidence cannot make a human-readded line direct.
    $readd_hash     = hash('sha256', 'readd-' . wp_generate_uuid4());
    $readd_workflow = wmcp_workflow($workflow_service, $events, $readd_hash, $product_id);
    $workflows[]    = (string) $readd_workflow['id'];
    $readd_order    = wmcp_order($product, (string) $readd_workflow['id'], $readd_hash, false);
    $orders[]       = $readd_order->get_id();
    $readd_order->payment_complete('wmcp-acceptance-readd');
    $link = wmcp_link($readd_order->get_id());
    wmcp_accept(is_array($link), 'The prior product evidence was not preserved as influenced attribution.');
    wmcp_accept('influenced' === $link['attribution_class'], 'A human-readded line was incorrectly classified as direct or assisted.');

    // A paid human-only order remains outside attributed reporting.
    $human_order = wmcp_order($product, null, null, false, false);
    $orders[]    = $human_order->get_id();
    $human_order->payment_complete('wmcp-acceptance-human');
    wmcp_accept(null === wmcp_link($human_order->get_id()), 'A human-only order received agent attribution.');

    $storage = OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'legacy';
    $success_message = sprintf(
        'Woo lifecycle passed in %s mode: unpaid exclusion, direct payment, partial/full refunds, cancellation removal, human re-add boundary, and human-only exclusion.',
        $storage
    );
} catch (Throwable $throwable) {
    $failure = $throwable;
} finally {
    try {
        wmcp_cleanup($workflows, $orders);
    } catch (Throwable $cleanup_error) {
        $failure = $failure instanceof Throwable
            ? new RuntimeException($failure->getMessage() . ' Cleanup also failed: ' . $cleanup_error->getMessage())
            : $cleanup_error;
    }
}

if ($failure instanceof Throwable) {
    WP_CLI::error($failure->getMessage());
}

WP_CLI::success((string) $success_message);
