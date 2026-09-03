<?php

/**
 * Plugin composition root.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR;

use Throwable;
use WPWebMCP\AgentSNR\Abilities\AbilityRegistry;
use WPWebMCP\AgentSNR\Abilities\CallbackRouter;
use WPWebMCP\AgentSNR\Abilities\CoreAbilities;
use WPWebMCP\AgentSNR\Abilities\ToolCatalog;
use WPWebMCP\AgentSNR\Activation\DatabaseMigrator;
use WPWebMCP\AgentSNR\Admin\AdminMenu;
use WPWebMCP\AgentSNR\Admin\DashboardController;
use WPWebMCP\AgentSNR\Admin\SettingsController;
use WPWebMCP\AgentSNR\Analytics\CapabilityGapService;
use WPWebMCP\AgentSNR\Analytics\EventRecorder;
use WPWebMCP\AgentSNR\Analytics\EventSchema;
use WPWebMCP\AgentSNR\Analytics\FunnelService;
use WPWebMCP\AgentSNR\Analytics\FeedbackMetricResolver;
use WPWebMCP\AgentSNR\Analytics\OpportunityDetector;
use WPWebMCP\AgentSNR\Analytics\QueryService;
use WPWebMCP\AgentSNR\Analytics\SignalService;
use WPWebMCP\AgentSNR\Analytics\WorkflowService;
use WPWebMCP\AgentSNR\Demo\DemoCheckout;
use WPWebMCP\AgentSNR\Demo\DemoCleanup;
use WPWebMCP\AgentSNR\Demo\DemoPages;
use WPWebMCP\AgentSNR\Demo\DemoReset;
use WPWebMCP\AgentSNR\Demo\DemoSession;
use WPWebMCP\AgentSNR\Guidance\AgentGuide;
use WPWebMCP\AgentSNR\Policy\KillSwitch;
use WPWebMCP\AgentSNR\Policy\PolicyEngine;
use WPWebMCP\AgentSNR\Policy\PolicyStore;
use WPWebMCP\AgentSNR\Policy\RateLimiter;
use WPWebMCP\AgentSNR\Policy\SessionPolicyStore;
use WPWebMCP\AgentSNR\Privacy\ActorHasher;
use WPWebMCP\AgentSNR\Privacy\Redactor;
use WPWebMCP\AgentSNR\WebMCP\CsrfToken;
use WPWebMCP\AgentSNR\WebMCP\DiagnosticsController;
use WPWebMCP\AgentSNR\WebMCP\DiagnosticsService;
use WPWebMCP\AgentSNR\WebMCP\ExecutionController;
use WPWebMCP\AgentSNR\WebMCP\IdempotencyStore;
use WPWebMCP\AgentSNR\WebMCP\ManifestController;
use WPWebMCP\AgentSNR\WebMCP\ManifestService;
use WPWebMCP\AgentSNR\WebMCP\OriginValidator;
use WPWebMCP\AgentSNR\WebMCP\RestResponseFactory;
use WPWebMCP\AgentSNR\WebMCP\RestRoutes;
use WPWebMCP\AgentSNR\WebMCP\SecurityHeaders;
use WPWebMCP\AgentSNR\WebMCP\SessionController;
use WPWebMCP\AgentSNR\WooCommerce\CartSession;
use WPWebMCP\AgentSNR\WooCommerce\WooIntegration;

final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;
    private ?string $migration_error = null;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        try {
            DatabaseMigrator::migrate();
        } catch (Throwable $exception) {
            $this->migration_error = get_class($exception);
            add_action('admin_notices', array($this, 'render_migration_notice'));
            return;
        }
        $this->compose();
        add_action('admin_notices', array($this, 'render_dependency_notice'));
    }

    private function compose(): void
    {
        global $wpdb;

        $schema      = new EventSchema(new Redactor());
        $events      = new EventRecorder($wpdb, $schema);
        $workflows   = new WorkflowService($wpdb, $events, $schema);
        $catalog     = new ToolCatalog();
        $callbacks   = new CallbackRouter();
        $policies    = new PolicyStore();
        $overrides   = new SessionPolicyStore();
        $kill_switch = new KillSwitch();
        $policy      = new PolicyEngine($policies, $overrides, $kill_switch);
        $rate_limits = new RateLimiter();
        $sessions    = new DemoSession();
        $origins     = new OriginValidator();
        $csrf        = new CsrfToken();
        $responses   = new RestResponseFactory();
        $idempotency = new IdempotencyStore();
        $diagnostics = new DiagnosticsService($catalog, $policies, $kill_switch);
        $guide       = new AgentGuide();
        $opportunities = new OpportunityDetector();
        $signals     = new SignalService(
            $wpdb,
            $schema,
            $events,
            new FeedbackMetricResolver($wpdb, $schema)
        );
        $queries     = new QueryService(
            $wpdb,
            $schema,
            static fn (string $tool_name, string $session_hash): bool => ! $kill_switch->active()
                && $policies->plugin_enabled()
                && $policies->enabled($tool_name)
                && ! $overrides->disabled($session_hash, $tool_name),
            $signals
        );
        $funnel      = new FunnelService($wpdb, $schema);
        $gaps        = new CapabilityGapService($wpdb, $schema, new Redactor(), $events);
        $manifests   = new ManifestService($catalog, $policy, $overrides, $workflows, $csrf, new CartSession());

        (new CoreAbilities(
            $queries,
            $funnel,
            $gaps,
            $signals,
            $guide,
            $diagnostics,
            $policies,
            $overrides,
            $manifests,
            $events
        ))->register($callbacks);

        (new WooIntegration($callbacks, $workflows, $events, $wpdb, $signals, $opportunities))->hooks();
        (new AbilityRegistry($catalog, $callbacks))->hooks();

        $session_controller  = new SessionController(
            $sessions,
            $origins,
            $manifests,
            $responses,
            $rate_limits,
            new ActorHasher()
        );
        $manifest_controller = new ManifestController($sessions, $origins, $manifests, $responses);
        $execution           = new ExecutionController(
            $catalog,
            $sessions,
            $origins,
            $csrf,
            $manifests,
            $policy,
            $rate_limits,
            $workflows,
            $events,
            $idempotency,
            $responses,
            $guide
        );
        $reset               = new DemoReset(
            $sessions,
            $origins,
            $csrf,
            $overrides,
            $rate_limits,
            $manifests,
            $responses
        );

        (new RestRoutes(
            $session_controller,
            $manifest_controller,
            $execution,
            new DiagnosticsController($diagnostics, $responses),
            $reset
        ))->hooks();

        (new SecurityHeaders())->hooks();
        (new DemoPages())->hooks();
        (new DemoCheckout())->hooks();
        (new DemoCleanup($workflows, $idempotency, $sessions, $rate_limits))->hooks();

        $settings  = new SettingsController(
            $catalog,
            $policies,
            $kill_switch,
            $workflows,
            $events,
            new ActorHasher()
        );
        $dashboard = new DashboardController($settings, $diagnostics);
        (new AdminMenu($dashboard))->hooks();
    }

    public function render_dependency_notice(): void
    {
        if (function_exists('wp_register_ability')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Agent SNR requires the WordPress Abilities API provided by WordPress 6.9 or newer.', 'wmcp-agentsnr');
        echo '</p></div>';
    }

    public function render_migration_notice(): void
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Agent SNR could not verify its database schema. Tools remain disabled; review the PHP/database logs and reactivate the plugin.', 'wmcp-agentsnr');
        echo '</p></div>';
    }
}
