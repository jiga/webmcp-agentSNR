<?php

/**
 * Isolated anonymous demo-session cookie management.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Demo;

final class DemoSession
{
    public const COOKIE = 'wmcp_demo_session';
    public const SECURE_COOKIE = '__Host-wmcp_demo_session';
    public const LIFETIME = DAY_IN_SECONDS;
    private const TRANSIENT_PREFIX = 'wmcp_demo_session_state_';

    /**
     * @return array{raw: string, hash: string, hash_hex: string}
     */
    public function ensure(): array
    {
        $context = $this->peek();
        if (null === $context) {
            return $this->issue();
        }

        $this->write_cookie($context['raw'], $context['expires_at']);

        return $context;
    }

    /**
     * Rotate the public scope instead of synchronously deleting shared records.
     *
     * @return array{raw: string, hash: string, hash_hex: string}
     */
    public function rotate(): array
    {
        $current = $this->peek();
        if (null !== $current) {
            delete_transient(self::TRANSIENT_PREFIX . $current['hash_hex']);
        }

        return $this->issue();
    }

    /**
     * Read a server-issued, unexpired session without creating a new one.
     *
     * @return array{raw: string, hash: string, hash_hex: string, expires_at: int}|null
     */
    public function peek(): ?array
    {
        $cookie_name = $this->cookie_name();
        $raw         = isset($_COOKIE[$cookie_name])
            ? sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]))
            : '';
        if (1 !== preg_match('/\A[a-f0-9]{64}\z/', $raw)) {
            return null;
        }

        $context = $this->context($raw);
        $state   = get_transient(self::TRANSIENT_PREFIX . $context['hash_hex']);
        if (! is_array($state) || ! isset($state['expires_at']) || (int) $state['expires_at'] < time()) {
            return null;
        }

        $context['expires_at'] = (int) $state['expires_at'];

        return $context;
    }

    public function hash_active(string $session_hash_hex): bool
    {
        if (1 !== preg_match('/\A[a-f0-9]{64}\z/', $session_hash_hex)) {
            return false;
        }

        $state = get_transient(self::TRANSIENT_PREFIX . $session_hash_hex);

        return is_array($state)
            && isset($state['expires_at'])
            && (int) $state['expires_at'] >= time();
    }

    public function expire_hash(string $session_hash_hex): void
    {
        if (1 === preg_match('/\A[a-f0-9]{64}\z/', $session_hash_hex)) {
            delete_transient(self::TRANSIENT_PREFIX . $session_hash_hex);
        }
    }

    /**
     * @return array{raw: string, hash: string, hash_hex: string, expires_at: int}
     */
    private function issue(): array
    {
        $raw = bin2hex(random_bytes(32));
        $context = $this->context($raw);
        $context['expires_at'] = time() + self::LIFETIME;
        set_transient(
            self::TRANSIENT_PREFIX . $context['hash_hex'],
            array('issued_at' => time(), 'expires_at' => $context['expires_at']),
            self::LIFETIME
        );
        $this->write_cookie($raw, $context['expires_at']);
        $_COOKIE[$this->cookie_name()] = $raw;

        return $context;
    }

    /**
     * @return array{raw: string, hash: string, hash_hex: string}
     */
    private function context(string $raw): array
    {
        $hash = hash('sha256', $raw, true);

        return array(
            'raw'      => $raw,
            'hash'     => $hash,
            'hash_hex' => bin2hex($hash),
        );
    }

    private function write_cookie(string $value, int $expires): void
    {
        setcookie(
            $this->cookie_name(),
            $value,
            array(
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }

    private function cookie_name(): string
    {
        return is_ssl() ? self::SECURE_COOKIE : self::COOKIE;
    }
}
