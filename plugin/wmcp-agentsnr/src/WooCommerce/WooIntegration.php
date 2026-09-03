<?php

/**
 * Optional WooCommerce integration composition and hooks.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WPWebMCP\AgentSNR\Abilities\CallbackRouter;
use WPWebMCP\AgentSNR\Analytics\EventRecorder;
use WPWebMCP\AgentSNR\Analytics\OpportunityDetector;
use WPWebMCP\AgentSNR\Analytics\SignalService;
use WPWebMCP\AgentSNR\Analytics\WorkflowService;
use WPWebMCP\AgentSNR\Demo\DemoMode;
use WPWebMCP\AgentSNR\Privacy\ActorHasher;

final class WooIntegration
{
    private bool $hooked = false;

    public function __construct(
        private readonly CallbackRouter $callbacks,
        private readonly WorkflowService $workflows,
        private readonly EventRecorder $events,
        private readonly ?object $database = null,
        private readonly ?SignalService $signals = null,
        private readonly ?OpportunityDetector $opportunities = null
    ) {
    }

    public static function available(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_products') && function_exists('WC');
    }

    public static function declare_hpos_compatibility(): void
    {
        if (class_exists(FeaturesUtil::class) && defined('WMCP_AGENTSNR_FILE')) {
            FeaturesUtil::declare_compatibility('custom_order_tables', WMCP_AGENTSNR_FILE, true);
        }
    }

    public function hooks(): void
    {
        if ($this->hooked) {
            return;
        }
        $this->hooked = true;

        add_action('admin_notices', array($this, 'dependency_notice'));
        if (! self::available()) {
            return;
        }

        $cart_session = new CartSession();
        $correlator   = new SessionCorrelator($cart_session, $this->workflows, new ActorHasher());
        $normalizer   = new ProductNormalizer();
        $catalog      = new ProductCatalog($normalizer, new ProductSearchMatcher());
        $cart         = new CartService($cart_session, $catalog, $correlator);
        $metadata     = new OrderMetadata($correlator);
        $revenue      = new RevenueSnapshot();
        $attribution  = new AttributionEngine(new AttributionRules(), $revenue, $metadata, $this->database);
        $observer     = new CommerceObserver($this->events, $metadata, $attribution, $revenue, $this->workflows);
        $abilities    = new CommerceAbilities(
            $catalog,
            new StorePolicyService(),
            $cart,
            new CommerceTelemetry($correlator, $this->events),
            $this->signals,
            $this->opportunities
        );

        $abilities->register($this->callbacks);

        add_action('woocommerce_checkout_create_order', array($metadata, 'classic_order'), 10, 2);
        add_action('woocommerce_store_api_checkout_update_order_meta', array($metadata, 'store_api_order'), 10, 1);
        add_action('woocommerce_checkout_create_order_line_item', array($metadata, 'line_item'), 10, 4);
        $observer->hooks();

        if (DemoMode::enabled()) {
            add_filter('woocommerce_payment_gateways', array($this, 'register_demo_gateway'));
            add_filter('woocommerce_available_payment_gateways', array($this, 'only_demo_gateway'), 1000);
        }
    }

    /**
     * @param array<int|string, class-string|\WC_Payment_Gateway> $gateways Gateway classes or objects.
     * @return array<int|string, class-string|\WC_Payment_Gateway>
     */
    public function register_demo_gateway(array $gateways): array
    {
        if (! DemoMode::enabled() || ! class_exists('WC_Payment_Gateway')) {
            return $gateways;
        }

        foreach ($gateways as $gateway) {
            if (is_a($gateway, DemoPaymentGateway::class, true)) {
                return $gateways;
            }
        }

        $gateways[] = DemoPaymentGateway::class;

        return $gateways;
    }

    /**
     * In explicit demo mode, never allow a real payment processor to appear.
     *
     * @param array<string, object> $gateways Available gateway objects.
     * @return array<string, object>
     */
    public function only_demo_gateway(array $gateways): array
    {
        if (! DemoMode::enabled()) {
            return $gateways;
        }

        return isset($gateways['wmcp_agentsnr_demo'])
            ? array('wmcp_agentsnr_demo' => $gateways['wmcp_agentsnr_demo'])
            : array();
    }

    public function dependency_notice(): void
    {
        if (self::available() || ! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('WooCommerce is not active. Agent SNR remains available, but product, cart, checkout, order, and attribution features are disabled.', 'wmcp-agentsnr');
        echo '</p></div>';
    }
}
