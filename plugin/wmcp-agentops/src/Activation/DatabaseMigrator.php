<?php

/**
 * Versioned custom-table migrations.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Activation;

use RuntimeException;
use WPWebMCP\AgentOps\Contract\Versions;

final class DatabaseMigrator
{
    public static function migrate(): void
    {
        if (
            Versions::DATABASE === (string) get_option('wmcp_agentops_db_version', '')
            && self::schema_ready()
        ) {
            return;
        }

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collation = $wpdb->get_charset_collate();
        $prefix    = $wpdb->prefix;

        dbDelta(
            "CREATE TABLE {$prefix}wmcp_workflows (
                id char(26) NOT NULL,
                site_id char(26) NOT NULL,
                demo_session_hash char(64) DEFAULT NULL,
                protocol varchar(16) NOT NULL DEFAULT 'webmcp',
                surface varchar(32) NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'active',
                wp_user_id bigint(20) unsigned DEFAULT NULL,
                actor_hash char(64) DEFAULT NULL,
                wc_session_hash char(64) DEFAULT NULL,
                client_name varchar(64) DEFAULT NULL,
                client_version varchar(32) DEFAULT NULL,
                intent_source varchar(24) NOT NULL DEFAULT 'unknown',
                intent_text text DEFAULT NULL,
                consent_state varchar(24) NOT NULL DEFAULT 'demo',
                started_at datetime NOT NULL,
                ended_at datetime DEFAULT NULL,
                last_event_at datetime NOT NULL,
                tool_count int(10) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY site_started (site_id,started_at),
                KEY demo_started (demo_session_hash,started_at),
                KEY wc_session (wc_session_hash),
                KEY status_last (status,last_event_at)
            ) {$collation};"
        );

        dbDelta(
            "CREATE TABLE {$prefix}wmcp_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_id char(30) NOT NULL,
                workflow_id char(26) NOT NULL,
                event_name varchar(64) NOT NULL,
                request_id char(36) DEFAULT NULL,
                step_number int(10) unsigned NOT NULL,
                tool_name varchar(128) DEFAULT NULL,
                tool_version varchar(32) DEFAULT NULL,
                risk_class varchar(24) DEFAULT NULL,
                outcome varchar(24) DEFAULT NULL,
                duration_ms int(10) unsigned DEFAULT NULL,
                error_code varchar(64) DEFAULT NULL,
                http_status smallint(5) unsigned DEFAULT NULL,
                product_ids_json text DEFAULT NULL,
                currency char(3) DEFAULT NULL,
                value decimal(18,6) DEFAULT NULL,
                properties_json longtext DEFAULT NULL,
                dedupe_key char(64) NOT NULL,
                occurred_at datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY event_id (event_id),
                UNIQUE KEY dedupe_key (dedupe_key),
                KEY workflow_step (workflow_id,step_number),
                KEY request_id (request_id),
                KEY event_time (event_name,occurred_at),
                KEY tool_time (tool_name,occurred_at),
                KEY outcome_time (outcome,occurred_at)
            ) {$collation};"
        );

        dbDelta(
            "CREATE TABLE {$prefix}wmcp_order_links (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                order_id bigint(20) unsigned NOT NULL,
                workflow_id char(26) NOT NULL,
                attribution_class varchar(24) NOT NULL,
                confidence varchar(16) NOT NULL,
                rule_version varchar(16) NOT NULL,
                first_touch_at datetime NOT NULL,
                last_touch_at datetime NOT NULL,
                gross_value decimal(18,6) NOT NULL DEFAULT 0,
                refund_value decimal(18,6) NOT NULL DEFAULT 0,
                net_value decimal(18,6) NOT NULL DEFAULT 0,
                currency char(3) NOT NULL,
                matched_products_json text NOT NULL,
                evidence_event_ids_json text NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY order_rule (order_id,rule_version),
                KEY order_id (order_id),
                KEY workflow_id (workflow_id)
            ) {$collation};"
        );

        dbDelta(
            "CREATE TABLE {$prefix}wmcp_capability_gaps (
                id char(26) NOT NULL,
                workflow_id char(26) NOT NULL,
                demo_session_hash char(64) DEFAULT NULL,
                capability_slug varchar(128) NOT NULL,
                user_goal_redacted varchar(300) NOT NULL,
                related_product_id bigint(20) unsigned DEFAULT NULL,
                context_json text DEFAULT NULL,
                signal_source varchar(24) NOT NULL DEFAULT 'agent_reported',
                signal_category varchar(32) NOT NULL DEFAULT 'capability_gap',
                signal_key char(64) DEFAULT NULL,
                evidence_status varchar(24) NOT NULL DEFAULT 'unlinked',
                outcome varchar(24) DEFAULT NULL,
                step_id varchar(32) DEFAULT NULL,
                reason_code varchar(64) DEFAULT NULL,
                evidence_event_ids_json text DEFAULT NULL,
                ratings_json text DEFAULT NULL,
                requested_metrics_json text DEFAULT NULL,
                measured_context_json text DEFAULT NULL,
                suggested_action varchar(64) DEFAULT NULL,
                dedupe_key char(64) DEFAULT NULL,
                feedback_slot tinyint(3) unsigned DEFAULT NULL,
                status varchar(24) NOT NULL DEFAULT 'open',
                occurred_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY signal_dedupe (dedupe_key),
                UNIQUE KEY workflow_feedback_slot (workflow_id,feedback_slot),
                KEY workflow_id (workflow_id),
                KEY demo_session_time (demo_session_hash,occurred_at),
                KEY capability_time (capability_slug,occurred_at),
                KEY signal_group (demo_session_hash,signal_category,capability_slug,occurred_at)
            ) {$collation};"
        );

        if (! self::schema_ready()) {
            throw new RuntimeException('Agent SNR could not verify its database schema.');
        }

        update_option('wmcp_agentops_db_version', Versions::DATABASE, false);
    }

    public static function schema_ready(): bool
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'wmcp_workflows' => array('id', 'demo_session_hash', 'wc_session_hash', 'status', 'started_at'),
            $wpdb->prefix . 'wmcp_events' => array('id', 'event_id', 'workflow_id', 'request_id', 'dedupe_key', 'event_name'),
            $wpdb->prefix . 'wmcp_order_links' => array('id', 'order_id', 'workflow_id', 'rule_version', 'net_value'),
            $wpdb->prefix . 'wmcp_capability_gaps' => array(
                'id',
                'workflow_id',
                'demo_session_hash',
                'capability_slug',
                'signal_source',
                'signal_category',
                'signal_key',
                'evidence_status',
                'evidence_event_ids_json',
                'measured_context_json',
                'dedupe_key',
                'feedback_slot',
            ),
        );
        $indexes = array(
            $wpdb->prefix . 'wmcp_workflows' => array('PRIMARY', 'demo_started', 'wc_session'),
            $wpdb->prefix . 'wmcp_events' => array('PRIMARY', 'event_id', 'dedupe_key', 'workflow_step', 'request_id'),
            $wpdb->prefix . 'wmcp_order_links' => array('PRIMARY', 'order_rule', 'workflow_id'),
            $wpdb->prefix . 'wmcp_capability_gaps' => array('PRIMARY', 'signal_dedupe', 'workflow_feedback_slot', 'demo_session_time', 'capability_time', 'signal_group'),
        );

        $previous_suppression = $wpdb->suppress_errors(true);
        try {
            foreach ($tables as $table => $required_columns) {
                if (false === $wpdb->query($wpdb->prepare('SELECT * FROM %i LIMIT 0', $table))) {
                    return false;
                }

                $columns = $wpdb->get_col_info('name');
                if (! is_array($columns) || array() !== array_diff($required_columns, $columns)) {
                    return false;
                }
            }

            if (self::uses_sqlite()) {
                return true;
            }

            foreach ($indexes as $table => $required_indexes) {
                $rows = $wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i', $table), ARRAY_A);
                if (! is_array($rows)) {
                    return false;
                }
                $names = array_values(array_unique(array_map(static fn (array $row): string => (string) ($row['Key_name'] ?? ''), $rows)));
                if (array() !== array_diff($required_indexes, $names)) {
                    return false;
                }
            }

            return true;
        } finally {
            $wpdb->suppress_errors($previous_suppression);
        }
    }

    private static function uses_sqlite(): bool
    {
        global $wpdb;

        if (defined('SQLITE_DB_DROPIN_VERSION') || defined('SQLITE_PLUGIN')) {
            return true;
        }

        $server = method_exists($wpdb, 'db_server_info') ? (string) $wpdb->db_server_info() : '';

        return false !== stripos($server, 'sqlite');
    }
}
