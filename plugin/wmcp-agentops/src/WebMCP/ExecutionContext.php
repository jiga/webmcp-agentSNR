<?php

/**
 * Request-scoped authorization context for native Ability callbacks.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

use LogicException;

final class ExecutionContext
{
    /** @var array<string, mixed>|null */
    private static ?array $current = null;

    /**
     * @param array<string, mixed> $context Verified controller context.
     */
    public static function enter(array $context): void
    {
        if (null !== self::$current) {
            throw new LogicException('A WebMCP execution context is already active.');
        }

        self::$current = $context;
    }

    public static function leave(): void
    {
        self::$current = null;
    }

    public static function allows(string $tool_name): bool
    {
        return null !== self::$current
            && isset(self::$current['tool_name'])
            && is_string(self::$current['tool_name'])
            && hash_equals($tool_name, self::$current['tool_name'])
            && true === (self::$current['authorized'] ?? false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function current(): ?array
    {
        return self::$current;
    }
}
