<?php

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\WooCommerce;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use WPWebMCP\AgentSNR\WooCommerce\DemoPaymentGateway;
use WPWebMCP\AgentSNR\WooCommerce\WooIntegration;

require_once __DIR__ . '/PaymentGatewayBaseDouble.php';

final class WooIntegrationTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_demo_gateway_registration_preserves_mixed_entries_and_is_idempotent(): void
    {
        define('WMCP_AGENTSNR_DEMO_MODE', true);
        class_alias(PaymentGatewayBaseDouble::class, 'WC_Payment_Gateway');

        $integration        = (new ReflectionClass(WooIntegration::class))->newInstanceWithoutConstructor();
        $third_party_object = new PaymentGatewayBaseDouble();
        $gateways           = array(
            'legacy' => 'WC_Legacy_Gateway',
            'axo'    => $third_party_object,
        );

        $registered = $integration->register_demo_gateway($gateways);

        self::assertSame('WC_Legacy_Gateway', $registered['legacy']);
        self::assertSame($third_party_object, $registered['axo']);
        self::assertSame(DemoPaymentGateway::class, $registered[0]);
        self::assertSame($registered, $integration->register_demo_gateway($registered));

        $demo_object = (new ReflectionClass(DemoPaymentGateway::class))->newInstanceWithoutConstructor();
        $with_object = array('third_party' => $third_party_object, 'demo' => $demo_object);

        self::assertSame($with_object, $integration->register_demo_gateway($with_object));

        $case_variant = array('demo' => strtolower(DemoPaymentGateway::class));

        self::assertSame($case_variant, $integration->register_demo_gateway($case_variant));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_disabled_demo_mode_preserves_mixed_gateway_entries(): void
    {
        $integration = (new ReflectionClass(WooIntegration::class))->newInstanceWithoutConstructor();
        $gateways    = array('legacy' => 'WC_Legacy_Gateway', 'object' => new stdClass());

        self::assertSame($gateways, $integration->register_demo_gateway($gateways));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_missing_woocommerce_gateway_base_preserves_mixed_entries(): void
    {
        define('WMCP_AGENTSNR_DEMO_MODE', true);

        $integration = (new ReflectionClass(WooIntegration::class))->newInstanceWithoutConstructor();
        $gateways    = array('legacy' => 'WC_Legacy_Gateway', 'object' => new stdClass());

        self::assertFalse(class_exists('WC_Payment_Gateway', false));
        self::assertSame($gateways, $integration->register_demo_gateway($gateways));
    }
}
