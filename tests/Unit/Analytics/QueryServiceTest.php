<?php

/**
 * Scoped analytics-query tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Analytics;

use WPWebMCP\AgentSNR\Analytics\QueryService;
use WPWebMCP\AgentSNR\Contract\EventName;

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
            array(
                'product_searches' => '1',
                'cart_mutations' => '1',
                'checkout_handoffs' => '1',
                'orders_created' => '2',
                'orders_paid' => '2',
                'refunds' => '0',
            ),
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

    public function test_large_replay_is_bounded_and_preserves_diagnostic_evidence(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $workflow = $this->workflow_row();
        $workflow['tool_count']    = 40;
        $workflow['last_event_at'] = '2026-08-29 20:00:40';

        $large_properties = array(
            'actor_type'          => str_repeat('a', 500),
            'agent_surface'       => str_repeat('b', 500),
            'analytics_consent'   => str_repeat('c', 500),
            'cart_item_count'     => 5,
            'cart_revision'       => str_repeat('d', 500),
            'checkout_ready'      => true,
            'client_name'         => str_repeat('e', 500),
            'client_version'      => str_repeat('f', 500),
            'confidence'          => str_repeat('g', 500),
            'requested_capability' => array_fill(0, 20, str_repeat('h', 500)),
        );
        $event_rows = array();
        for ($step = 1; $step <= 40; ++$step) {
            $event_name = EventName::TOOL_CALL_STARTED;
            $overrides  = array('properties_json' => wp_json_encode($large_properties));
            if (10 === $step) {
                $event_name = EventName::TOOL_CALL_FAILED;
                $overrides  = array_merge($overrides, array('outcome' => 'failed', 'error_code' => 'upstream_timeout'));
            } elseif (11 === $step) {
                $event_name = EventName::TOOL_CALL_SUCCEEDED;
                $overrides  = array_merge($overrides, array('outcome' => 'success'));
            } elseif (25 === $step) {
                $event_name = EventName::ORDER_PAID;
                $overrides  = array_merge(
                    $overrides,
                    array('currency' => 'USD', 'value' => '109.000000', 'product_ids_json' => wp_json_encode(range(1, 20)))
                );
            }
            $event_rows[] = $this->replay_event_row($step, $event_name, $overrides);
        }

        $database->row_results = array($workflow, $event_rows[9], $event_rows[10], $event_rows[24]);
        $database->result_sets = array(
            array_slice($event_rows, 0, 9),
            array_reverse(array_slice($event_rows, -13)),
            $this->order_rows(5),
            $this->gap_rows(7),
        );

        $result = (new QueryService($database))->explain(str_repeat('a', 64), (string) $workflow['id']);

        self::assertNotNull($result);
        self::assertTrue($result['truncated']);
        self::assertLessThanOrEqual(7000, strlen(wp_json_encode($result, JSON_UNESCAPED_SLASHES)));
        self::assertSame($this->event_id(1), $result['timeline'][0]['event_id']);
        self::assertSame($this->event_id(40), $result['timeline'][count($result['timeline']) - 1]['event_id']);
        self::assertSame($this->event_id(10), $result['first_problem']['event_id']);
        self::assertSame($this->event_id(11), $result['recovery']['event_id']);
        self::assertSame($this->event_id(25), $result['commerce_outcome']['last_event']['event_id']);
        self::assertLessThanOrEqual(4, count($result['commerce_outcome']['orders']));
        self::assertLessThanOrEqual(6, count($result['capability_gaps']));
    }

    public function test_exact_timeline_cap_remains_complete(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $workflow = $this->workflow_row();
        $event_rows = array();
        for ($step = 1; $step <= 20; ++$step) {
            $event_rows[] = $this->replay_event_row($step);
        }
        $database->row_results = array($workflow, array(), array());
        $database->result_sets = array(
            array_slice($event_rows, 0, 9),
            array_reverse(array_slice($event_rows, -13)),
            array(),
            array(),
        );

        $result = (new QueryService($database))->explain(str_repeat('a', 64), (string) $workflow['id']);

        self::assertNotNull($result);
        self::assertFalse($result['truncated']);
        self::assertCount(20, $result['timeline']);
        self::assertSame($this->event_id(1), $result['timeline'][0]['event_id']);
        self::assertSame($this->event_id(20), $result['timeline'][19]['event_id']);
        self::assertLessThanOrEqual(7000, strlen(wp_json_encode($result, JSON_UNESCAPED_SLASHES)));
    }

    public function test_short_replay_returns_every_event_without_partial_marker(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $workflow = $this->workflow_row();
        $events = array(
            $this->replay_event_row(
                1,
                EventName::WORKFLOW_STARTED,
                array('tool_name' => null, 'tool_version' => null, 'risk_class' => null, 'request_id' => null)
            ),
            $this->replay_event_row(2, EventName::TOOL_CALL_STARTED),
            $this->replay_event_row(3, EventName::TOOL_CALL_SUCCEEDED, array('outcome' => 'success')),
            $this->replay_event_row(
                4,
                EventName::WORKFLOW_COMPLETED,
                array('tool_name' => null, 'tool_version' => null, 'risk_class' => null, 'request_id' => null)
            ),
        );
        $database->row_results = array($workflow, array(), array());
        $database->result_sets = array($events, array_reverse($events), array(), array());

        $result = (new QueryService($database))->explain(str_repeat('a', 64), (string) $workflow['id']);

        self::assertNotNull($result);
        self::assertFalse($result['truncated']);
        self::assertSame(array_map(fn (array $row): string => (string) $row['event_id'], $events), array_column($result['timeline'], 'event_id'));
    }

    public function test_replay_over_two_hundred_events_keeps_true_tail_and_targeted_diagnostics(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $workflow = $this->workflow_row();
        $workflow['tool_count']    = 220;
        $workflow['last_event_at'] = $this->event_time(220);

        $head_rows = array();
        for ($step = 1; $step <= 9; ++$step) {
            $head_rows[] = $this->replay_event_row($step);
        }
        $tail_rows = array();
        for ($step = 220; $step >= 208; --$step) {
            $tail_rows[] = $this->replay_event_row($step);
        }
        $problem = $this->replay_event_row(
            201,
            EventName::TOOL_CALL_FAILED,
            array('outcome' => 'failed', 'error_code' => 'upstream_timeout')
        );
        $commerce = $this->replay_event_row(
            202,
            EventName::ORDER_PAID,
            array('currency' => 'USD', 'value' => '109.000000')
        );
        $recovery = $this->replay_event_row(
            203,
            EventName::TOOL_CALL_SUCCEEDED,
            array('outcome' => 'success')
        );

        $database->row_results = array($workflow, $problem, $recovery, $commerce);
        $database->result_sets = array($head_rows, $tail_rows, array(), array());

        $result = (new QueryService($database))->explain(str_repeat('a', 64), (string) $workflow['id']);

        self::assertNotNull($result);
        self::assertTrue($result['truncated']);
        self::assertCount(20, $result['timeline']);
        self::assertSame($this->event_id(1), $result['timeline'][0]['event_id']);
        self::assertSame($this->event_id(220), $result['timeline'][19]['event_id']);
        self::assertSame($this->event_id(201), $result['first_problem']['event_id']);
        self::assertSame($this->event_id(203), $result['recovery']['event_id']);
        self::assertSame($this->event_id(202), $result['commerce_outcome']['last_event']['event_id']);
        self::assertStringContainsString('upstream_timeout', $result['explanation']);
        self::assertStringContainsString('later successful search_products', $result['explanation']);
        self::assertLessThanOrEqual(7000, strlen(wp_json_encode($result, JSON_UNESCAPED_SLASHES)));

        self::assertStringContainsString('ORDER BY e.occurred_at DESC', $database->queries[2]['sql']);
        foreach (array_slice($database->queries, 1, 5) as $query) {
            self::assertContains(str_repeat('a', 64), $query['args']);
            self::assertContains((string) $workflow['id'], $query['args']);
        }
    }

    public function test_payload_reduction_rebuilds_narrative_from_remaining_orders_and_gaps(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $workflow = $this->workflow_row();
        $events = array(
            $this->replay_event_row(1),
            $this->replay_event_row(2),
            $this->replay_event_row(3),
            $this->replay_event_row(4),
        );
        $orders = $this->order_rows(4);
        foreach ($orders as &$order) {
            $order['attribution_class'] = str_repeat('a', 1600);
        }
        unset($order);
        $gaps = $this->gap_rows(6);
        foreach ($gaps as &$gap) {
            $gap['capability_slug'] = str_repeat('g', 1600);
        }
        unset($gap);

        $database->row_results = array($workflow, array(), array());
        $database->result_sets = array($events, array_reverse($events), $orders, $gaps);

        $result = (new QueryService($database))->explain(str_repeat('a', 64), (string) $workflow['id']);

        self::assertNotNull($result);
        self::assertTrue($result['truncated']);
        self::assertLessThanOrEqual(7000, strlen(wp_json_encode($result, JSON_UNESCAPED_SLASHES)));

        $order_count = count($result['commerce_outcome']['orders']);
        $gap_count   = count($result['capability_gaps']);
        self::assertLessThan(4, $order_count);
        self::assertLessThan(6, $gap_count);
        self::assertStringContainsString(
            0 === $order_count ? 'No attributed WooCommerce order' : "{$order_count} attributed WooCommerce order",
            $result['explanation']
        );
        if (0 === $gap_count) {
            self::assertStringNotContainsString('unsupported capability request', $result['explanation']);
        } else {
            self::assertStringContainsString("{$gap_count} unsupported capability request", $result['explanation']);
        }
    }

    public function test_explain_returns_null_for_workflow_outside_scope(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->row_results = array(array());

        $result = (new QueryService($database))->explain(
            str_repeat('a', 64),
            '01ARZ3NDEKTSV4RRFFQ69G5FAV'
        );

        self::assertNull($result);
        self::assertCount(1, $database->queries);
    }

    /**
     * @param array<string, mixed> $overrides Event-field overrides.
     * @return array<string, mixed>
     */
    private function replay_event_row(int $step, string $event_name = EventName::TOOL_CALL_STARTED, array $overrides = array()): array
    {
        return array_replace(
            array(
                'row_id'           => $step,
                'event_id'         => $this->event_id($step),
                'event_name'       => $event_name,
                'request_id'       => 'req_' . str_pad(dechex($step), 32, '0', STR_PAD_LEFT),
                'step_number'      => $step,
                'tool_name'        => 'search_products',
                'tool_version'     => '1.0.0',
                'risk_class'       => 'read',
                'outcome'          => null,
                'duration_ms'      => null,
                'error_code'       => null,
                'http_status'      => null,
                'product_ids_json' => '[]',
                'currency'         => null,
                'value'            => null,
                'properties_json'  => '{"status":"ok"}',
                'occurred_at'      => $this->event_time($step),
            ),
            $overrides
        );
    }

    private function event_id(int $step): string
    {
        return 'evt_' . str_pad((string) $step, 26, '0', STR_PAD_LEFT);
    }

    private function event_time(int $step): string
    {
        return sprintf('2026-08-29 20:%02d:%02d', intdiv($step, 60), $step % 60);
    }

    /** @return list<array<string, mixed>> */
    private function order_rows(int $count): array
    {
        $rows = array();
        for ($index = $count; $index >= 1; --$index) {
            $rows[] = array(
                'order_id'               => $index,
                'attribution_class'      => 'agent_direct',
                'confidence'             => 'high',
                'rule_version'           => '1.0.0',
                'gross_value'            => '109.000000',
                'refund_value'           => '0.000000',
                'net_value'              => '109.000000',
                'currency'               => 'USD',
                'matched_products_json'  => wp_json_encode(range(1, 20)),
                'evidence_event_ids_json' => wp_json_encode(array_map(fn (int $step): string => $this->event_id($step), range(1, 20))),
                'updated_at'              => sprintf('2026-08-29 21:00:%02d', $index),
            );
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function gap_rows(int $count): array
    {
        $rows = array();
        for ($index = $count; $index >= 1; --$index) {
            $rows[] = array(
                'id'                 => 'gap_' . $index,
                'capability_slug'    => 'gift-wrap-' . $index,
                'related_product_id' => $index,
                'status'             => 'open',
                'occurred_at'        => sprintf('2026-08-29 22:00:%02d', $index),
            );
        }

        return $rows;
    }
}
