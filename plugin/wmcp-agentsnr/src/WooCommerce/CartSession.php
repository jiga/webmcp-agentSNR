<?php

/**
 * Current WooCommerce guest/customer session cart loader.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

use WPWebMCP\AgentSNR\WebMCP\ToolException;

final class CartSession
{
    public function cart(): object
    {
        if (! function_exists('WC') || ! function_exists('wc_load_cart')) {
            throw new ToolException('woocommerce_unavailable', 'WooCommerce cart tools are unavailable.', 503, true);
        }

        $woocommerce = WC();
        if (! is_object($woocommerce)) {
            throw new ToolException('woocommerce_unavailable', 'WooCommerce cart tools are unavailable.', 503, true);
        }

        $cart_was_missing = empty($woocommerce->cart) || ! is_object($woocommerce->cart);
        if (empty($woocommerce->session) || $cart_was_missing) {
            wc_load_cart();
        }

        if (empty($woocommerce->session) && method_exists($woocommerce, 'initialize_session')) {
            $woocommerce->initialize_session();
        }
        if (empty($woocommerce->cart) && method_exists($woocommerce, 'initialize_cart')) {
            $woocommerce->initialize_cart();
        }

        if (empty($woocommerce->session) || ! is_object($woocommerce->session) || empty($woocommerce->cart) || ! is_object($woocommerce->cart)) {
            throw new ToolException('cart_unavailable', 'The current WooCommerce cart could not be initialized.', 503, true);
        }

        if ($cart_was_missing && did_action('wp_loaded') && method_exists($woocommerce->cart, 'get_cart_from_session')) {
            $woocommerce->cart->get_cart_from_session();
        }

        return $woocommerce->cart;
    }

    public function session(): object
    {
        $this->cart();

        return WC()->session;
    }

    public function customer_id(): string
    {
        $session = $this->session();

        if (method_exists($session, 'get_customer_unique_id')) {
            $customer_id = (string) $session->get_customer_unique_id();
            if ('' !== $customer_id) {
                return $customer_id;
            }
        }

        return method_exists($session, 'get_customer_id') ? (string) $session->get_customer_id() : '';
    }

    public function persist(): void
    {
        $cart = $this->cart();
        if (method_exists($cart, 'calculate_totals')) {
            $cart->calculate_totals();
        }
        if (method_exists($cart, 'set_session')) {
            $cart->set_session();
        }

        $this->set_session_cookie($this->session());
    }

    /**
     * Establish the stable Woo guest identity without recalculating totals or
     * writing cart contents during a read-only manifest request.
     */
    public function prime_cookie(): void
    {
        $this->set_session_cookie($this->session());
    }

    private function set_session_cookie(object $session): void
    {
        if (method_exists($session, 'set_customer_session_cookie') && ! headers_sent()) {
            $session->set_customer_session_cookie(true);
        }
    }
}
