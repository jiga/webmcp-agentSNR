<?php

/**
 * Strict JSON encoding helpers.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Support;

use JsonException;

final class Json
{
    /**
     * @param mixed $value Value to encode.
     * @throws JsonException When the value cannot be encoded.
     */
    public static function encode($value): string
    {
        return wp_json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException When the JSON is invalid or not an object.
     */
    public static function decode_object(string $json): array
    {
        $trimmed = ltrim($json);
        if ('' === $trimmed || '{' !== $trimmed[0]) {
            throw new JsonException('Expected a JSON object.');
        }

        $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new JsonException('Expected a JSON object.');
        }

        return $decoded;
    }
}
