<?php

/**
 * Site-wide server-authoritative execution kill switch.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Policy;

final class KillSwitch
{
    public function active(): bool
    {
        return (bool) get_option('wmcp_agentops_kill_switch', false);
    }

    public function set(bool $active): void
    {
        update_option('wmcp_agentops_kill_switch', $active, false);
    }
}
