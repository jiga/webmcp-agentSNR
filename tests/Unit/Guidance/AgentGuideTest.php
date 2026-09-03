<?php

/**
 * Agent-guide discovery and feedback-trigger tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Guidance;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentSNR\Abilities\ToolCatalog;
use WPWebMCP\AgentSNR\Contract\ToolName;
use WPWebMCP\AgentSNR\Contract\Versions;
use WPWebMCP\AgentSNR\Guidance\AgentGuide;

final class AgentGuideTest extends TestCase
{
    public function test_guide_is_a_versioned_start_here_contract_with_human_and_privacy_boundaries(): void
    {
        $guide = (new AgentGuide())->guide();

        self::assertSame(Versions::AGENT_GUIDE, $guide['version']);
        self::assertTrue($guide['start_here']);
        self::assertSame('top_level_co_browsing', $guide['execution']['supported_mode']);
        self::assertSame('unsupported', $guide['execution']['unattended_remote_execution']);
        self::assertSame(ToolName::REPORT_AGENT_FEEDBACK, $guide['feedback']['tool']);
        self::assertTrue($guide['feedback']['optional']);
        self::assertSame(2, $guide['feedback']['max_reports_per_workflow']);
        self::assertContains('human_handoff', $guide['feedback']['recommended_when']);
        self::assertContains('raw_prompt', $guide['privacy']['excluded']);
        self::assertContains('payment_data', $guide['privacy']['excluded']);
        self::assertContains('structured_feedback', $guide['privacy']['stored']);

        self::assertSame(
            array('evidence_first_purchase', 'agent_outcome_monitoring'),
            array_column($guide['supported_journeys'], 'id')
        );

        $shopper = $guide['supported_journeys'][0];
        self::assertSame('evidence_first_purchase', $shopper['id']);
        self::assertSame(
            array('understand', 'discover', 'evaluate', 'verify_policy', 'prepare_cart', 'handoff', 'feedback'),
            array_column($shopper['steps'], 'id')
        );
        self::assertSame('human_checkpoint', $shopper['steps'][5]['effect']);
        self::assertSame(array(ToolName::CHECKOUT_HANDOFF), $shopper['steps'][5]['tools']);
        self::assertTrue($shopper['steps'][5]['human_required']);
        self::assertTrue($shopper['steps'][6]['optional']);

        $operator = $guide['supported_journeys'][1];
        self::assertSame(
            array('monitor', 'investigate', 'diagnose', 'govern'),
            array_column($operator['steps'], 'id')
        );
        self::assertContains(ToolName::GET_OPPORTUNITY_SIGNALS, $operator['steps'][1]['tools']);
        self::assertSame(array(ToolName::SET_TOOL_ENABLED), $operator['steps'][3]['tools']);

        self::assertSame('untrusted', $guide['trust']['merchant_content']);
        self::assertSame('agent_reported', $guide['trust']['agent_feedback']);
        self::assertSame('site_computed', $guide['trust']['site_metrics']);
        self::assertSame(
            array('answer', 'action', 'telemetry', 'human_checkpoint', 'sensitive_action'),
            array_column($guide['trust']['effects'], 'id')
        );
        $defined_effects = array_column($guide['trust']['effects'], 'id');
        foreach ($guide['supported_journeys'] as $journey) {
            foreach ($journey['steps'] as $step) {
                self::assertContains($step['effect'], $defined_effects, 'Every journey effect must have a trust definition.');
            }
        }
        self::assertSame(0, $guide['sensitive_actions']['tool_count']);
        self::assertSame(array(), $guide['sensitive_actions']['tools']);
        self::assertContains('place_order', $guide['sensitive_actions']['human_owned']);
        self::assertContains('refund_order', $guide['sensitive_actions']['human_owned']);
        self::assertSame('cart_subtotal_or_estimate', $guide['pricing_boundary']['before_checkout']);
        self::assertSame('human_checkout', $guide['pricing_boundary']['final_total_at']);
        self::assertContains('shipping', $guide['pricing_boundary']['may_include']);
        self::assertStringContainsString('final all-in total', $guide['pricing_boundary']['agent_rule']);
        self::assertContains('place_order', $guide['human_boundaries'][0]['human_must']);
        self::assertSame(array(ToolName::CHECKOUT_HANDOFF), $guide['human_boundaries'][0]['agent_may']);
        self::assertLessThanOrEqual(8192, strlen((string) json_encode($guide)));
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

    public function test_guide_matches_the_strict_catalog_output_schema(): void
    {
        $definition = (new ToolCatalog())->find(ToolName::GET_AGENT_GUIDE);

        self::assertNotNull($definition);
        $this->assert_schema_shape($definition['output_schema'], (new AgentGuide())->guide(), '$');
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

    /**
     * Verify the closed object/array shape and scalar types used by the guide.
     *
     * @param array<string, mixed> $schema JSON Schema fragment.
     * @param mixed                $value Guide value.
     */
    private function assert_schema_shape(array $schema, $value, string $path): void
    {
        $type = $schema['type'] ?? null;
        if ('object' === $type) {
            self::assertIsArray($value, $path . ' must be an object.');
            foreach ($schema['required'] ?? array() as $required) {
                self::assertArrayHasKey($required, $value, $path . ' is missing ' . $required);
            }

            $properties = $schema['properties'] ?? array();
            if (false === ($schema['additionalProperties'] ?? true)) {
                self::assertSame(
                    array(),
                    array_values(array_diff(array_keys($value), array_keys($properties))),
                    $path . ' contains properties outside the closed schema.'
                );
            }

            foreach ($properties as $name => $property_schema) {
                if (array_key_exists($name, $value) && is_array($property_schema)) {
                    $this->assert_schema_shape($property_schema, $value[$name], $path . '.' . $name);
                }
            }

            return;
        }

        if ('array' === $type) {
            self::assertIsArray($value, $path . ' must be an array.');
            if (isset($schema['minItems'])) {
                self::assertGreaterThanOrEqual((int) $schema['minItems'], count($value), $path);
            }
            if (isset($schema['maxItems'])) {
                self::assertLessThanOrEqual((int) $schema['maxItems'], count($value), $path);
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $index => $item) {
                    $this->assert_schema_shape($schema['items'], $item, $path . '[' . $index . ']');
                }
            }

            return;
        }

        if ('string' === $type) {
            self::assertIsString($value, $path . ' must be a string.');
        } elseif ('boolean' === $type) {
            self::assertIsBool($value, $path . ' must be a boolean.');
        } elseif ('integer' === $type) {
            self::assertIsInt($value, $path . ' must be an integer.');
        }

        if (isset($schema['enum'])) {
            self::assertContains($value, $schema['enum'], $path . ' must use a declared enum value.');
        }
    }
}
