<?php
/**
 * Tool risk classes supported by the initial release.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Contract;

final class RiskClass
{
    public const READ = 'read';
    public const TELEMETRY_WRITE = 'telemetry_write';
    public const SESSION_WRITE = 'session_write';
    public const ADMIN_WRITE = 'admin_write';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array(
            self::READ,
            self::TELEMETRY_WRITE,
            self::SESSION_WRITE,
            self::ADMIN_WRITE,
        );
    }
}

