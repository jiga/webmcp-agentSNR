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
        wp_clear_scheduled_hook('wmcp_agentops_cleanup');
        wp_clear_scheduled_hook('wmcp_agentops_cleanup_session');

        flush_rewrite_rules(false);
    }
}
