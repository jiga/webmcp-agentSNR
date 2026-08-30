<?php

/**
 * Session-bound response cache and replay lock for mutation request IDs.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

final class IdempotencyStore
{
    private const PREFIX = 'wmcp_idem_';
    private const LIFETIME = DAY_IN_SECONDS;
    private const GUARD_LIFETIME = 2 * DAY_IN_SECONDS;

    private const STATE_PENDING = 'pending';
    private const STATE_EXECUTING = 'executing';
    private const STATE_EXECUTED = 'executed';
    private const STATE_COMPLETE = 'complete';
    private const STATE_SEALED = 'sealed';

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $session_hash_hex, string $workflow_id, string $request_id): ?array
    {
        $key   = $this->key($session_hash_hex, $workflow_id, $request_id);
        $state = get_option($key, null);
        if (! is_array($state)) {
            return null;
        }

        if (isset($state['gc_after']) && (int) $state['gc_after'] < time()) {
            delete_option($key);
            return null;
        }

        if (
            self::STATE_PENDING === ($state['state'] ?? null)
            && isset($state['expires_at'])
            && (int) $state['expires_at'] < time()
        ) {
            delete_option($key);
            return null;
        }

        if (
            self::STATE_COMPLETE === ($state['state'] ?? null)
            && isset($state['expires_at'])
            && (int) $state['expires_at'] < time()
        ) {
            $sealed = $this->compact_response($state);
            $this->persist($key, $sealed);

            return $sealed;
        }

        return $state;
    }

    public function begin(
        string $session_hash_hex,
        string $workflow_id,
        string $request_id,
        string $tool_name,
        string $fingerprint
    ): bool {
        $now = time();

        return add_option(
            $this->key($session_hash_hex, $workflow_id, $request_id),
            array(
                'state'       => self::STATE_PENDING,
                'tool_name'   => $tool_name,
                'fingerprint' => $fingerprint,
                'created_at'  => $now,
                'expires_at'  => $now + self::LIFETIME,
                'gc_after'    => $now + self::GUARD_LIFETIME,
            ),
            '',
            false
        );
    }

    /**
     * Persist an execution guard before invoking an Ability. The guard outlives
     * the absolute demo-session lifetime and is then eligible for cleanup.
     */
    public function mark_executing(string $session_hash_hex, string $workflow_id, string $request_id): bool
    {
        $key      = $this->key($session_hash_hex, $workflow_id, $request_id);
        $existing = get_option($key, null);
        if (! is_array($existing)) {
            return false;
        }

        $state = (string) ($existing['state'] ?? '');
        if (in_array($state, array(self::STATE_EXECUTING, self::STATE_EXECUTED, self::STATE_COMPLETE, self::STATE_SEALED), true)) {
            return true;
        }
        if (self::STATE_PENDING !== $state) {
            return false;
        }

        unset($existing['expires_at']);
        $existing['state']                = self::STATE_EXECUTING;
        $existing['execution_started_at'] = time();

        return $this->persist($key, $existing);
    }

    /**
     * Record that the Ability returned. Failure is safe because the earlier
     * executing state remains a durable guard.
     */
    public function mark_executed(string $session_hash_hex, string $workflow_id, string $request_id): bool
    {
        $key      = $this->key($session_hash_hex, $workflow_id, $request_id);
        $existing = get_option($key, null);
        if (! is_array($existing)) {
            return false;
        }

        $state = (string) ($existing['state'] ?? '');
        if (in_array($state, array(self::STATE_EXECUTED, self::STATE_COMPLETE, self::STATE_SEALED), true)) {
            return true;
        }
        if (self::STATE_EXECUTING !== $state) {
            return false;
        }

        unset($existing['expires_at']);
        $existing['state']               = self::STATE_EXECUTED;
        $existing['ability_returned_at'] = time();

        return $this->persist($key, $existing);
    }

    /**
     * @param array<string, mixed> $body Safe response body.
     */
    public function complete(
        string $session_hash_hex,
        string $workflow_id,
        string $request_id,
        int $status,
        array $body
    ): bool {
        $key      = $this->key($session_hash_hex, $workflow_id, $request_id);
        $existing = get_option($key, null);
        if (! is_array($existing) || ! isset($existing['fingerprint'], $existing['tool_name'])) {
            return false;
        }

        $existing['state']        = self::STATE_COMPLETE;
        $existing['status']       = $status;
        $existing['body']         = $body;
        $existing['completed_at'] = time();
        $existing['expires_at']   = time() + self::LIFETIME;
        unset($existing['finalization_error']);

        return $this->persist($key, $existing);
    }

    /**
     * Preserve a compact request tombstone when a response can no longer be
     * finalized or replayed safely.
     */
    public function seal(
        string $session_hash_hex,
        string $workflow_id,
        string $request_id,
        string $reason
    ): bool {
        $key      = $this->key($session_hash_hex, $workflow_id, $request_id);
        $existing = get_option($key, null);
        if (! is_array($existing)) {
            return false;
        }

        $sealed                       = $this->tombstone($existing);
        $sealed['state']              = self::STATE_SEALED;
        $sealed['sealed_at']          = time();
        $sealed['finalization_error'] = sanitize_key($reason);

        return $this->persist($key, $sealed);
    }

    /**
     * Delete only a reservation that is known not to have crossed the
     * execution boundary.
     */
    public function release(string $session_hash_hex, string $workflow_id, string $request_id): bool
    {
        $key      = $this->key($session_hash_hex, $workflow_id, $request_id);
        $existing = get_option($key, null);
        if (! is_array($existing) || self::STATE_PENDING !== ($existing['state'] ?? null)) {
            return false;
        }

        return delete_option($key);
    }

    public function cleanup(): int
    {
        global $wpdb;

        $like  = $wpdb->esc_like(self::PREFIX) . '%';
        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT 500",
                $like
            )
        );
        $count = 0;

        foreach (is_array($names) ? $names : array() as $name) {
            $key   = (string) $name;
            $state = get_option($key, null);
            if (! is_array($state)) {
                if (delete_option($key)) {
                    ++$count;
                }
                continue;
            }

            if (isset($state['gc_after']) && (int) $state['gc_after'] < time()) {
                if (delete_option($key)) {
                    ++$count;
                }
                continue;
            }

            if (
                self::STATE_PENDING === ($state['state'] ?? null)
                && isset($state['expires_at'])
                && (int) $state['expires_at'] < time()
                && delete_option($key)
            ) {
                ++$count;
                continue;
            }

            if (
                self::STATE_COMPLETE === ($state['state'] ?? null)
                && isset($state['expires_at'])
                && (int) $state['expires_at'] < time()
            ) {
                $this->persist($key, $this->compact_response($state));
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $state Stored response state.
     * @return array<string, mixed>
     */
    private function compact_response(array $state): array
    {
        $sealed              = $this->tombstone($state);
        $sealed['state']     = self::STATE_SEALED;
        $sealed['sealed_at'] = time();

        return $sealed;
    }

    /**
     * @param array<string, mixed> $state Stored state.
     * @return array<string, mixed>
     */
    private function tombstone(array $state): array
    {
        unset($state['body'], $state['status'], $state['expires_at']);

        return $state;
    }

    /**
     * Persist and then verify the exact state. WordPress returns false both
     * when an update fails and when a value is unchanged, so the read-back is
     * the authoritative result.
     *
     * @param array<string, mixed> $state State to persist.
     */
    private function persist(string $key, array $state): bool
    {
        update_option($key, $state, false);

        return $state === get_option($key, null);
    }

    private function key(string $session_hash_hex, string $workflow_id, string $request_id): string
    {
        return self::PREFIX . hash('sha256', $session_hash_hex . '|' . $workflow_id . '|' . $request_id);
    }
}
