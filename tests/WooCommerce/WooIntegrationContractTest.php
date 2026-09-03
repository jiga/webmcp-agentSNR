<?php

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\WooCommerce;

use PHPUnit\Framework\TestCase;

final class WooIntegrationContractTest extends TestCase
{
    private string $source_directory;

    protected function setUp(): void
    {
        $this->source_directory = dirname(__DIR__, 2) . '/plugin/wmcp-agentsnr/src/WooCommerce';
    }

    public function test_classic_and_store_api_provenance_hooks_are_registered(): void
    {
        $source = file_get_contents($this->source_directory . '/WooIntegration.php');

        self::assertIsString($source);
        self::assertStringContainsString('woocommerce_checkout_create_order', $source);
        self::assertStringContainsString('woocommerce_store_api_checkout_update_order_meta', $source);
        self::assertStringContainsString('woocommerce_checkout_create_order_line_item', $source);
        self::assertStringContainsString('woocommerce_store_api_checkout_order_processed', file_get_contents($this->source_directory . '/CommerceObserver.php'));
    }

    public function test_order_lifecycle_and_refund_hooks_are_registered(): void
    {
        $source = file_get_contents($this->source_directory . '/CommerceObserver.php');

        self::assertStringContainsString('woocommerce_payment_complete', $source);
        self::assertStringContainsString('woocommerce_order_status_changed', $source);
        self::assertStringContainsString('woocommerce_refund_created', $source);
        self::assertStringContainsString('woocommerce_order_refunded', $source);
        self::assertStringContainsString('woocommerce_update_order_refund', $source);
        self::assertStringContainsString('woocommerce_before_delete_order', $source);
        self::assertStringContainsString('woocommerce_before_trash_order', $source);
        self::assertStringContainsString('woocommerce_untrash_order', $source);
    }

    public function test_only_hpos_compatibility_is_declared(): void
    {
        $source = file_get_contents($this->source_directory . '/WooIntegration.php');

        self::assertStringContainsString("declare_compatibility('custom_order_tables'", $source);
        self::assertStringNotContainsString('cart_checkout_blocks', $source);
    }

    public function test_demo_gateway_depends_on_server_side_demo_gate(): void
    {
        $integration = file_get_contents($this->source_directory . '/WooIntegration.php');
        $gateway     = file_get_contents($this->source_directory . '/DemoPaymentGateway.php');

        self::assertStringContainsString('DemoMode::enabled()', $integration);
        self::assertStringContainsString('DemoMode::enabled()', $gateway);
        self::assertStringContainsString('only_demo_gateway', $integration);
        self::assertStringContainsString('has_current_provenance', $gateway);
        self::assertStringContainsString('quota_available', $gateway);
        self::assertStringNotContainsString('$_REQUEST', $gateway);
        self::assertStringNotContainsString('$_GET', $gateway);
        self::assertStringNotContainsString('$_POST', $gateway);
    }

    public function test_woocommerce_orders_are_never_read_from_wordpress_order_tables(): void
    {
        foreach (glob($this->source_directory . '/*.php') ?: array() as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression('/(?:wp_|\$wpdb->)(?:posts|postmeta)/i', $source, basename($file));
        }
    }
}
