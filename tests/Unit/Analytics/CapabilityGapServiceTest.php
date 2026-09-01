<?php

/**
 * Capability-gap privacy and replay tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Analytics;

use WPWebMCP\AgentOps\Analytics\CapabilityGapService;
use WPWebMCP\AgentOps\Contract\EventName;

require_once __DIR__ . '/AnalyticsTestCase.php';

final class CapabilityGapServiceTest extends AnalyticsTestCase
{
    public function test_report_discards_free_text_and_exact_replay_is_idempotent(): void
    {
        $database = new AnalyticsDatabaseDouble();
        $workflow = $this->workflow_row();
        $database->workflows[$workflow['id']] = $workflow;
        $service = new CapabilityGapService(
            $database,
            null,
            null,
            null,
            static fn (): string => '2026-08-29 20:05:00',
            static fn (): string => '01ARZ3NDEKTSV4RRFFQ69G5FAB'
        );
        $goal = 'Email shopper@example.test or call +1 (415) 555-0199 when blue is available.';

        $first = $service->report(
            $workflow['id'],
            $workflow['demo_session_hash'],
            'back_in_stock_notification',
            $goal,
            123,
            array('color' => 'blue', 'email' => 'shopper@example.test')
        );
        $replay = $service->report(
            $workflow['id'],
            $workflow['demo_session_hash'],
            'back_in_stock_notification',
            $goal,
            123,
            array('color' => 'blue', 'email' => 'shopper@example.test')
        );

        self::assertFalse($first['replayed']);
        self::assertTrue($replay['replayed']);
        self::assertSame($first['gap_id'], $replay['gap_id']);
        self::assertFalse($first['fulfilled']);
        self::assertCount(1, $database->gaps);
        $stored = array_values($database->gaps)[0];
        self::assertSame(
            'Shopper requested a back-in-stock notification.',
            $stored['user_goal_redacted']
        );
        self::assertStringNotContainsString('shopper@example.test', $stored['user_goal_redacted']);
        self::assertStringNotContainsString('415', $stored['user_goal_redacted']);
        self::assertSame('{"color":"blue"}', $stored['context_json']);
        self::assertSame('agent_reported', $stored['signal_source']);
        self::assertSame('capability_gap', $stored['signal_category']);
        self::assertSame(hash('sha256', 'capability_gap|back_in_stock_notification'), $stored['signal_key']);
        self::assertCount(
            1,
            array_filter(
                $database->events,
                static fn (array $event): bool => EventName::CAPABILITY_GAP_REPORTED === $event['event_name']
            )
        );
    }

    public function test_grouped_results_are_compact_and_use_opportunity_not_lost_revenue_language(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets = array(
            array(
                array(
                    'capability_slug'  => 'back_in_stock_notification',
                    'status'           => 'open',
                    'requests'         => '3',
                    'workflows'        => '2',
                    'latest_occurrence' => '2026-08-29 20:05:00',
                ),
            ),
            array(
                array(
                    'capability_slug'   => 'back_in_stock_notification',
                    'status'            => 'open',
                    'related_product_id' => '123',
                    'requests'          => '3',
                ),
            ),
            array(
                array(
                    'capability_slug' => 'back_in_stock_notification',
                    'status'          => 'open',
                    'currency'        => 'USD',
                    'value'           => '109.000000',
                ),
            ),
        );
        $service = new CapabilityGapService($database);

        $result = $service->grouped(str_repeat('a', 64), array('limit' => 10));

        self::assertSame(3, $result['items'][0]['requests']);
        self::assertSame(array(123), $result['items'][0]['related_product_ids']);
        self::assertSame(array('USD' => 109.0), $result['items'][0]['viewed_product_value_context']);
        self::assertStringNotContainsString('lost revenue', strtolower($result['items'][0]['opportunity_context_label']));
        foreach ($database->queries as $query) {
            self::assertStringContainsString('demo_session_hash = %s', $query['sql']);
            self::assertContains(str_repeat('a', 64), $query['args']);
        }
    }
}
