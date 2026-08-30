<?php

/**
 * Same-origin protection for public REST writes.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

use WP_REST_Request;

final class OriginValidator
{
    public function validate(WP_REST_Request $request): bool
    {
        $origin = trim((string) $request->get_header('origin'));
        if ('' !== $origin) {
            return hash_equals($this->site_origin(), rtrim($origin, '/'));
        }

        $referer = trim((string) $request->get_header('referer'));
        if ('' === $referer) {
            return false;
        }

        return hash_equals($this->site_origin(), $this->origin_from_url($referer));
    }

    public function same_origin_url(string $url): bool
    {
        return '' !== $url
            && strlen($url) <= 2048
            && hash_equals($this->site_origin(), $this->origin_from_url($url));
    }

    private function site_origin(): string
    {
        return $this->origin_from_url(home_url('/'));
    }

    private function origin_from_url(string $url): string
    {
        $parts = wp_parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $origin = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
        if (isset($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }

        return $origin;
    }
}
