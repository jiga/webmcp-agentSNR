<?php

/**
 * Signed, short-lived guest CSRF tokens.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WebMCP;

use JsonException;
use WPWebMCP\AgentSNR\Support\Json;

final class CsrfToken
{
    private const LIFETIME = 10 * MINUTE_IN_SECONDS;

    public function expires_at(): int
    {
        return time() + self::LIFETIME;
    }

    public function issue(string $session_hash_hex, string $surface): string
    {
        $payload = array(
            's'   => $session_hash_hex,
            'o'   => $surface,
            'sid' => (string) get_option('wmcp_agentsnr_site_id', ''),
            'iat' => time(),
            'exp' => time() + self::LIFETIME,
            'jti' => bin2hex(random_bytes(12)),
        );

        $encoded   = $this->base64url_encode(Json::encode($payload));
        $signature = hash_hmac('sha256', $encoded, wp_salt('auth'), true);

        return $encoded . '.' . $this->base64url_encode($signature);
    }

    public function verify(string $token, string $session_hash_hex, string $surface): bool
    {
        $parts = explode('.', $token);
        if (2 !== count($parts)) {
            return false;
        }

        [$encoded, $provided_signature] = $parts;
        $signature                      = $this->base64url_decode($provided_signature);
        if (null === $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $encoded, wp_salt('auth'), true);
        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $json = $this->base64url_decode($encoded);
        if (null === $json) {
            return false;
        }

        try {
            $payload = Json::decode_object($json);
        } catch (JsonException $exception) {
            return false;
        }

        return isset($payload['s'], $payload['o'], $payload['sid'], $payload['iat'], $payload['exp'], $payload['jti'])
            && is_string($payload['s'])
            && hash_equals($session_hash_hex, $payload['s'])
            && $surface === $payload['o']
            && (string) get_option('wmcp_agentsnr_site_id', '') === $payload['sid']
            && is_int($payload['iat'])
            && is_int($payload['exp'])
            && $payload['iat'] <= time() + 30
            && $payload['exp'] >= time()
            && $payload['exp'] - $payload['iat'] <= self::LIFETIME;
    }

    private function base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64url_decode(string $value): ?string
    {
        if (1 !== preg_match('/\A[A-Za-z0-9_-]+\z/', $value)) {
            return null;
        }

        $padding = strlen($value) % 4;
        if (0 !== $padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}
