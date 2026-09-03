<?php
/**
 * WP-CLI entry point for the idempotent demo seeder.
 *
 * Run: wp eval-file /workspace/bin/seed-demo.php
 *
 * @package WPWebMCP\AgentSNR
 */

if (! defined('WP_CLI') || ! WP_CLI) {
    throw new RuntimeException('Run this file with WP-CLI.');
}

$result = (new WPWebMCP\AgentSNR\Demo\Seeder())->seed();
WP_CLI::success(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
