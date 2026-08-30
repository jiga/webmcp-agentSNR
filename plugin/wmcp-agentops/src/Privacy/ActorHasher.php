<?php

/**
 * One-way identifiers for analytics scope keys.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Privacy;

final class ActorHasher
{
    public function binary(string $value): string
    {
        return hash_hmac('sha256', $value, wp_salt('secure_auth'), true);
    }

    public function hex(string $value): string
    {
        return bin2hex($this->binary($value));
    }
}
