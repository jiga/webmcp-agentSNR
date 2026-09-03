<?php

/**
 * WooCommerce order/payment/status/refund observations and recalculation hooks.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

use Throwable;
use WPWebMCP\AgentSNR\Analytics\EventRecorder;
use WPWebMCP\AgentSNR\Analytics\WorkflowService;
use WPWebMCP\AgentSNR\Contract\EventName;

final class CommerceObserver
{
    public function __construct(
        private readonly EventRecorder $events,
        private readonly OrderMetadata $metadata,
        private readonly AttributionEngine $attribution,
        private readonly RevenueSnapshot $revenue,
        private readonly ?WorkflowService $workflows = null
    ) {
    }

    public function hooks(): void
    {
        add_action('woocommerce_checkout_order_processed', array($this, 'classic_order_created'), 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'store_api_order_created'), 10, 1);
        add_action('woocommerce_payment_complete', array($this, 'payment_complete'), 10, 2);
        add_action('woocommerce_order_status_changed', array($this, 'status_changed'), 10, 4);
        add_action('woocommerce_refund_created', array($this, 'refund_created'), 10, 2);
        add_action('woocommerce_order_refunded', array($this, 'order_refunded'), 10, 2);
        add_action('woocommerce_update_order_refund', array($this, 'refund_updated'), 10, 2);
        add_action('woocommerce_refund_deleted', array($this, 'refund_deleted'), 10, 2);
        add_action('woocommerce_before_delete_order', array($this, 'order_deleted'), 10, 1);
        add_action('woocommerce_before_trash_order', array($this, 'order_deleted'), 10, 1);
        add_action('woocommerce_untrash_order', array($this, 'order_restored'), 10, 1);
    }

    /**
     * @param array<string, mixed> $posted_data Classic checkout data, intentionally ignored.
     */
    public function classic_order_created(int $order_id, array $posted_data = array(), ?object $order = null): void
    {
        unset($posted_data);
        $this->observe_created($order ?? wc_get_order($order_id), 'classic_checkout');
    }

    public function store_api_order_created(object $order): void
    {
        $this->observe_created($order, 'store_api_checkout');
    }

    public function payment_complete(int $order_id, string $transaction_id = ''): void
    {
        unset($transaction_id);
        $this->observe_paid(wc_get_order($order_id), 'payment_complete');
    }

    public function status_changed(int $order_id, string $from, string $to, ?object $order = null): void
    {
        $order = $order ?? wc_get_order($order_id);
        if (! is_object($order)) {
            return;
        }

        /**
         * Observe every Woo status transition without inventing a non-canonical
         * analytics event name. Consumers receive an HPOS-safe WC_Order object.
         */
        do_action('wmcp_agentsnr_woocommerce_order_status_observed', $order, $from, $to);

        if ('cancelled' === $to) {
            $this->record_order_event($order, EventName::ORDER_CANCELLED, 'order:' . $order_id . ':cancelled', array('source' => 'status_changed'));
            $this->resolve_workflow($order, false, 'order_cancelled');
        } elseif (in_array($to, array('processing', 'completed'), true)) {
            $this->observe_paid($order, 'status_changed');
        }

        $this->safe_recompute($order);
    }

    /**
     * @param array<string, mixed> $args Refund arguments, intentionally ignored.
     */
    public function refund_created(int $refund_id, array $args = array()): void
    {
        unset($args);
        $refund = wc_get_order($refund_id);
        if (! is_object($refund) || ! method_exists($refund, 'get_parent_id')) {
            return;
        }

        $this->observe_refund((int) $refund->get_parent_id(), $refund_id, 'created', 'refund:' . $refund_id . ':created');
    }

    public function order_refunded(int $order_id, int $refund_id): void
    {
        $this->observe_refund($order_id, $refund_id, 'created', 'refund:' . $refund_id . ':created');
    }

    public function refund_updated(int $refund_id, object $refund): void
    {
        if (! method_exists($refund, 'get_parent_id')) {
            return;
        }

        $amount   = method_exists($refund, 'get_amount') ? (string) $refund->get_amount() : '';
        $modified = method_exists($refund, 'get_date_modified') && is_object($refund->get_date_modified())
            ? (string) $refund->get_date_modified()->getTimestamp()
            : '';
        $seed = 'refund:' . $refund_id . ':updated:' . hash('sha256', $amount . '|' . $modified);

        $this->observe_refund((int) $refund->get_parent_id(), $refund_id, 'updated', $seed);
    }

    public function refund_deleted(int $refund_id, int $order_id): void
    {
        $this->observe_refund($order_id, $refund_id, 'deleted', 'refund:' . $refund_id . ':deleted');
    }

    public function order_deleted(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (is_object($order)) {
            $this->record_order_event(
                $order,
                EventName::ORDER_CANCELLED,
                'order:' . $order_id . ':deleted',
                array('source' => 'order_deleted')
            );
        }
        $this->attribution->remove_for_order($order_id);
    }

    public function order_restored(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (is_object($order)) {
            $this->safe_recompute($order);
        }
    }

    private function observe_created($order, string $source): void
    {
        if (! is_object($order) || ! method_exists($order, 'get_id')) {
            return;
        }

        $order_id = (int) $order->get_id();
        $this->record_order_event($order, EventName::ORDER_CREATED, 'order:' . $order_id . ':created', array('source' => $source));
        $this->safe_recompute($order);
    }

    private function observe_paid($order, string $source): void
    {
        if (! is_object($order) || ! method_exists($order, 'get_id')) {
            return;
        }

        $order_id = (int) $order->get_id();
        $this->record_order_event($order, EventName::ORDER_PAID, 'order:' . $order_id . ':paid', array('source' => $source));
        $this->safe_recompute($order);
        $this->resolve_workflow($order, true, 'order_paid');
    }

    private function observe_refund(int $order_id, int $refund_id, string $mutation, string $dedupe): void
    {
        $order = wc_get_order($order_id);
        if (! is_object($order)) {
            return;
        }

        $this->record_order_event(
            $order,
            EventName::ORDER_REFUNDED,
            $dedupe,
            array('refund_id' => $refund_id, 'mutation' => $mutation, 'source' => 'woocommerce_refund')
        );
        $this->safe_recompute($order);
    }

    /**
     * @param array<string, mixed> $properties Event properties.
     */
    private function record_order_event(object $order, string $event_name, string $dedupe, array $properties): void
    {
        $workflow_id = $this->metadata->workflow_id($order);
        if (null === $workflow_id || null === $this->workflows) {
            return;
        }

        $snapshot = $this->revenue->from_order($order);
        $data     = array(
            'product_ids' => $this->product_ids($order),
            'currency'    => $snapshot['currency'],
            'value'       => EventName::ORDER_REFUNDED === $event_name ? $snapshot['refund_value'] : $snapshot['gross_value'],
            'properties'  => array_merge(
                $properties,
                array('order_status' => method_exists($order, 'get_status') ? (string) $order->get_status() : '')
            ),
        );

        try {
            $this->events->record($workflow_id, $event_name, $data, $dedupe);
        } catch (Throwable $throwable) {
            $this->log('event_record_failed', $order, $throwable);
        }
    }

    /**
     * @return list<int>
     */
    private function product_ids(object $order): array
    {
        $ids = array();
        if (method_exists($order, 'get_items')) {
            foreach ((array) $order->get_items('line_item') as $item) {
                if (is_object($item) && method_exists($item, 'get_product_id')) {
                    $ids[] = (int) $item->get_product_id();
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function safe_recompute(object $order): void
    {
        try {
            $this->attribution->recompute($order);
        } catch (Throwable $throwable) {
            $this->log('attribution_recompute_failed', $order, $throwable);
        }
    }

    private function resolve_workflow(object $order, bool $completed, string $reason): void
    {
        $workflow_id = $this->metadata->workflow_id($order);
        if (null === $workflow_id) {
            return;
        }

        try {
            if ($completed) {
                $this->workflows->complete($workflow_id, $reason);
            } else {
                $this->workflows->abandon($workflow_id, $reason);
            }
        } catch (Throwable $throwable) {
            $this->log('workflow_resolution_failed', $order, $throwable);
        }
    }

    private function log(string $code, object $order, Throwable $throwable): void
    {
        if (! function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->error(
            $code,
            array(
                'source'   => 'wmcp-agentsnr',
                'order_id' => method_exists($order, 'get_id') ? (int) $order->get_id() : 0,
                'error'    => get_class($throwable),
            )
        );
    }
}
