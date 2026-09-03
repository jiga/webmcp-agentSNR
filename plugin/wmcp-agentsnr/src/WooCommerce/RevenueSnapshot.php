<?php

/**
 * Gross/refund/net values recomputed from a live WooCommerce order object.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

final class RevenueSnapshot
{
    /**
     * @return array{gross_value: float, refund_value: float, net_value: float, currency: string}
     */
    public function from_order(object $order): array
    {
        $status   = method_exists($order, 'get_status') ? (string) $order->get_status() : '';
        $currency = method_exists($order, 'get_currency') ? strtoupper((string) $order->get_currency()) : '';
        if (1 !== preg_match('/\A[A-Z]{3}\z/', $currency) && function_exists('get_woocommerce_currency')) {
            $currency = strtoupper((string) get_woocommerce_currency());
        }

        if (in_array($status, array('cancelled', 'failed'), true)) {
            return array('gross_value' => 0.0, 'refund_value' => 0.0, 'net_value' => 0.0, 'currency' => $currency);
        }

        $date_paid   = method_exists($order, 'get_date_paid') ? $order->get_date_paid() : null;
        $paid_status = in_array($status, array('processing', 'completed', 'refunded'), true);
        $gross       = (null !== $date_paid || $paid_status) && method_exists($order, 'get_total')
            ? max(0.0, (float) $order->get_total())
            : 0.0;
        $refund      = 0.0;
        if (0.0 < $gross && method_exists($order, 'get_total_refunded')) {
            $refund = min($gross, abs((float) $order->get_total_refunded()));
        }

        return array(
            'gross_value'  => $gross,
            'refund_value' => $refund,
            'net_value'    => max(0.0, $gross - $refund),
            'currency'     => $currency,
        );
    }
}
