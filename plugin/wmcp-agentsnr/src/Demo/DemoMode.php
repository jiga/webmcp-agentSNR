<?php

/**
 * Server-side demo-mode gate.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Demo;

final class DemoMode
{
    public static function enabled(): bool
    {
        return defined('WMCP_AGENTSNR_DEMO_MODE') && true === WMCP_AGENTSNR_DEMO_MODE;
    }
}
