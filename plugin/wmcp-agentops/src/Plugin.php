<?php

/**
 * Plugin composition root.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps;

use Throwable;
use WPWebMCP\AgentOps\Abilities\AbilityRegistry;
use WPWebMCP\AgentOps\Abilities\CallbackRouter;
use WPWebMCP\AgentOps\Abilities\CoreAbilities;
use WPWebMCP\AgentOps\Abilities\ToolCatalog;
use WPWebMCP\AgentOps\Activation\DatabaseMigrator;
use WPWebMCP\AgentOps\Admin\AdminMenu;
use WPWebMCP\AgentOps\Admin\DashboardController;
use WPWebMCP\AgentOps\Admin\SettingsController;
use WPWebMCP\AgentOps\Analytics\CapabilityGapService;
use WPWebMCP\AgentOps\Analytics\EventRecorder;
use WPWebMCP\AgentOps\Analytics\EventSchema;
use WPWebMCP\AgentOps\Analytics\FunnelService;
use WPWebMCP\AgentOps\Analytics\QueryService;
use WPWebMCP\AgentOps\Analytics\WorkflowService;
use WPWebMCP\AgentOps\Demo\DemoCheckout;
use WPWebMCP\AgentOps\Demo\DemoCleanup;
use WPWebMCP\AgentOps\Demo\DemoPages;
use WPWebMCP\AgentOps\Demo\DemoReset;
use WPWebMCP\AgentOps\Demo\DemoSession;
use WPWebMCP\AgentOps\Policy\KillSwitch;
use WPWebMCP\AgentOps\Policy\PolicyEngine;
use WPWebMCP\AgentOps\Policy\PolicyStore;
use WPWebMCP\AgentOps\Policy\RateLimiter;
use WPWebMCP\AgentOps\Policy\SessionPolicyStore;
use WPWebMCP\AgentOps\Privacy\ActorHasher;
use WPWebMCP\AgentOps\Privacy\Redactor;
use WPWebMCP\AgentOps\WebMCP\CsrfToken;
use WPWebMCP\AgentOps\WebMCP\DiagnosticsController;
use WPWebMCP\AgentOps\WebMCP\DiagnosticsService;
use WPWebMCP\AgentOps\WebMCP\ExecutionController;
use WPWebMCP\AgentOps\WebMCP\IdempotencyStore;
use WPWebMCP\AgentOps\WebMCP\ManifestController;
use WPWebMCP\AgentOps\WebMCP\ManifestService;
use WPWebMCP\AgentOps\WebMCP\OriginValidator;
use WPWebMCP\AgentOps\WebMCP\RestResponseFactory;
use WPWebMCP\AgentOps\WebMCP\RestRoutes;
use WPWebMCP\AgentOps\WebMCP\SecurityHeaders;
use WPWebMCP\AgentOps\WebMCP\SessionController;
use WPWebMCP\AgentOps\WooCommerce\WooIntegration;

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
        $queries     = new QueryService(
            $wpdb,
            $schema,
            static fn (string $tool_name, string $session_hash): bool => ! $kill_switch->active()
                && $policies->plugin_enabled()
                && $policies->enabled($tool_name)
                && ! $overrides->disabled($session_hash, $tool_name)
        );
        $funnel      = new FunnelService($wpdb, $schema);
        $gaps        = new CapabilityGapService($wpdb, $schema, new Redactor(), $events);
        $manifests   = new ManifestService($catalog, $policy, $overrides, $workflows, $csrf);

        (new CoreAbilities(
            $queries,
            $funnel,
            $gaps,
            $diagnostics,
            $policies,
            $overrides,
            $manifests,
            $events
        ))->register($callbacks);

        (new WooIntegration($callbacks, $workflows, $events, $wpdb))->hooks();
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
            $responses
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
        echo esc_html__('WP WebMCP AgentOps requires the WordPress Abilities API provided by WordPress 6.9 or newer.', 'wmcp-agentops');
        echo '</p></div>';
    }

    public function render_migration_notice(): void
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('WP WebMCP AgentOps could not verify its database schema. Tools remain disabled; review the PHP/database logs and reactivate the plugin.', 'wmcp-agentops');
        echo '</p></div>';
    }
}
