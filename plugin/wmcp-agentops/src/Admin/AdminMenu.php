<?php

/**
 * WordPress admin menu and asset registration.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Admin;

use WPWebMCP\AgentOps\Demo\DemoMode;

final class AdminMenu
{
    private DashboardController $dashboard;

    public function __construct(?DashboardController $dashboard = null)
    {
        $this->dashboard = $dashboard ?? new DashboardController();
    }

    public function hooks(): void
    {
        add_action('admin_menu', array($this, 'register'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        $this->dashboard->settings()->hooks();
    }

    public function register(): void
    {
        add_menu_page(
            __('WebMCP AgentOps', 'wmcp-agentops'),
            __('AgentOps', 'wmcp-agentops'),
            'view_wmcp_analytics',
            'wmcp-agentops',
            array($this->dashboard, 'render'),
            'dashicons-chart-area',
            58
        );
    }

    public function enqueue(string $hook_suffix): void
    {
        if ('toplevel_page_wmcp-agentops' !== $hook_suffix) {
            return;
        }

        $version = defined('WMCP_AGENTOPS_VERSION') ? WMCP_AGENTOPS_VERSION : '0.1.0';
        $base     = defined('WMCP_AGENTOPS_URL') ? WMCP_AGENTOPS_URL : plugin_dir_url(dirname(__DIR__, 2) . '/wmcp-agentops.php');

        wp_enqueue_style('wmcp-field-lab', $base . 'assets/css/field-lab.css', array(), $version);
        wp_enqueue_style('wmcp-dashboard', $base . 'assets/css/dashboard.css', array('wmcp-field-lab'), $version);

        if (! DemoMode::enabled()) {
            return;
        }

        wp_enqueue_script('wmcp-webmcp-runtime', $base . 'assets/js/webmcp-runtime.js', array(), $version, true);
        wp_enqueue_script('wmcp-surface-runtime', $base . 'assets/js/agentops-runtime.js', array('wmcp-webmcp-runtime'), $version, true);
        wp_enqueue_script('wmcp-dashboard', $base . 'assets/js/dashboard.js', array('wmcp-surface-runtime'), $version, true);

        $namespace = untrailingslashit(rest_url('wmcp-agentops/v1'));
        $config    = array(
            'manifestUrl'      => add_query_arg('surface', 'agentops', $namespace . '/manifest'),
            'executionBaseUrl' => untrailingslashit($namespace),
            'healthUrl'        => $namespace . '/health',
            'resetUrl'         => $namespace . '/demo/reset',
            'sessionUrl'       => $namespace . '/session',
            'surface'          => 'agentops',
            'siteId'           => (string) get_option('wmcp_agentops_site_id', ''),
        );
        wp_add_inline_script(
            'wmcp-webmcp-runtime',
            'window.wmcpConfig = ' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );
    }
}
