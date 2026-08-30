<?php

/**
 * Dependency-free support and privacy tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace {
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
}

namespace WPWebMCP\AgentOps\Tests\Unit\Support {
    use JsonException;
    use PHPUnit\Framework\TestCase;
    use WPWebMCP\AgentOps\Privacy\Redactor;
    use WPWebMCP\AgentOps\Support\IdGenerator;
    use WPWebMCP\AgentOps\Support\Json;

    final class SupportTest extends TestCase
    {
        public function test_ulid_and_event_ids_use_fixed_width_contracts(): void
        {
            $first = IdGenerator::ulid();
            usleep(2000);
            $second = IdGenerator::ulid();

            self::assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $first);
            self::assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $second);
            self::assertLessThan($second, $first);
            self::assertMatchesRegularExpression('/\Aevt_[0-9A-HJKMNP-TV-Z]{26}\z/', IdGenerator::event());
        }

        public function test_uuid_is_version_four(): void
        {
            self::assertMatchesRegularExpression(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
                IdGenerator::uuid()
            );
        }

        public function test_json_requires_an_object_on_decode(): void
        {
            self::assertSame(array('ok' => true), Json::decode_object('{"ok":true}'));

            $this->expectException(JsonException::class);
            Json::decode_object('[1,2,3]');
        }

        public function test_redactor_keeps_only_allowlisted_non_sensitive_properties(): void
        {
            $result = (new Redactor())->properties(
                array(
                    'quantity' => 2,
                    'email'    => 'judge@example.invalid',
                    'nested'   => array('product_id' => 123, 'authorization' => 'secret'),
                    'ignored'  => 'not allowlisted',
                ),
                array('quantity', 'email', 'nested')
            );

            self::assertSame(
                array('quantity' => 2, 'nested' => array('product_id' => 123)),
                $result
            );
        }
    }
}
