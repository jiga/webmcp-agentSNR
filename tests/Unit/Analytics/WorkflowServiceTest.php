<?php

/**
 * Workflow lifecycle tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Analytics;

use WPWebMCP\AgentSNR\Analytics\WorkflowService;
use WPWebMCP\AgentSNR\Contract\EventName;

require_once __DIR__ . '/AnalyticsTestCase.php';

final class WorkflowServiceTest extends AnalyticsTestCase
{
    public function test_current_reuses_by_session_and_keeps_agentsnr_audit_separate(): void
    {
        $database = new AnalyticsDatabaseDouble();
        $ids = array('01ARZ3NDEKTSV4RRFFQ69G5FAB', '01ARZ3NDEKTSV4RRFFQ69G5FAC');
        $service = new WorkflowService(
            $database,
            null,
            null,
            static fn (): string => '2026-08-29 20:00:01',
            static function () use (&$ids): string {
                return array_shift($ids);
            },
            static fn (): string => '01ARZ3NDEKTSV4RRFFQ69G5FAA'
        );
        $session_hash = str_repeat('a', 64);

        $first = $service->current($session_hash, 'storefront');
        $reused = $service->current($session_hash, 'storefront');
        $audit = $service->current($session_hash, 'agentsnr');

        self::assertFalse($first['reused']);
        self::assertTrue($reused['reused']);
        self::assertSame($first['id'], $reused['id']);
        self::assertNotSame($first['id'], $audit['id']);
        self::assertSame('agentsnr', $audit['surface']);
        self::assertArrayNotHasKey('demo_session_hash', $first);
        self::assertArrayNotHasKey('wc_session_hash', $first);
        self::assertCount(2, $database->workflows);
        self::assertCount(
            2,
            array_filter(
                $database->events,
                static fn (array $event): bool => EventName::WORKFLOW_STARTED === $event['event_name']
            )
        );
    }

    public function test_completion_is_deterministic_and_cannot_be_overwritten_by_abandonment(): void
    {
        $database = new AnalyticsDatabaseDouble();
        $workflow = $this->workflow_row();
        $database->workflows[$workflow['id']] = $workflow;
        $service = new WorkflowService(
            $database,
            null,
            null,
            static fn (): string => '2026-08-29 20:30:00',
            null,
            static fn (): string => '01ARZ3NDEKTSV4RRFFQ69G5FAA'
        );

        $completed = $service->complete($workflow['id']);
        $late_abandon = $service->abandon($workflow['id']);

        self::assertSame('completed', $completed['status']);
        self::assertFalse($completed['reused']);
        self::assertSame('completed', $late_abandon['status']);
        self::assertTrue($late_abandon['reused']);
        self::assertCount(
            1,
            array_filter(
                $database->events,
                static fn (array $event): bool => EventName::WORKFLOW_COMPLETED === $event['event_name']
            )
        );
    }
}
