<?php

/**
 * Minimal dependency-free PSR-4 autoloader for release packages.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR;

final class Autoloader
{
    private const PREFIX = __NAMESPACE__ . '\\';

    public static function register(string $source_directory): void
    {
        $base = rtrim($source_directory, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(
            static function (string $class) use ($base): void {
                if (0 !== strncmp($class, self::PREFIX, strlen(self::PREFIX))) {
                    return;
                }

                $relative = substr($class, strlen(self::PREFIX));
                $file     = $base . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

                if (is_readable($file)) {
                    require_once $file;
                }
            }
        );
    }
}
