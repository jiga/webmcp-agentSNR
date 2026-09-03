<?php

/**
 * Restrictive per-demo-session policy overrides.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Policy;

use InvalidArgumentException;
use WPWebMCP\AgentSNR\Contract\ToolName;
use WPWebMCP\AgentSNR\Support\Clock;
use WPWebMCP\AgentSNR\Support\IdGenerator;

final class SessionPolicyStore
{
    private const PREFIX = 'wmcp_demo_policy_';

    public function disabled(string $session_hash_hex, string $tool_name): bool
    {
        $state = $this->get($session_hash_hex);

        return isset($state['tools'][$tool_name]) && false === $state['tools'][$tool_name]['enabled'];
    }

    /**
     * Public callers may disable a tool or clear their own override; they can never
     * elevate a persistent site-level denial.
     *
     * @return array<string, mixed>
     */
    public function set(string $session_hash_hex, string $tool_name, bool $enabled, string $reason): array
    {
        if (! in_array($tool_name, ToolName::storefront(), true)) {
            throw new InvalidArgumentException('Only storefront tools may receive demo-session overrides.');
        }

        $state = $this->get($session_hash_hex);
        if ($enabled) {
            unset($state['tools'][$tool_name]);
        } else {
            $state['tools'][$tool_name] = array(
                'enabled'    => false,
                'reason'     => mb_substr(sanitize_text_field($reason), 0, 300),
                'changed_at' => Clock::iso8601(),
            );
        }

        $state['revision']   = IdGenerator::ulid();
        $state['expires_at'] = time() + DAY_IN_SECONDS;
        set_transient($this->key($session_hash_hex), $state, DAY_IN_SECONDS);

        return $state;
    }

    /**
     * @return array{revision: string, expires_at: int, tools: array<string, array<string, mixed>>}
     */
    public function get(string $session_hash_hex): array
    {
        $state = get_transient($this->key($session_hash_hex));
        if (! is_array($state)) {
            return array(
                'revision'   => 'baseline',
                'expires_at' => time() + DAY_IN_SECONDS,
                'tools'      => array(),
            );
        }

        return array(
            'revision'   => isset($state['revision']) ? (string) $state['revision'] : 'baseline',
            'expires_at' => isset($state['expires_at']) ? (int) $state['expires_at'] : time(),
            'tools'      => isset($state['tools']) && is_array($state['tools']) ? $state['tools'] : array(),
        );
    }

    public function clear(string $session_hash_hex): void
    {
        delete_transient($this->key($session_hash_hex));
    }

    private function key(string $session_hash_hex): string
    {
        if (1 !== preg_match('/\A[a-f0-9]{64}\z/', $session_hash_hex)) {
            throw new InvalidArgumentException('Invalid demo-session hash.');
        }

        return self::PREFIX . $session_hash_hex;
    }
}
