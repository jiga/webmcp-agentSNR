<?php
/**
 * Plugin deactivation lifecycle.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Activation;

final class Deactivator
{
    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled('wmcp_agentops_cleanup');
        if (false !== $timestamp) {
            wp_unschedule_event($timestamp, 'wmcp_agentops_cleanup');
        }

        flush_rewrite_rules(false);
    }
}

