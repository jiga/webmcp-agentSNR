<?php

/**
 * Unified opportunity and structured-feedback signal tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Analytics;

use InvalidArgumentException;
use WPWebMCP\AgentSNR\Analytics\SignalService;
use WPWebMCP\AgentSNR\Contract\EventName;

require_once __DIR__ . '/AnalyticsTestCase.php';

final class SignalServiceTest extends AnalyticsTestCase
{
    private const EVENT = 'evt_01ARZ3NDEKTSV4RRFFQ69G5FAA';
    private const FEEDBACK_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAB';
    private const REQUEST = '550e8400-e29b-41d4-a716-446655440000';

    public function test_observed_search_signal_is_idempotent_and_contains_no_raw_query(): void
    {
        $database = $this->database_with_workflow();
        $service  = $this->service($database);
        $analysis = array(
            'demand' => array(
                'key'     => str_repeat('b', 64),
                'title'   => 'Waterproof backpack · under $100',
                'context' => array(
                    'terms'        => array('backpack', 'waterproof'),
                    'price_bucket' => 'under_100',
                ),
            ),
            'metrics' => array(
                'eligible_product_count'         => 0,
                'highest_matching_water_rating' => null,
            ),
            'signals' => array(
                array(
                    'category' => 'demand_gap',
                    'code'     => 'zero_results',
                    'action'   => 'improve_product_coverage',
                ),
            ),
        );
        $semantic = array('event_id' => self::EVENT, 'workflow_id' => $this->workflow_row()['id']);

        $first  = $service->observe_search($semantic, $analysis);
        $replay = $service->observe_search($semantic, $analysis);

        self::assertCount(1, $first);
        self::assertSame($first[0]['id'], $replay[0]['id']);
        self::assertTrue($replay[0]['replayed']);
        self::assertCount(1, $database->gaps);
        self::assertCount(
            1,
            array_filter(
                $database->events,
                static fn (array $event): bool => EventName::OPPORTUNITY_DETECTED === $event['event_name']
            )
        );
        $stored = array_values($database->gaps)[0];
        self::assertSame('site_observed', $stored['signal_source']);
        self::assertSame('verified', $stored['evidence_status']);
        self::assertStringNotContainsString('query', (string) $stored['context_json']);
        self::assertStringNotContainsString('prompt', (string) $stored['context_json']);
    }

    public function test_feedback_is_linked_deduplicated_and_returns_flat_site_measurements(): void
    {
        $database = $this->database_with_workflow();
        $this->add_search_event($database);
        $service = $this->service($database);
        $input   = $this->feedback_input();

        $first = $service->report_feedback(
            $this->workflow_row()['id'],
            $this->workflow_row()['demo_session_hash'],
            self::REQUEST,
            $input + array(
                'comment'       => 'Email shopper@example.test or call 415-555-0199.',
                'metric_values' => array('paid_order_value' => 999999),
            )
        );
        $replay = $service->report_feedback(
            $this->workflow_row()['id'],
            $this->workflow_row()['demo_session_hash'],
            '550e8400-e29b-41d4-a716-446655440001',
            $input
        );

        self::assertTrue($first['recorded']);
        self::assertFalse($first['replayed']);
        self::assertTrue($replay['replayed']);
        self::assertSame($first['feedback_id'], $replay['feedback_id']);
        self::assertSame('agent_reported', $first['trust']);
        self::assertSame('linked', $first['evidence_status']);
        self::assertSame(
            array('value' => 2, 'status' => 'verified'),
            $first['measured_context']['eligible_product_count']
        );
        self::assertSame(
            array('value' => 'IPX4', 'status' => 'verified'),
            $first['measured_context']['highest_matching_water_rating']
        );
        self::assertSame(
            array('value' => 1, 'status' => 'verified'),
            $first['measured_context']['search_refinement_count']
        );
        self::assertArrayNotHasKey('metric_values', $first);
        self::assertCount(1, $database->gaps);
        $encoded = json_encode(array_values($database->gaps)[0], JSON_UNESCAPED_SLASHES);
        self::assertIsString($encoded);
        self::assertStringNotContainsString('shopper@example.test', $encoded);
        self::assertStringNotContainsString('415-555', $encoded);
        self::assertStringNotContainsString('999999', $encoded);
        self::assertCount(
            1,
            array_filter(
                $database->events,
                static fn (array $event): bool => EventName::AGENT_FEEDBACK_REPORTED === $event['event_name']
            )
        );
    }

    public function test_feedback_limit_is_semantic_and_cross_session_scope_fails_closed(): void
    {
        $database = $this->database_with_workflow();
        $this->add_search_event($database);
        for ($index = 0; $index < 2; ++$index) {
            $database->gaps['existing-' . $index] = array(
                'workflow_id'      => $this->workflow_row()['id'],
                'signal_source'    => 'agent_reported',
                'evidence_status'  => 'linked',
                'reason_code'      => 'low_coverage',
            );
        }
        $service = $this->service($database);

        try {
            $service->report_feedback(
                $this->workflow_row()['id'],
                str_repeat('b', 64),
                self::REQUEST,
                $this->feedback_input()
            );
            self::fail('A foreign demo session must not attach feedback.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('not available', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('feedback limit');
        $service->report_feedback(
            $this->workflow_row()['id'],
            $this->workflow_row()['demo_session_hash'],
            self::REQUEST,
            $this->feedback_input()
        );
    }

    public function test_feedback_uses_database_unique_slots_for_race_safe_budget(): void
    {
        $database = $this->database_with_workflow();
        $this->add_search_event($database);
        $database->gaps['occupied-slot'] = array(
            'id'             => 'occupied-slot',
            'workflow_id'    => $this->workflow_row()['id'],
            'signal_source'  => 'site_observed',
            'feedback_slot'  => 1,
        );

        $feedback = $this->service($database)->report_feedback(
            $this->workflow_row()['id'],
            $this->workflow_row()['demo_session_hash'],
            self::REQUEST,
            $this->feedback_input()
        );

        self::assertTrue($feedback['recorded']);
        $stored = $database->gaps[$feedback['feedback_id']];
        self::assertSame(2, $stored['feedback_slot']);
    }

    public function test_grouped_signals_are_current_session_scoped_and_keep_provenance_separate(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->row_results[] = array(
            'id'                     => '01ARZ3NDEKTSV4RRFFQ69G5FBC',
            'workflow_id'            => $this->workflow_row()['id'],
            'signal_source'          => 'agent_reported',
            'requested_metrics_json' => null,
            'measured_context_json'  => '{"eligible_product_count":{"value":2,"status":"verified"},'
                . '"highest_matching_water_rating":{"value":"IPX4","status":"verified"}}',
            'occurred_at'            => '2026-08-31 12:00:00',
        );
        $database->result_sets[] = array(
            array(
                'signal_key'             => str_repeat('c', 64),
                'signal_category'        => 'demand_gap',
                'signal_code'            => 'low_coverage',
                'signal_title'           => 'Waterproof backpack · under $100',
                'status'                 => 'open',
                'observed_count'         => '2',
                'feedback_count'         => '1',
                'affected_workflows'     => '2',
                'distinct_sessions'      => '2',
                'related_product_id'     => '18',
                'measured_context_json'  => '{"eligible_product_count":{"value":2,"status":"verified"},'
                    . '"highest_matching_water_rating":{"value":"IPX4","status":"verified"}}',
                'suggested_action'       => 'improve_product_coverage',
                'latest_occurrence'      => '2026-08-31 12:00:00',
                'has_verified'           => '1',
                'has_linked'             => '1',
            ),
        );
        $result = (new SignalService($database))->grouped(str_repeat('a', 64));

        self::assertSame('current_demo_session', $result['scope']['visibility']);
        self::assertSame(array('site_observed' => true, 'agent_reported' => true), $result['items'][0]['sources']);
        self::assertSame('verified', $result['items'][0]['evidence_status']);
        self::assertSame('latest_workflow_sample', $result['items'][0]['measurement_scope']['kind']);
        self::assertSame('agent_reported', $result['items'][0]['measurement_scope']['source']);
        self::assertSame($this->workflow_row()['id'], $result['items'][0]['measurement_scope']['workflow_id']);
        self::assertSame(
            array('value' => 2, 'status' => 'verified'),
            $result['items'][0]['measured_context']['eligible_product_count']
        );
        self::assertStringContainsString('g.demo_session_hash = %s', $database->queries[0]['sql']);
        self::assertContains(str_repeat('a', 64), $database->queries[0]['args']);
    }

    public function test_grouped_signals_are_trimmed_below_the_execution_budget(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $rows = array();
        for ($index = 0; $index < 8; ++$index) {
            $rows[] = array(
                'signal_key'            => hash('sha256', 'signal-' . $index),
                'signal_category'       => 'demand_gap',
                'signal_code'           => 'low_coverage_' . $index,
                'signal_title'          => str_repeat('Opportunity ' . $index . ' ', 15),
                'status'                => 'open',
                'observed_count'        => '1',
                'feedback_count'        => '0',
                'affected_workflows'    => '1',
                'distinct_sessions'     => '1',
                'related_product_id'    => null,
                'measured_context_json' => json_encode(
                    array('bounded_detail' => array('value' => str_repeat('x', 1200), 'status' => 'verified')),
                    JSON_THROW_ON_ERROR
                ),
                'suggested_action'      => 'improve_product_coverage',
                'latest_occurrence'     => '2026-08-31 12:00:00',
                'has_verified'          => '1',
                'has_linked'            => '0',
            );
            $database->row_results[] = null;
            $database->row_results[] = array(
                'id'                     => 'observed-' . $index,
                'workflow_id'            => $this->workflow_row()['id'],
                'signal_source'          => 'site_observed',
                'requested_metrics_json' => null,
                'measured_context_json'  => $rows[$index]['measured_context_json'],
                'occurred_at'            => '2026-08-31 12:00:00',
            );
        }
        $database->result_sets[] = $rows;

        $result = (new SignalService($database))->grouped(str_repeat('a', 64), array('limit' => 8));

        self::assertTrue($result['truncated']);
        self::assertTrue($result['has_more']);
        self::assertNotNull($result['next_cursor']);
        self::assertLessThanOrEqual(6500, strlen(json_encode($result, JSON_THROW_ON_ERROR)));
        self::assertLessThan(8, count($result['items']));
    }

    public function test_group_measurement_re_resolves_latest_feedback_and_never_claims_group_aggregate(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets = array(
            array(
                array(
                    'signal_key'         => str_repeat('e', 64),
                    'signal_category'    => 'demand_gap',
                    'signal_code'        => 'low_coverage',
                    'signal_title'       => 'Waterproof backpack · under $100',
                    'status'             => 'open',
                    'observed_count'     => '3',
                    'feedback_count'     => '2',
                    'affected_workflows' => '3',
                    'distinct_sessions'  => '1',
                    'related_product_id' => '18',
                    'suggested_action'   => 'improve_product_coverage',
                    'latest_occurrence'  => '2026-08-31 14:00:00',
                    'has_verified'       => '1',
                    'has_linked'         => '1',
                ),
            ),
            array(array('properties_json' => '{"result_count":2,"highest_water_rating":"IPX4"}')),
            array(array('currency' => 'USD', 'orders' => '1', 'gross' => '69.000000', 'net' => '69.000000')),
        );
        $database->row_results[] = array(
            'id'                     => '01ARZ3NDEKTSV4RRFFQ69G5FBD',
            'workflow_id'            => $this->workflow_row()['id'],
            'signal_source'          => 'agent_reported',
            'requested_metrics_json' => '["checkout_conversion","paid_order_value"]',
            'measured_context_json'  => '{"checkout_conversion":{"value":null,"status":"pending"}}',
            'occurred_at'            => '2026-08-31 13:00:00',
        );
        $database->var_results[] = 1;

        $item = (new SignalService($database))->grouped(str_repeat('a', 64))['items'][0];

        self::assertSame(array('value' => true, 'status' => 'verified'), $item['measured_context']['checkout_conversion']);
        self::assertSame(69.0, $item['measured_context']['paid_order_value']['value'][0]['value']);
        self::assertSame(
            array(
                'kind'        => 'latest_workflow_sample',
                'workflow_id' => $this->workflow_row()['id'],
                'source'      => 'agent_reported',
                'occurred_at' => '2026-08-31 13:00:00',
            ),
            $item['measurement_scope']
        );
        self::assertStringNotContainsString('MAX(g.measured_context_json)', $database->queries[0]['sql']);
    }

    public function test_group_measurement_falls_back_to_latest_observed_single_workflow(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets[] = array(
            array(
                'signal_key'         => str_repeat('f', 64),
                'signal_category'    => 'inventory_gap',
                'signal_code'        => 'out_of_stock_match',
                'signal_title'       => 'TerraRoll 25 Pack · out of stock',
                'status'             => 'open',
                'observed_count'     => '1',
                'feedback_count'     => '0',
                'affected_workflows' => '1',
                'distinct_sessions'  => '1',
                'related_product_id' => '25',
                'suggested_action'   => 'review_inventory',
                'latest_occurrence'  => '2026-08-31 15:00:00',
                'has_verified'       => '1',
                'has_linked'         => '0',
            ),
        );
        $database->row_results = array(
            null,
            array(
                'id'                     => '01ARZ3NDEKTSV4RRFFQ69G5FBE',
                'workflow_id'            => $this->workflow_row()['id'],
                'signal_source'          => 'site_observed',
                'requested_metrics_json' => null,
                'measured_context_json'  => '{"out_of_stock_match_count":{"value":1,"status":"verified"}}',
                'occurred_at'            => '2026-08-31 15:00:00',
            ),
        );

        $item = (new SignalService($database))->grouped(str_repeat('a', 64))['items'][0];

        self::assertSame(array('value' => 1, 'status' => 'verified'), $item['measured_context']['out_of_stock_match_count']);
        self::assertSame('single_workflow', $item['measurement_scope']['kind']);
        self::assertSame('site_observed', $item['measurement_scope']['source']);
        self::assertSame('2026-08-31 15:00:00', $item['measurement_scope']['occurred_at']);
    }

    public function test_group_measurement_honors_source_and_date_filters(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets[] = array(
            array(
                'signal_key'         => str_repeat('9', 64),
                'signal_category'    => 'demand_gap',
                'signal_code'        => 'low_coverage',
                'signal_title'       => 'Filtered demand',
                'status'             => 'open',
                'observed_count'     => '1',
                'feedback_count'     => '0',
                'affected_workflows' => '1',
                'distinct_sessions'  => '1',
                'related_product_id' => null,
                'suggested_action'   => 'improve_product_coverage',
                'latest_occurrence'  => '2026-08-15 12:00:00',
                'has_verified'       => '1',
                'has_linked'         => '0',
            ),
        );
        $database->row_results[] = array(
            'id'                     => '01ARZ3NDEKTSV4RRFFQ69G5FBF',
            'workflow_id'            => $this->workflow_row()['id'],
            'signal_source'          => 'site_observed',
            'requested_metrics_json' => null,
            'measured_context_json'  => '{"eligible_product_count":{"value":2,"status":"verified"}}',
            'occurred_at'            => '2026-08-15 12:00:00',
        );

        $result = (new SignalService($database))->grouped(
            str_repeat('a', 64),
            array(
                'source'    => 'site_observed',
                'date_from' => '2026-08-01',
                'date_to'   => '2026-08-31',
            )
        );

        self::assertSame('site_observed', $result['items'][0]['measurement_scope']['source']);
        self::assertStringContainsString("signal_source = 'site_observed'", $database->queries[1]['sql']);
        self::assertStringNotContainsString("signal_source = 'agent_reported'", $database->queries[1]['sql']);
        self::assertContains('2026-08-01 00:00:00', $database->queries[1]['args']);
        self::assertContains('2026-09-01 00:00:00', $database->queries[1]['args']);
    }

    public function test_legacy_capability_groups_stay_separate_and_agent_reported(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets[] = array(
            array(
                'signal_key'            => null,
                'signal_category'       => 'capability_gap',
                'signal_code'           => 'back_in_stock_notification',
                'signal_title'          => 'Back in stock notification requested',
                'status'                => 'open',
                'observed_count'        => '0',
                'feedback_count'        => '2',
                'affected_workflows'    => '2',
                'distinct_sessions'     => '1',
                'related_product_id'    => '18',
                'measured_context_json' => null,
                'suggested_action'      => 'add_capability',
                'latest_occurrence'     => '2026-08-31 12:00:00',
                'has_verified'          => '0',
                'has_linked'            => '0',
            ),
            array(
                'signal_key'            => null,
                'signal_category'       => 'capability_gap',
                'signal_code'           => 'product_reservation',
                'signal_title'          => 'Product reservation requested',
                'status'                => 'open',
                'observed_count'        => '0',
                'feedback_count'        => '1',
                'affected_workflows'    => '1',
                'distinct_sessions'     => '1',
                'related_product_id'    => null,
                'measured_context_json' => null,
                'suggested_action'      => 'add_capability',
                'latest_occurrence'     => '2026-08-31 11:00:00',
                'has_verified'          => '0',
                'has_linked'            => '0',
            ),
        );

        $result = (new SignalService($database))->grouped(str_repeat('a', 64));

        self::assertCount(2, $result['items']);
        self::assertTrue($result['items'][0]['sources']['agent_reported']);
        self::assertSame(2, $result['items'][0]['feedback_count']);
        self::assertStringContainsString('CASE WHEN g.signal_key IS NULL', $database->queries[0]['sql']);
    }

    public function test_summary_applies_dates_and_discloses_unsupported_tool_filter(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->row_results[] = array('observed' => '2', 'agent_reported' => '1', 'workflows' => '2', 'categories' => '1');
        $service = new SignalService($database);

        $dated = $service->summary(
            str_repeat('a', 64),
            array('date_from' => '2026-08-01 00:00:00', 'date_to' => '2026-09-01 00:00:00', 'tool_name' => null)
        );
        $tool_filtered = $service->summary(str_repeat('a', 64), array('tool_name' => 'search_products'));

        self::assertTrue($dated['available']);
        self::assertTrue($dated['date_filter_applied']);
        self::assertContains('2026-08-01 00:00:00', $database->queries[0]['args']);
        self::assertContains('2026-09-01 00:00:00', $database->queries[0]['args']);
        self::assertFalse($tool_filtered['available']);
        self::assertSame('not_available_for_tool_filter', $tool_filtered['reason']);
        self::assertNull($tool_filtered['observed']);
        self::assertFalse($dated['tool_filter_applied']);
    }

    private function database_with_workflow(): AnalyticsDatabaseDouble
    {
        $database = new AnalyticsDatabaseDouble();
        $workflow = $this->workflow_row();
        $database->workflows[$workflow['id']] = $workflow;

        return $database;
    }

    private function service(AnalyticsDatabaseDouble $database): SignalService
    {
        return new SignalService(
            $database,
            null,
            null,
            null,
            static fn (): string => '2026-08-31 12:05:00',
            static fn (): string => self::FEEDBACK_ID
        );
    }

    private function add_search_event(AnalyticsDatabaseDouble $database): void
    {
        $database->events[] = array(
            'id'               => 1,
            'event_id'         => self::EVENT,
            'workflow_id'      => $this->workflow_row()['id'],
            'event_name'       => EventName::PRODUCT_SEARCHED,
            'request_id'       => self::REQUEST,
            'step_number'      => 1,
            'tool_name'        => 'search_products',
            'tool_version'     => '1.0.0',
            'risk_class'       => 'read',
            'outcome'          => null,
            'duration_ms'      => null,
            'error_code'       => null,
            'http_status'      => null,
            'product_ids_json' => '[18,21]',
            'currency'         => null,
            'value'            => null,
            'properties_json'  => '{"result_count":2,"highest_water_rating":"IPX4"}',
            'dedupe_key'       => str_repeat('d', 64),
            'occurred_at'      => '2026-08-31 12:00:00',
            'created_at'       => '2026-08-31 12:00:00',
        );
    }

    /** @return array<string, mixed> */
    private function feedback_input(): array
    {
        return array(
            'feedback_type'         => 'constraint_encountered',
            'outcome'               => 'partial',
            'step'                  => 'discovery',
            'reason_code'           => 'low_coverage',
            'ratings'               => array('evidence_quality' => 'sufficient', 'effort' => 'low'),
            'evidence_event_ids'    => array(self::EVENT),
            'requested_metrics'     => array('eligible_product_count', 'highest_matching_water_rating', 'search_refinement_count'),
            'suggested_owner_action' => 'improve_product_coverage',
        );
    }
}
