<?php

/**
 * Deterministic no-email checkout defaults for explicit demo environments.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Demo;

final class DemoCheckout
{
    public function hooks(): void
    {
        if (! DemoMode::enabled()) {
            return;
        }

        add_filter('woocommerce_checkout_get_value', array($this, 'default_value'), 10, 2);

        foreach (array('new_order', 'cancelled_order', 'failed_order', 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'customer_invoice', 'customer_note', 'customer_reset_password', 'customer_new_account') as $email_id) {
            add_filter('woocommerce_email_enabled_' . $email_id, '__return_false');
        }
    }

    /**
     * @param mixed  $value Existing checkout value.
     * @param string $key Checkout field key.
     * @return mixed
     */
    public function default_value($value, string $key)
    {
        if (! empty($value)) {
            return $value;
        }

        $defaults = array(
            'billing_first_name' => 'Demo',
            'billing_last_name'  => 'Judge',
            'billing_company'    => 'WebMCP Challenge',
            'billing_country'    => 'US',
            'billing_address_1'  => '1 Demo Trail',
            'billing_city'       => 'Portland',
            'billing_state'      => 'OR',
            'billing_postcode'   => '97205',
            'billing_phone'      => '5035550100',
            'billing_email'      => 'judge@example.invalid',
        );

        return $defaults[$key] ?? $value;
    }
}
