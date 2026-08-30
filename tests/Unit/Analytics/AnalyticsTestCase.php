<?php

/**
 * Analytics test bootstrap and WordPress-function shims.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace {
    if (! defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    if (! function_exists('wp_json_encode')) {
        /** @param mixed $value Value to encode. */
        function wp_json_encode($value, int $flags = 0): string
        {
            return json_encode($value, $flags | JSON_THROW_ON_ERROR);
        }
    }

    if (! function_exists('sanitize_text_field')) {
        function sanitize_text_field(string $value): string
        {
            return trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', strip_tags($value)));
        }
    }

    if (! function_exists('sanitize_key')) {
        function sanitize_key(string $value): string
        {
            return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
        }
    }

    if (! function_exists('mb_substr')) {
        function mb_substr(string $value, int $offset, ?int $length = null): string
        {
            return substr($value, $offset, $length);
        }
    }

    if (! function_exists('mb_strlen')) {
        function mb_strlen(string $value): int
        {
            return strlen($value);
        }
    }

    if (! function_exists('update_option')) {
        /** @param mixed $value Option value. */
        function update_option(string $name, $value, bool $autoload = false): bool
        {
            unset($name, $value, $autoload);

            return true;
        }
    }
}

namespace WPWebMCP\AgentOps\Tests\Unit\Analytics {
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/AnalyticsDatabaseDouble.php';
    require_once __DIR__ . '/ScriptedAnalyticsDatabase.php';

    abstract class AnalyticsTestCase extends TestCase
    {
        /**
         * @return array<string, mixed>
         */
        protected function workflow_row(
            string $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            string $session_hash = ''
        ): array {
            if ('' === $session_hash) {
                $session_hash = str_repeat('a', 64);
            }

            return array(
                'id'                => $id,
                'site_id'           => '01ARZ3NDEKTSV4RRFFQ69G5FAA',
                'demo_session_hash' => $session_hash,
                'protocol'          => 'webmcp',
                'surface'           => 'storefront',
                'status'            => 'active',
                'wp_user_id'        => null,
                'actor_hash'        => null,
                'wc_session_hash'   => null,
                'client_name'       => null,
                'client_version'    => null,
                'intent_source'     => 'unknown',
                'intent_text'       => null,
                'consent_state'     => 'demo',
                'started_at'        => '2026-08-29 20:00:00',
                'ended_at'          => null,
                'last_event_at'     => '2026-08-29 20:00:00',
                'tool_count'        => 0,
                'created_at'        => '2026-08-29 20:00:00',
                'updated_at'        => '2026-08-29 20:00:00',
            );
        }
    }
}
