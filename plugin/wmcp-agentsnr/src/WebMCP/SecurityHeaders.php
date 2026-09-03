<?php

/**
 * Browser security headers required by the WebMCP execution context.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WebMCP;

use WPWebMCP\AgentSNR\Demo\DemoMode;

final class SecurityHeaders
{
    public function hooks(): void
    {
        add_filter('wp_headers', array($this, 'filter'));
    }

    /**
     * @param array<string, string> $headers Existing response headers.
     * @return array<string, string>
     */
    public function filter(array $headers): array
    {
        $headers['Origin-Agent-Cluster']   = '?1';
        $headers['Permissions-Policy']     = $this->permissions_policy($headers['Permissions-Policy'] ?? '');
        $headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
        $headers['X-Content-Type-Options'] = 'nosniff';
        if (DemoMode::enabled()) {
            $headers['Content-Security-Policy'] = "default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self' data:; form-action 'self'; frame-src 'none'; img-src 'self' data:; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'";
        }

        return $headers;
    }

    private function permissions_policy(string $current): string
    {
        if (preg_match('/(?:^|,)\s*tools\s*=/', $current)) {
            return $current;
        }

        return '' === trim($current) ? 'tools=(self)' : rtrim($current) . ', tools=(self)';
    }
}
