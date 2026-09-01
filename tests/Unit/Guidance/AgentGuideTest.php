<?php

/**
 * Agent-guide discovery and feedback-trigger tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Guidance;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentOps\Contract\ToolName;
use WPWebMCP\AgentOps\Contract\Versions;
use WPWebMCP\AgentOps\Guidance\AgentGuide;

final class AgentGuideTest extends TestCase
{
    public function test_guide_is_a_versioned_start_here_contract_with_human_and_privacy_boundaries(): void
    {
        $guide = (new AgentGuide())->guide();

        self::assertSame(Versions::AGENT_GUIDE, $guide['version']);
        self::assertTrue($guide['start_here']);
        self::assertSame(ToolName::REPORT_AGENT_FEEDBACK, $guide['feedback']['tool']);
        self::assertSame(2, $guide['feedback']['max_reports_per_workflow']);
        self::assertContains('human_handoff', $guide['feedback']['recommended_when']);
        self::assertContains('raw_prompt', $guide['privacy']['excluded']);
        self::assertContains('payment_data', $guide['privacy']['excluded']);
        self::assertContains('structured_feedback', $guide['privacy']['stored']);

        $journey = $guide['supported_journeys'][0];
        self::assertSame('evidence_first_purchase', $journey['id']);
        self::assertSame('human_checkpoint', $journey['steps'][4]['effect']);
        self::assertTrue($journey['steps'][4]['human_required']);
        self::assertContains('place_order', $guide['human_boundaries'][0]['human_must']);
    }

    public function test_discovery_points_to_the_start_here_tool(): void
    {
        $discovery = (new AgentGuide())->discovery();

        self::assertSame(
            array(
                'available'  => true,
                'tool'       => ToolName::GET_AGENT_GUIDE,
                'version'    => Versions::AGENT_GUIDE,
                'start_here' => true,
            ),
            $discovery
        );
    }

    public function test_zero_results_and_handoff_emit_bounded_contextual_actions(): void
    {
        $guide    = new AgentGuide();
        $event_id = 'evt_01ARZ3NDEKTSV4RRFFQ69G5FAV';

        $zero = $guide->next_actions(ToolName::SEARCH_PRODUCTS, array('result_count' => 0), $event_id);
        self::assertCount(1, $zero);
        self::assertSame(ToolName::REPORT_AGENT_FEEDBACK, $zero[0]['tool']);
        self::assertSame('zero_results', $zero[0]['reason']);
        self::assertSame(array($event_id), $zero[0]['evidence_event_ids']);
        self::assertArrayHasKey('requested_metrics', $zero[0]['suggested_input']);
        self::assertArrayNotHasKey('metric_values', $zero[0]['suggested_input']);

        self::assertSame(array(), $guide->next_actions(ToolName::GET_STOREFRONT_CONTEXT, array(), $event_id));
        self::assertSame(array(), $guide->next_actions(ToolName::SEARCH_PRODUCTS, array('result_count' => 2), $event_id));
        self::assertSame(array(), $guide->next_actions(ToolName::SEARCH_PRODUCTS, array('result_count' => 3), $event_id));
        self::assertSame(array(), $guide->next_actions(ToolName::COMPARE_PRODUCTS, array('missing_facts' => array()), $event_id));

        $handoff = $guide->next_actions(ToolName::CHECKOUT_HANDOFF, array('checkout_ready' => true), $event_id);
        self::assertCount(1, $handoff);
        self::assertSame('smooth_handoff', $handoff[0]['reason']);
    }

    public function test_guide_and_feedback_do_not_recursively_suggest_more_feedback(): void
    {
        $guide    = new AgentGuide();
        $event_id = 'evt_01ARZ3NDEKTSV4RRFFQ69G5FAV';

        self::assertSame(array(), $guide->next_actions(ToolName::GET_AGENT_GUIDE, array(), $event_id));
        self::assertSame(array(), $guide->next_actions(ToolName::REPORT_AGENT_FEEDBACK, array(), $event_id));
    }
}
