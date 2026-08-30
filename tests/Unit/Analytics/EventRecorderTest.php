<?php

/**
 * Event recorder integrity tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Analytics;

use WPWebMCP\AgentOps\Analytics\EventRecorder;
use WPWebMCP\AgentOps\Contract\EventName;
use WPWebMCP\AgentOps\Contract\RiskClass;

require_once __DIR__ . '/AnalyticsTestCase.php';

final class EventRecorderTest extends AnalyticsTestCase
{
    public function test_start_and_terminal_are_idempotent_and_terminal_outcome_is_authoritative(): void
    {
        $database   = new AnalyticsDatabaseDouble();
        $workflow   = $this->workflow_row();
        $database->workflows[$workflow['id']] = $workflow;
        $event_ids = array(
            'evt_01ARZ3NDEKTSV4RRFFQ69G5FAB',
            'evt_01ARZ3NDEKTSV4RRFFQ69G5FAC',
            'evt_01ARZ3NDEKTSV4RRFFQ69G5FAD',
        );
        $recorder = new EventRecorder(
            $database,
            null,
            static fn (): string => '2026-08-29 20:00:01',
            static function () use (&$event_ids): string {
                return array_shift($event_ids);
            },
            static fn (): int => 123456789
        );
        $request_id = '550e8400-e29b-41d4-a716-446655440000';
        $tool = array('name' => 'search_products', 'version' => '1.0.0', 'risk_class' => RiskClass::READ);

        $first_start = $recorder->start($workflow['id'], $request_id, $tool, array('quantity' => 1, 'prompt' => 'do not save'));
        $replay_start = $recorder->start($workflow['id'], $request_id, $tool);
        self::assertFalse($first_start['replayed']);
        self::assertTrue($replay_start['replayed']);
        self::assertSame($first_start['event_id'], $replay_start['event_id']);
        self::assertSame(1, $database->workflows[$workflow['id']]['tool_count']);

        $failed = $recorder->terminal(
            $workflow['id'],
            $request_id,
            EventName::TOOL_CALL_FAILED,
            array('duration_ms' => 42, 'error_code' => 'catalog_unavailable', 'http_status' => 503)
        );
        $late_success = $recorder->terminal(
            $workflow['id'],
            $request_id,
            EventName::TOOL_CALL_SUCCEEDED,
            array('duration_ms' => 50, 'http_status' => 200)
        );

        self::assertFalse($failed['replayed']);
        self::assertTrue($late_success['replayed']);
        self::assertSame(EventName::TOOL_CALL_FAILED, $late_success['event_name']);
        self::assertSame('catalog_unavailable', $late_success['error_code']);
        self::assertCount(2, $database->events);
        self::assertStringNotContainsString('prompt', (string) $database->events[0]['properties_json']);
    }

    public function test_start_replay_exposes_prior_terminal_state(): void
    {
        $database = new AnalyticsDatabaseDouble();
        $workflow = $this->workflow_row();
        $database->workflows[$workflow['id']] = $workflow;
        $event_ids = array('evt_01ARZ3NDEKTSV4RRFFQ69G5FAB', 'evt_01ARZ3NDEKTSV4RRFFQ69G5FAC');
        $recorder = new EventRecorder(
            $database,
            null,
            static fn (): string => '2026-08-29 20:00:01',
            static function () use (&$event_ids): string {
                return array_shift($event_ids);
            }
        );
        $request_id = '550e8400-e29b-41d4-a716-446655440000';
        $tool = array('name' => 'get_cart', 'version' => '1.0.0', 'risk_class' => RiskClass::READ);

        $recorder->start($workflow['id'], $request_id, $tool);
        $recorder->terminal($workflow['id'], $request_id, EventName::TOOL_CALL_SUCCEEDED, array('http_status' => 200));
        $replay = $recorder->start($workflow['id'], $request_id, $tool);

        self::assertSame(EventName::TOOL_CALL_SUCCEEDED, $replay['terminal']['event_name']);
        self::assertTrue($replay['terminal']['replayed']);
    }
}
