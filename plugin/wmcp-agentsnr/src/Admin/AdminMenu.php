<?php

/**
 * WordPress admin menu and asset registration.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Admin;

use WPWebMCP\AgentSNR\Demo\DemoMode;

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
            __('Agent SNR — Agent outcome monitoring for WordPress', 'wmcp-agentsnr'),
            __('Agent SNR', 'wmcp-agentsnr'),
            'view_wmcp_analytics',
            'wmcp-agentsnr',
            array($this->dashboard, 'render'),
            'dashicons-chart-area',
            58
        );
    }

    public function enqueue(string $hook_suffix): void
    {
        if ('toplevel_page_wmcp-agentsnr' !== $hook_suffix) {
            return;
        }

        $version = defined('WMCP_AGENTSNR_VERSION') ? WMCP_AGENTSNR_VERSION : '0.1.0';
        $base     = defined('WMCP_AGENTSNR_URL') ? WMCP_AGENTSNR_URL : plugin_dir_url(dirname(__DIR__, 2) . '/wmcp-agentsnr.php');

        wp_enqueue_style('wmcp-field-lab', $base . 'assets/css/field-lab.css', array(), $version);
        wp_enqueue_style('wmcp-dashboard', $base . 'assets/css/dashboard.css', array('wmcp-field-lab'), $version);

        if (! DemoMode::enabled()) {
            return;
        }

        wp_enqueue_script('wmcp-webmcp-runtime', $base . 'assets/js/webmcp-runtime.js', array(), $version, true);
        wp_enqueue_script('wmcp-surface-runtime', $base . 'assets/js/agentsnr-runtime.js', array('wmcp-webmcp-runtime'), $version, true);
        wp_enqueue_script('wmcp-dashboard', $base . 'assets/js/dashboard.js', array('wmcp-surface-runtime'), $version, true);

        $namespace = untrailingslashit(rest_url('wmcp-agentsnr/v1'));
        $config    = array(
            'manifestUrl'      => add_query_arg('surface', 'agentsnr', $namespace . '/manifest'),
            'executionBaseUrl' => untrailingslashit($namespace),
            'healthUrl'        => $namespace . '/health',
            'resetUrl'         => $namespace . '/demo/reset',
            'sessionUrl'       => $namespace . '/session',
            'surface'          => 'agentsnr',
            'siteId'           => (string) get_option('wmcp_agentsnr_site_id', ''),
        );
        wp_add_inline_script(
            'wmcp-webmcp-runtime',
            'window.wmcpConfig = ' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );
    }
}
