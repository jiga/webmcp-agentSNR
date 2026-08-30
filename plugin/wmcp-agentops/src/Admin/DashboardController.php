<?php

/**
 * Authenticated AgentOps admin dashboard renderer.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Admin;

use WPWebMCP\AgentOps\Demo\DemoMode;
use WPWebMCP\AgentOps\WebMCP\DiagnosticsService;

final class DashboardController
{
    private SettingsController $settings;
    private DiagnosticsService $diagnostics;

    public function __construct(
        ?SettingsController $settings = null,
        ?DiagnosticsService $diagnostics = null
    ) {
        $this->settings    = $settings ?? new SettingsController();
        $this->diagnostics = $diagnostics ?? new DiagnosticsService();
    }

    public function render(): void
    {
        if (! current_user_can('view_wmcp_analytics')) {
            wp_die(esc_html__('You do not have permission to view AgentOps analytics.', 'wmcp-agentops'), '', array('response' => 403));
        }

        $settings         = $this->settings->snapshot();
        // Read-only display flag set only after the nonce-protected settings redirect.
        $settings_saved = isset($_GET['wmcp-settings-saved']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            && '1' === sanitize_text_field(wp_unslash($_GET['wmcp-settings-saved'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $view             = array(
            'site_name'      => (string) get_bloginfo('name'),
            'landing_url'    => home_url('/webmcp-field-lab/'),
            'storefront_url' => home_url('/storefront-demo/'),
            'agentops_url'   => home_url('/agentops-demo/'),
            'health_url'     => home_url('/webmcp-health/'),
            'demo_mode'      => DemoMode::enabled(),
            'is_admin'       => true,
            'settings'       => $settings,
            'tools'          => $settings['tools'],
            'diagnostics'    => $this->diagnostics->run(),
            'can_manage_policies' => current_user_can('manage_wmcp_policies'),
            'settings_saved' => $settings_saved,
        );
        $template         = (defined('WMCP_AGENTOPS_PATH') ? WMCP_AGENTOPS_PATH : dirname(__DIR__, 2) . '/') . 'templates/agentops-demo.php';

        if (! is_readable($template)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('The AgentOps dashboard template is unavailable.', 'wmcp-agentops') . '</p></div>';
            return;
        }

        include $template;
    }

    public function settings(): SettingsController
    {
        return $this->settings;
    }
}
