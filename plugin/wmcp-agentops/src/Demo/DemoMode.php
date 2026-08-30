<?php

/**
 * Server-side demo-mode gate.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Demo;

final class DemoMode
{
    public static function enabled(): bool
    {
        return defined('WMCP_AGENTOPS_DEMO_MODE') && true === WMCP_AGENTOPS_DEMO_MODE;
    }
}
