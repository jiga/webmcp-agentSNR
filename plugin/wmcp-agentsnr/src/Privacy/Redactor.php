<?php

/**
 * Allowlist-first event-property redaction.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Privacy;

final class Redactor
{
    private const PROHIBITED_KEYS = array(
        'address',
        'authorization',
        'card',
        'conversation',
        'cookie',
        'email',
        'name',
        'nonce',
        'password',
        'payment',
        'phone',
        'prompt',
        'session_token',
    );

    /**
     * @param array<string, mixed> $properties Raw structured properties.
     * @param list<string>         $allowlist  Allowed top-level keys.
     * @return array<string, mixed>
     */
    public function properties(array $properties, array $allowlist): array
    {
        $redacted = array();

        foreach ($allowlist as $key) {
            if (! array_key_exists($key, $properties) || $this->prohibited($key)) {
                continue;
            }

            $redacted[$key] = $this->value($properties[$key], 0);
        }

        return $redacted;
    }

    private function prohibited(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::PROHIBITED_KEYS as $prohibited) {
            if (str_contains($normalized, $prohibited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value Raw value.
     * @return mixed
     */
    private function value($value, int $depth)
    {
        if ($depth >= 3) {
            return '[redacted-depth]';
        }

        if (is_string($value)) {
            return mb_substr(sanitize_text_field($value), 0, 300);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || null === $value) {
            return $value;
        }

        if (! is_array($value)) {
            return '[redacted-type]';
        }

        $safe = array();
        foreach (array_slice($value, 0, 20, true) as $key => $nested) {
            $safe_key = is_string($key) ? sanitize_key($key) : $key;
            if (is_string($safe_key) && $this->prohibited($safe_key)) {
                continue;
            }
            $safe[$safe_key] = $this->value($nested, $depth + 1);
        }

        return $safe;
    }
}
