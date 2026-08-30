<?php

/**
 * Server-issued demo-session security tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Demo;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentOps\Demo\DemoSession;

final class DemoSessionTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = array();
        $GLOBALS['wmcp_test_transients'] = array();
    }

    public function test_unknown_attacker_chosen_cookie_is_rotated_and_registered(): void
    {
        $chosen = str_repeat('a', 64);
        $_COOKIE[DemoSession::COOKIE] = $chosen;

        $context = (new DemoSession())->ensure();

        self::assertNotSame($chosen, $context['raw']);
        self::assertSame(64, strlen($context['raw']));
        self::assertTrue((new DemoSession())->hash_active($context['hash_hex']));
    }

    public function test_issued_cookie_is_reused_until_server_expiry(): void
    {
        $sessions = new DemoSession();
        $first    = $sessions->ensure();
        $second   = $sessions->ensure();

        self::assertSame($first['raw'], $second['raw']);
        self::assertSame($first['expires_at'], $second['expires_at']);

        $key = 'wmcp_demo_session_state_' . $first['hash_hex'];
        $GLOBALS['wmcp_test_transients'][$key]['value']['expires_at'] = time() - 1;
        $third = $sessions->ensure();

        self::assertNotSame($first['raw'], $third['raw']);
    }

    public function test_rotate_invalidates_prior_server_scope(): void
    {
        $sessions = new DemoSession();
        $first    = $sessions->ensure();
        $second   = $sessions->rotate();

        self::assertFalse($sessions->hash_active($first['hash_hex']));
        self::assertTrue($sessions->hash_active($second['hash_hex']));
        self::assertNotSame($first['raw'], $second['raw']);
    }
}
