<?php

/**
 * Plugin deactivation lifecycle.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Activation;

final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('wmcp_agentsnr_cleanup');
        wp_clear_scheduled_hook('wmcp_agentsnr_cleanup_session');

        flush_rewrite_rules(false);
    }
}
