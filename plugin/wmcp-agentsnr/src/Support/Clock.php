<?php

/**
 * UTC time helpers.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Clock
{
    public static function mysql(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function iso8601(): string
    {
        return self::now()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function unix(): int
    {
        return time();
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
