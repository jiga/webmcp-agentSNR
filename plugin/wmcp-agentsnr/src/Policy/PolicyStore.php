<?php

/**
 * Persistent site-level tool policy.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Policy;

use InvalidArgumentException;
use WPWebMCP\AgentSNR\Contract\ToolName;

final class PolicyStore
{
    private const OPTION = 'wmcp_agentsnr_tool_policies';

    public function plugin_enabled(): bool
    {
        return (bool) get_option('wmcp_agentsnr_enabled', false);
    }

    public function enabled(string $tool_name): bool
    {
        if (! in_array($tool_name, array_merge(ToolName::storefront(), ToolName::agentsnr()), true)) {
            return false;
        }

        $policies = get_option(self::OPTION, array());
        if (! is_array($policies) || ! array_key_exists($tool_name, $policies)) {
            return true;
        }

        return true === $policies[$tool_name];
    }

    public function set(string $tool_name, bool $enabled): void
    {
        if (! in_array($tool_name, array_merge(ToolName::storefront(), ToolName::agentsnr()), true)) {
            throw new InvalidArgumentException('Unknown tool policy.');
        }

        $policies = get_option(self::OPTION, array());
        if (! is_array($policies)) {
            $policies = array();
        }

        $policies[$tool_name] = $enabled;
        update_option(self::OPTION, $policies, false);
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        $result = array();
        foreach (array_merge(ToolName::storefront(), ToolName::agentsnr()) as $tool_name) {
            $result[$tool_name] = $this->enabled($tool_name);
        }

        return $result;
    }
}
