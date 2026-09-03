<?php

/**
 * Privacy-preserving fixed-window rate limiter.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Policy;

final class RateLimiter
{
    /**
     * @return array{allowed: bool, limit: int, remaining: int, retry_after: int}
     */
    public function consume(string $actor_hash_hex, string $tool_name, int $limit, int $window_seconds): array
    {
        $now    = time();
        $bucket = intdiv($now, $window_seconds);
        $window_end = ($bucket + 1) * $window_seconds;
        $key    = 'wmcp_rl_' . $window_end . '_' . hash('sha256', $actor_hash_hex . '|' . $tool_name . '|' . $bucket);
        $count  = $this->increment($key);

        if ($count > $limit) {
            return array(
                'allowed'     => false,
                'limit'       => $limit,
                'remaining'   => 0,
                'retry_after' => max(1, $window_end - $now),
            );
        }

        return array(
            'allowed'     => true,
            'limit'       => $limit,
            'remaining'   => max(0, $limit - $count),
            'retry_after' => 0,
        );
    }

    public function available(string $actor_hash_hex, string $tool_name, int $limit, int $window_seconds): bool
    {
        $bucket     = intdiv(time(), $window_seconds);
        $window_end = ($bucket + 1) * $window_seconds;
        $key        = 'wmcp_rl_' . $window_end . '_' . hash('sha256', $actor_hash_hex . '|' . $tool_name . '|' . $bucket);

        return (int) get_option($key, 0) < $limit;
    }

    public function cleanup(): int
    {
        global $wpdb;

        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT 500",
                $wpdb->esc_like('wmcp_rl_') . '%'
            )
        );
        $deleted = 0;
        foreach (is_array($names) ? $names : array() as $name) {
            if (1 !== preg_match('/\Awmcp_rl_([0-9]+)_[a-f0-9]{64}\z/', (string) $name, $matches)) {
                continue;
            }
            if ((int) $matches[1] < time() && delete_option((string) $name)) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function increment(string $key): int
    {
        if (add_option($key, 1, '', false)) {
            return 1;
        }

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options}
                 SET option_value = CAST(option_value AS UNSIGNED) + 1
                 WHERE option_name = %s",
                $key
            )
        );
        wp_cache_delete($key, 'options');

        return (int) get_option($key, 0);
    }
}
