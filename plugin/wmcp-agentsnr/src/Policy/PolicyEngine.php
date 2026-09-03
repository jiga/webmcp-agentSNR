<?php

/**
 * Deterministic tool exposure and execution policy.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Policy;

use WPWebMCP\AgentSNR\Contract\ToolName;

final class PolicyEngine
{
    public function __construct(
        private readonly PolicyStore $policies,
        private readonly SessionPolicyStore $session_policies,
        private readonly KillSwitch $kill_switch
    ) {
    }

    /**
     * @param array<string, mixed> $tool Tool-catalog entry.
     * @return array{allowed: bool, code: string, http_status: int}
     */
    public function evaluate(array $tool, string $surface, string $session_hash_hex): array
    {
        if ($this->kill_switch->active()) {
            return $this->denied('kill_switch_active', 503);
        }

        if (! $this->policies->plugin_enabled()) {
            return $this->denied('webmcp_disabled', 503);
        }

        $tool_name = isset($tool['name']) ? (string) $tool['name'] : '';
        $allowlist = array_merge(ToolName::storefront(), ToolName::agentsnr());
        if (! in_array($tool_name, $allowlist, true)) {
            return $this->denied('tool_not_allowlisted', 404);
        }

        if (! isset($tool['surface']) || $surface !== $tool['surface']) {
            return $this->denied('surface_not_allowed', 403);
        }

        if (! $this->policies->enabled($tool_name)) {
            return $this->denied('tool_disabled', 403);
        }

        if ($this->session_policies->disabled($session_hash_hex, $tool_name)) {
            return $this->denied('tool_disabled', 403);
        }

        return array(
            'allowed'     => true,
            'code'        => 'allowed',
            'http_status' => 200,
        );
    }

    /**
     * @return array{allowed: false, code: string, http_status: int}
     */
    private function denied(string $code, int $http_status): array
    {
        return array(
            'allowed'     => false,
            'code'        => $code,
            'http_status' => $http_status,
        );
    }
}
