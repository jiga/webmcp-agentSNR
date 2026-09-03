<?php

/**
 * Fail-closed demo cleanup tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Demo;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentSNR\Analytics\WorkflowService;
use WPWebMCP\AgentSNR\Demo\DemoCleanup;
use WPWebMCP\AgentSNR\Demo\DemoSession;
use WPWebMCP\AgentSNR\WebMCP\IdempotencyStore;

require_once __DIR__ . '/CleanupDatabaseDouble.php';
require_once __DIR__ . '/CleanupOrderDouble.php';

final class DemoCleanupTest extends TestCase
{
    private const SESSION_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private CleanupDatabaseDouble $database;

    protected function setUp(): void
    {
        $_COOKIE = array();
        $GLOBALS['wmcp_test_options'] = array();
        $GLOBALS['wmcp_test_transients'] = array();
        $GLOBALS['wmcp_test_scheduled_events'] = array();
        $this->database = new CleanupDatabaseDouble();
        $GLOBALS['wpdb'] = $this->database;
    }

    public function test_unavailable_woocommerce_reschedules_without_deleting_related_data(): void
    {
        $result = $this->cleanup()->cleanup_session(self::SESSION_HASH);

        self::assertFalse($result['completed']);
        self::assertSame('woocommerce_unavailable', $result['order_cleanup_status']);
        self::assertSame(0, $result['orders_found']);
        self::assertSame(0, $result['orders_deleted']);
        self::assertSame(array(), $this->database->deletes);
        self::assertSame(0, $this->database->get_col_calls);
        $this->assert_rescheduled_once();
    }

    public function test_delete_failure_is_distinct_from_zero_found_and_preserves_related_data(): void
    {
        $calls = 0;
        $limits = array();
        $order = new CleanupOrderDouble(self::SESSION_HASH, false);
        $cleanup = $this->cleanup(
            static function (array $arguments) use (&$calls, &$limits, $order): array {
                ++$calls;
                $limits[] = $arguments['limit'];

                return array($order);
            }
        );

        $result = $cleanup->cleanup_session(self::SESSION_HASH);

        self::assertFalse($result['completed']);
        self::assertSame('delete_failed', $result['order_cleanup_status']);
        self::assertSame(1, $result['orders_found']);
        self::assertSame(0, $result['orders_deleted']);
        self::assertSame(1, $result['orders_remaining']);
        self::assertSame(2, $calls, 'Cleanup must re-query after its bounded deletion attempt.');
        self::assertSame(array(20, 1), $limits);
        self::assertSame(array(), $this->database->deletes);
        $this->assert_rescheduled_once();
    }

    public function test_successful_delete_with_remaining_order_reschedules_and_preserves_related_data(): void
    {
        $calls = 0;
        $deleted_order = new CleanupOrderDouble(self::SESSION_HASH, true);
        $remaining_order = new CleanupOrderDouble(self::SESSION_HASH, true);
        $cleanup = $this->cleanup(
            static function (array $arguments) use (&$calls, $deleted_order, $remaining_order): array {
                ++$calls;

                return 20 === $arguments['limit'] ? array($deleted_order) : array($remaining_order);
            }
        );

        $result = $cleanup->cleanup_session(self::SESSION_HASH);

        self::assertFalse($result['completed']);
        self::assertSame('orders_remaining', $result['order_cleanup_status']);
        self::assertSame(1, $result['orders_found']);
        self::assertSame(1, $result['orders_deleted']);
        self::assertSame(1, $result['orders_remaining']);
        self::assertSame(2, $calls);
        self::assertSame(1, $deleted_order->delete_calls);
        self::assertTrue($deleted_order->force_delete);
        self::assertSame(array(), $this->database->deletes);
        $this->assert_rescheduled_once();
    }

    public function test_zero_orders_is_confirmed_by_a_second_query_before_related_cleanup(): void
    {
        $calls = 0;
        $limits = array();
        $cleanup = $this->cleanup(
            static function (array $arguments) use (&$calls, &$limits): array {
                ++$calls;
                $limits[] = $arguments['limit'];

                return array();
            }
        );

        $result = $cleanup->cleanup_session(self::SESSION_HASH);

        self::assertTrue($result['completed']);
        self::assertSame('confirmed_empty', $result['order_cleanup_status']);
        self::assertSame(0, $result['orders_found']);
        self::assertSame(0, $result['orders_deleted']);
        self::assertSame(2, $calls);
        self::assertSame(array(20, 1), $limits);
        self::assertSame(1, $this->database->get_col_calls);
        self::assertNotSame(array(), $this->database->deletes);
        self::assertSame(array(), $GLOBALS['wmcp_test_scheduled_events']);
    }

    public function test_related_delete_failure_reschedules_without_clearing_session_scoped_state(): void
    {
        $workflow_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $this->database->workflow_ids = array($workflow_id);
        $this->database->failing_delete_table = 'wp_wmcp_events';
        set_transient('wmcp_demo_policy_' . self::SESSION_HASH, array('tools' => array()), DAY_IN_SECONDS);
        set_transient('wmcp_demo_orders_' . self::SESSION_HASH, array(42), DAY_IN_SECONDS);
        $cleanup = $this->cleanup(static fn (array $arguments): array => array());

        $result = $cleanup->cleanup_session(self::SESSION_HASH);

        self::assertFalse($result['completed']);
        self::assertSame('confirmed_empty', $result['order_cleanup_status']);
        self::assertSame(0, $result['workflows_deleted']);
        self::assertSame(
            array('wp_wmcp_order_links', 'wp_wmcp_events'),
            array_column($this->database->deletes, 'table')
        );
        self::assertIsArray(get_transient('wmcp_demo_policy_' . self::SESSION_HASH));
        self::assertSame(array(42), get_transient('wmcp_demo_orders_' . self::SESSION_HASH));
        $this->assert_rescheduled_once();
    }

    private function cleanup(?callable $order_query = null): DemoCleanup
    {
        return new DemoCleanup(
            new WorkflowService($this->database),
            new IdempotencyStore(),
            new DemoSession(),
            null,
            $order_query
        );
    }

    private function assert_rescheduled_once(): void
    {
        self::assertCount(1, $GLOBALS['wmcp_test_scheduled_events']);
        self::assertSame('wmcp_agentsnr_cleanup_session', $GLOBALS['wmcp_test_scheduled_events'][0]['hook']);
        self::assertSame(array(self::SESSION_HASH), $GLOBALS['wmcp_test_scheduled_events'][0]['args']);
    }
}
