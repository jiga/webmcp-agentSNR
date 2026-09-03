<?php

/**
 * Conditional data cleanup for plugin uninstall.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$administrator = get_role('administrator');
if (null !== $administrator) {
    foreach (
        array(
            'manage_wmcp_tools',
            'manage_wmcp_policies',
            'view_wmcp_analytics',
            'export_wmcp_analytics',
            'view_wmcp_security_log',
            'manage_wmcp_demo',
        ) as $capability
    ) {
        $administrator->remove_cap($capability);
    }
}

if (get_option('wmcp_agentsnr_delete_data_on_uninstall', false)) {
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'wmcp_order_links',
        $wpdb->prefix . 'wmcp_capability_gaps',
        $wpdb->prefix . 'wmcp_events',
        $wpdb->prefix . 'wmcp_workflows',
    );

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are constructed from the trusted WordPress prefix and a fixed allowlist.
    }

    delete_option('wmcp_agentsnr_db_version');
    delete_option('wmcp_agentsnr_enabled');
    delete_option('wmcp_agentsnr_delete_data_on_uninstall');
    delete_option('wmcp_agentsnr_site_id');
    delete_option('wmcp_agentsnr_tool_policies');
    delete_option('wmcp_agentsnr_kill_switch');
    delete_option('wmcp_agentsnr_store_policies');
    delete_option('wmcp_agentsnr_pages');
    delete_option('wmcp_agentsnr_playground_url');
    delete_option('wmcp_agentsnr_repository_url');
    delete_option('wmcp_agentsnr_release_url');

    foreach (array('wmcp_demo_policy_', 'wmcp_demo_session_state_', 'wmcp_idem_', 'wmcp_rl_') as $family) {
        $like = '%' . $wpdb->esc_like($family) . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
    }
}
