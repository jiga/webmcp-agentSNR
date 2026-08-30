<?php

/**
 * Scoped WooCommerce-order cleanup test double.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Demo;

final class CleanupOrderDouble
{
    public int $delete_calls = 0;

    public bool $force_delete = false;

    public function __construct(
        private readonly string $session_hash,
        private readonly bool $delete_succeeds
    ) {
    }

    /** @return string */
    public function get_meta(string $key, bool $single = true)
    {
        unset($single);

        return match ($key) {
            '_wmcp_demo_order'        => 'yes',
            '_wmcp_demo_session_hash' => $this->session_hash,
            default                   => '',
        };
    }

    public function delete(bool $force_delete): bool
    {
        ++$this->delete_calls;
        $this->force_delete = $force_delete;

        return $this->delete_succeeds;
    }
}
