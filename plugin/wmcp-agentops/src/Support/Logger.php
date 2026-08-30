<?php

/**
 * Redacted plugin logging through WooCommerce or an integration hook.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Support;

final class Logger
{
    /**
     * @param array<string, scalar|null> $context Redacted operational context.
     */
    public static function error(string $code, array $context = array()): void
    {
        $context['source'] = 'wmcp-agentops';
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error(sanitize_key($code), $context);

            return;
        }

        do_action('wmcp_agentops_log', 'error', sanitize_key($code), $context);
    }
}
