<?php

/**
 * Scoped analytics-query tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Analytics;

use WPWebMCP\AgentOps\Analytics\QueryService;

require_once __DIR__ . '/AnalyticsTestCase.php';

final class QueryServiceTest extends AnalyticsTestCase
{
    public function test_workflow_query_scopes_in_sql_before_fetch(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets = array(array());
        $hash = str_repeat('a', 64);

        $result = (new QueryService($database))->workflows($hash);

        self::assertSame(array(), $result['items']);
        self::assertStringContainsString('w.demo_session_hash = %s', $database->queries[0]['sql']);
        self::assertStringContainsString("w.surface = 'storefront'", $database->queries[0]['sql']);
        self::assertContains($hash, $database->queries[0]['args']);
    }

    public function test_overview_keeps_currencies_separate(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->row_results = array(
            array('total' => '1', 'active' => '0', 'completed' => '1', 'abandoned' => '0', 'expired' => '0'),
            array('calls' => '1', 'succeeded' => '1', 'failed' => '0', 'cancelled' => '0', 'denied' => '0', 'workflows' => '1'),
            array('product_searches' => '1', 'cart_mutations' => '1', 'checkout_handoffs' => '1', 'orders_created' => '2', 'orders_paid' => '2', 'refunds' => '0'),
            array('requests' => '0', 'workflows' => '0', 'capabilities' => '0'),
        );
        $database->result_sets = array(
            array(array('duration_ms' => '20')),
            array(
                array('attribution_class' => 'agent_direct', 'currency' => 'USD', 'orders' => '1', 'gross' => '100', 'refunds' => '0', 'net' => '100'),
                array('attribution_class' => 'agent_direct', 'currency' => 'EUR', 'orders' => '1', 'gross' => '80', 'refunds' => '0', 'net' => '80'),
            ),
        );

        $result = (new QueryService($database))->overview(str_repeat('a', 64));

        self::assertArrayNotHasKey('gross', $result['revenue']);
        self::assertSame(100.0, $result['revenue']['by_currency']['USD']['gross']);
        self::assertSame(80.0, $result['revenue']['by_currency']['EUR']['gross']);
        self::assertSame(2, $result['revenue']['orders']);
    }
}
