<?php

/**
 * Site-wide server-authoritative execution kill switch.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Policy;

final class KillSwitch
{
    public function active(): bool
    {
        return (bool) get_option('wmcp_agentsnr_kill_switch', false);
    }

    public function set(bool $active): void
    {
        update_option('wmcp_agentsnr_kill_switch', $active, false);
    }
}
