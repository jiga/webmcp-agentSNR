<?php
/**
 * Conditional data cleanup for plugin uninstall.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (! get_option('wmcp_agentops_delete_data_on_uninstall', false)) {
    return;
}

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

delete_option('wmcp_agentops_db_version');
delete_option('wmcp_agentops_enabled');
delete_option('wmcp_agentops_delete_data_on_uninstall');
delete_option('wmcp_agentops_site_id');

