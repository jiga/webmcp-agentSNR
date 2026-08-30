<?php

/**
 * PHPUnit bootstrap for dependency-free core tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

$GLOBALS['wmcp_test_transients'] = array();
$GLOBALS['wmcp_test_options'] = array();
$GLOBALS['wmcp_test_fail_option_update'] = false;
$GLOBALS['wmcp_test_scheduled_events'] = array();

if (! function_exists('wp_schedule_single_event')) {
    /** @param list<mixed> $args Event arguments. @return bool */
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = array(), bool $wp_error = false): bool
    {
        unset($wp_error);
        $GLOBALS['wmcp_test_scheduled_events'][] = array(
            'timestamp' => $timestamp,
            'hook'      => $hook,
            'args'      => $args,
        );

        return true;
    }
}

if (! function_exists('is_wp_error')) {
    /** @param mixed $thing Candidate value. */
    function is_wp_error($thing): bool
    {
        unset($thing);

        return false;
    }
}

if (! function_exists('add_option')) {
    /** @param mixed $value Option value. */
    function add_option(string $name, $value, string $deprecated = '', bool $autoload = false): bool
    {
        unset($deprecated, $autoload);
        if (array_key_exists($name, $GLOBALS['wmcp_test_options'])) {
            return false;
        }
        $GLOBALS['wmcp_test_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('get_option')) {
    /** @param mixed $default Default value. @return mixed */
    function get_option(string $name, $default = false)
    {
        return $GLOBALS['wmcp_test_options'][$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    /** @param mixed $value Option value. */
    function update_option(string $name, $value, bool $autoload = false): bool
    {
        unset($autoload);
        if (true === ($GLOBALS['wmcp_test_fail_option_update'] ?? false)) {
            return false;
        }
        $GLOBALS['wmcp_test_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $name): bool
    {
        $existed = array_key_exists($name, $GLOBALS['wmcp_test_options']);
        unset($GLOBALS['wmcp_test_options'][$name]);

        return $existed;
    }
}

if (! function_exists('wp_cache_delete')) {
    function wp_cache_delete(string $key, string $group = ''): bool
    {
        unset($key, $group);

        return true;
    }
}

if (! function_exists('set_transient')) {
    /** @param mixed $value Transient value. */
    function set_transient(string $key, $value, int $expiration): bool
    {
        $GLOBALS['wmcp_test_transients'][$key] = array(
            'expires_at' => time() + $expiration,
            'value'      => $value,
        );

        return true;
    }
}

if (! function_exists('get_transient')) {
    /** @return mixed */
    function get_transient(string $key)
    {
        $stored = $GLOBALS['wmcp_test_transients'][$key] ?? null;
        if (! is_array($stored) || $stored['expires_at'] < time()) {
            unset($GLOBALS['wmcp_test_transients'][$key]);

            return false;
        }

        return $stored['value'];
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        $existed = isset($GLOBALS['wmcp_test_transients'][$key]);
        unset($GLOBALS['wmcp_test_transients'][$key]);

        return $existed;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', strip_tags($value)));
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value, bool $remove_breaks = false): string
    {
        $value = strip_tags($value);

        return $remove_breaks
            ? trim((string) preg_replace('/[\r\n\t ]+/', ' ', $value))
            : $value;
    }
}

if (! function_exists('wp_unslash')) {
    /** @param mixed $value Input value. @return mixed */
    function wp_unslash($value)
    {
        return $value;
    }
}

if (! function_exists('is_ssl')) {
    function is_ssl(): bool
    {
        return false;
    }
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
}

require_once dirname(__DIR__) . '/plugin/wmcp-agentops/src/Autoloader.php';
WPWebMCP\AgentOps\Autoloader::register(dirname(__DIR__) . '/plugin/wmcp-agentops/src');
