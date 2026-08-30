<?php

/**
 * Plugin activation lifecycle.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Activation;

use RuntimeException;

final class Activator
{
    private const CAPABILITIES = array(
        'manage_wmcp_tools',
        'manage_wmcp_policies',
        'view_wmcp_analytics',
        'export_wmcp_analytics',
        'view_wmcp_security_log',
        'manage_wmcp_demo',
    );

    public static function activate(): void
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            throw new RuntimeException(esc_html__('WP WebMCP AgentOps requires PHP 8.1 or newer.', 'wmcp-agentops'));
        }

        if (version_compare(get_bloginfo('version'), '6.9', '<')) {
            throw new RuntimeException(esc_html__('WP WebMCP AgentOps requires WordPress 6.9 or newer.', 'wmcp-agentops'));
        }

        DatabaseMigrator::migrate();
        self::grant_capabilities();
        self::schedule_cleanup();

        add_option('wmcp_agentops_enabled', false, '', false);
        add_option('wmcp_agentops_delete_data_on_uninstall', false, '', false);
        add_option('wmcp_agentops_site_id', self::generate_site_id(), '', false);

        flush_rewrite_rules(false);
    }

    private static function grant_capabilities(): void
    {
        $administrator = get_role('administrator');
        if (null === $administrator) {
            return;
        }

        foreach (self::CAPABILITIES as $capability) {
            $administrator->add_cap($capability);
        }
    }

    private static function generate_site_id(): string
    {
        return substr(strtoupper(bin2hex(random_bytes(16))), 0, 26);
    }

    private static function schedule_cleanup(): void
    {
        if (false !== wp_next_scheduled('wmcp_agentops_cleanup')) {
            return;
        }

        $scheduled = wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wmcp_agentops_cleanup', array(), true);
        if (is_wp_error($scheduled)) {
            throw new RuntimeException(
                esc_html(sprintf('Unable to schedule demo cleanup: %s', $scheduled->get_error_code()))
            );
        }
    }
}
