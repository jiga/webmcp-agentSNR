<?php

/**
 * Funnel aggregation tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Analytics;

use WPWebMCP\AgentSNR\Analytics\FunnelService;
use WPWebMCP\AgentSNR\Contract\EventName;

require_once __DIR__ . '/AnalyticsTestCase.php';

final class FunnelServiceTest extends AnalyticsTestCase
{
    public function test_funnel_uses_only_sql_scoped_storefront_workflows(): void
    {
        $workflow_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets = array(
            array(array('id' => $workflow_id, 'status' => 'active', 'started_at' => '2026-08-29 20:00:00', 'ended_at' => null)),
            array(
                array('workflow_id' => $workflow_id, 'event_name' => EventName::PRODUCT_SEARCHED, 'error_code' => null, 'occurred_at' => '2026-08-29 20:00:01'),
                array('workflow_id' => $workflow_id, 'event_name' => EventName::PRODUCTS_COMPARED, 'error_code' => null, 'occurred_at' => '2026-08-29 20:00:03'),
                array('workflow_id' => $workflow_id, 'event_name' => EventName::CART_CHANGED, 'error_code' => null, 'occurred_at' => '2026-08-29 20:00:05'),
            ),
            array(),
        );
        $hash = str_repeat('a', 64);

        $result = (new FunnelService($database))->get($hash);

        self::assertSame(1, $result['workflow_count']);
        self::assertSame(1, $result['stages'][2]['workflow_count']);
        self::assertSame('product_viewed', $result['stages'][2]['stage']);
        foreach ($database->queries as $query) {
            self::assertStringContainsString('demo_session_hash = %s', $query['sql']);
            self::assertStringContainsString("surface = 'storefront'", $query['sql']);
            self::assertContains($hash, $query['args']);
        }
    }
}
