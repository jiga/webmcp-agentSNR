<?php

/**
 * Generate or verify the static schemas consumed by webmcp-evals.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

use WPWebMCP\AgentSNR\Abilities\ToolCatalog;

$repository_root = dirname(__DIR__);

require_once $repository_root . '/plugin/wmcp-agentsnr/src/Autoloader.php';
WPWebMCP\AgentSNR\Autoloader::register($repository_root . '/plugin/wmcp-agentsnr/src');

$arguments = array_slice($argv, 1);
$check     = in_array('--check', $arguments, true);
$unknown   = array_values(array_diff($arguments, array('--check')));

if (array() !== $unknown) {
    fwrite(STDERR, 'Unknown argument(s): ' . implode(', ', $unknown) . PHP_EOL);
    exit(2);
}

$catalog  = new ToolCatalog();
$surfaces = array('storefront', 'agentsnr');
$changed  = array();

foreach ($surfaces as $surface) {
    $tools = array_map(
        static function (array $definition): array {
            return array(
                'name'         => (string) $definition['name'],
                'description'  => (string) $definition['description'],
                'inputSchema'  => $definition['input_schema'],
                'outputSchema' => $definition['output_schema'],
            );
        },
        $catalog->public_surface($surface)
    );

    $encoded = json_encode(
        array('tools' => $tools),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL;

    $path     = $repository_root . '/evals/schemas/' . $surface . '-tools.json';
    $existing = is_readable($path) ? file_get_contents($path) : false;

    if ($encoded === $existing) {
        continue;
    }

    $changed[] = str_replace($repository_root . '/', '', $path);

    if ($check) {
        continue;
    }

    $directory = dirname($path);
    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed repository path in a CLI exception.
        throw new RuntimeException('Unable to create eval schema directory: ' . $directory);
    }

    $temporary = $path . '.tmp';
    if (false === file_put_contents($temporary, $encoded, LOCK_EX) || ! rename($temporary, $path)) {
        @unlink($temporary);
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed repository path in a CLI exception.
        throw new RuntimeException('Unable to write eval schema: ' . $path);
    }
}

if ($check && array() !== $changed) {
    fwrite(
        STDERR,
        "WebMCP eval schemas are stale. Regenerate with `php bin/generate-webmcp-eval-schemas.php`:" .
        PHP_EOL . ' - ' . implode(PHP_EOL . ' - ', $changed) . PHP_EOL
    );
    exit(1);
}

if ($check) {
    fwrite(STDOUT, "WebMCP eval schemas match the public ToolCatalog.\n");
} else {
    fwrite(
        STDOUT,
        array() === $changed
            ? "WebMCP eval schemas were already current.\n"
            : 'Generated ' . implode(', ', $changed) . PHP_EOL
    );
}
