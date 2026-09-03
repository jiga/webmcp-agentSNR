<?php

/**
 * Classic no-charge demo gateway.
 *
 * Loaded only when WooCommerce is active and WMCP_AGENTSNR_DEMO_MODE is true.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

use WPWebMCP\AgentSNR\Demo\DemoMode;
use WPWebMCP\AgentSNR\Demo\DemoSession;
use WPWebMCP\AgentSNR\Policy\RateLimiter;

final class DemoPaymentGateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id                 = 'wmcp_agentsnr_demo';
        $this->method_title       = __('Demo payment — no charge', 'wmcp-agentsnr');
        $this->method_description = __('Completes a demo order without contacting a payment processor or collecting card details.', 'wmcp-agentsnr');
        $this->title              = __('Demo payment — no charge', 'wmcp-agentsnr');
        $this->description        = __('For this public demo only. No payment is processed and no card details are requested or stored.', 'wmcp-agentsnr');
        $this->has_fields         = false;
        $this->enabled            = 'yes';
        $this->supports           = array('products');
    }

    public function is_available()
    {
        $session = (new DemoSession())->peek();

        return DemoMode::enabled()
            && null !== $session
            && $this->has_current_provenance($session['hash_hex'])
            && $this->quota_available($session['hash_hex'])
            && parent::is_available();
    }

    /**
     * @return array{result: string, redirect: string}
     */
    public function process_payment($order_id)
    {
        if (! DemoMode::enabled()) {
            return array('result' => 'failure', 'redirect' => '');
        }

        $order = wc_get_order((int) $order_id);
        $session = (new DemoSession())->peek();
        if (
            ! is_object($order)
            || null === $session
            || ! $this->has_current_provenance($session['hash_hex'])
            || ! $this->quota_available($session['hash_hex'])
            || ! method_exists($order, 'get_meta')
            || ! hash_equals($session['hash_hex'], (string) $order->get_meta('_wmcp_demo_session_hash', true))
        ) {
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('The no-charge demo checkout requires a current agent-prepared cart and an available demo quota.', 'wmcp-agentsnr'), 'error');
            }
            return array('result' => 'failure', 'redirect' => '');
        }

        if (! $this->consume_quota($session['hash_hex'], $order)) {
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('The no-charge demo order limit has been reached. Start a fresh run later.', 'wmcp-agentsnr'), 'error');
            }

            return array('result' => 'failure', 'redirect' => '');
        }

        $order->update_meta_data('_wmcp_demo_order', 'yes');
        $order->add_order_note(__('Demo payment completed. No money was charged.', 'wmcp-agentsnr'));
        $order->save_meta_data();
        $order->payment_complete();

        if (function_exists('WC') && is_object(WC()->cart)) {
            WC()->cart->empty_cart();
        }

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    private function has_current_provenance(string $session_hash_hex): bool
    {
        if (! function_exists('WC') || ! is_object(WC()->session) || ! method_exists(WC()->session, 'get')) {
            return false;
        }

        $stored = WC()->session->get(SessionCorrelator::SESSION_KEY, array());
        if (! is_array($stored) || ! isset($stored['current_workflow_id'], $stored['workflows'])) {
            return false;
        }

        $workflow = is_array($stored['workflows'])
            ? ($stored['workflows'][(string) $stored['current_workflow_id']] ?? null)
            : null;

        return is_array($workflow)
            && isset($workflow['demo_session_hash'])
            && is_string($workflow['demo_session_hash'])
            && hash_equals($session_hash_hex, $workflow['demo_session_hash'])
            && true === ($workflow['checkout_handoff'] ?? false);
    }

    private function quota_available(string $session_hash_hex): bool
    {
        $limiter = new RateLimiter();

        return $limiter->available($session_hash_hex, 'demo_order', 5, DAY_IN_SECONDS)
            && $limiter->available('global', 'demo_order', 100, HOUR_IN_SECONDS);
    }

    private function consume_quota(string $session_hash_hex, object $order): bool
    {
        if (method_exists($order, 'get_meta') && 'yes' === (string) $order->get_meta('_wmcp_demo_quota_consumed', true)) {
            return true;
        }

        $limiter = new RateLimiter();
        $session = $limiter->consume($session_hash_hex, 'demo_order', 5, DAY_IN_SECONDS);
        $global  = $limiter->consume('global', 'demo_order', 100, HOUR_IN_SECONDS);
        if (! $session['allowed'] || ! $global['allowed']) {
            return false;
        }

        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_wmcp_demo_quota_consumed', 'yes');
        }
        if (method_exists($order, 'save_meta_data')) {
            $order->save_meta_data();
        }

        return true;
    }
}
