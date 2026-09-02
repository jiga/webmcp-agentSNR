<?php

/**
 * Tool catalog contract tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Abilities;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentOps\Abilities\ToolCatalog;
use WPWebMCP\AgentOps\Contract\RiskClass;
use WPWebMCP\AgentOps\Contract\ToolName;

final class ToolCatalogTest extends TestCase
{
    public function test_catalog_has_unique_stable_tool_contracts(): void
    {
        $definitions = (new ToolCatalog())->all();

        self::assertCount(22, $definitions);
        self::assertSame(array_keys($definitions), array_values(array_unique(array_keys($definitions))));

        foreach ($definitions as $name => $definition) {
            self::assertMatchesRegularExpression('/\A[A-Za-z0-9_.-]{1,30}\z/', $name);
            self::assertSame($name, $definition['name']);
            self::assertMatchesRegularExpression(
                '/\A[a-z0-9-]+\/[a-z0-9-]+\z/',
                $definition['ability_id']
            );
            self::assertLessThanOrEqual(500, strlen((string) $definition['description']));
            self::assertIsBool($definition['discoverable']);
            self::assertContains($definition['risk_class'], RiskClass::all());
            self::assertSame('object', $definition['input_schema']['type']);
            self::assertFalse($definition['input_schema']['additionalProperties']);
            $this->assert_input_property_contracts($definition['input_schema']);
            self::assertLessThanOrEqual(8192, $definition['max_input_bytes']);
            self::assertLessThanOrEqual(8192, $definition['max_output_bytes']);
        }
    }

    public function test_surface_lists_match_public_constants(): void
    {
        $catalog = new ToolCatalog();

        self::assertSame(
            ToolName::storefront(),
            array_column($catalog->surface('storefront'), 'name')
        );
        self::assertSame(
            ToolName::agentops(),
            array_column($catalog->surface('agentops'), 'name')
        );
    }

    public function test_public_surfaces_hide_only_legacy_gap_abilities(): void
    {
        $catalog = new ToolCatalog();

        self::assertCount(13, $catalog->surface('storefront'));
        self::assertCount(12, $catalog->public_surface('storefront'));
        self::assertCount(9, $catalog->surface('agentops'));
        self::assertCount(8, $catalog->public_surface('agentops'));

        self::assertFalse($catalog->find(ToolName::REPORT_CAPABILITY_GAP)['discoverable']);
        self::assertFalse($catalog->find(ToolName::GET_CAPABILITY_GAPS)['discoverable']);
        self::assertNotContains(
            ToolName::REPORT_CAPABILITY_GAP,
            array_column($catalog->public_surface('storefront'), 'name')
        );
        self::assertNotContains(
            ToolName::GET_CAPABILITY_GAPS,
            array_column($catalog->public_surface('agentops'), 'name')
        );

        foreach ($catalog->public_surface('storefront') as $definition) {
            self::assertTrue($definition['discoverable']);
        }
        foreach ($catalog->public_surface('agentops') as $definition) {
            self::assertTrue($definition['discoverable']);
        }
    }

    public function test_explain_workflow_keeps_standard_output_ceiling(): void
    {
        $definitions = (new ToolCatalog())->all();

        self::assertSame(8192, $definitions[ToolName::EXPLAIN_WORKFLOW]['max_output_bytes']);
        foreach ($definitions as $definition) {
            self::assertSame(8192, $definition['max_output_bytes']);
        }
    }

    public function test_capability_gap_is_a_telemetry_write_and_checkout_uses_cart_revision(): void
    {
        $catalog  = new ToolCatalog();
        $gap      = $catalog->find(ToolName::REPORT_CAPABILITY_GAP);
        $add      = $catalog->find(ToolName::ADD_TO_CART);
        $checkout = $catalog->find(ToolName::CHECKOUT_HANDOFF);

        self::assertNotNull($gap);
        self::assertSame(RiskClass::TELEMETRY_WRITE, $gap['risk_class']);
        self::assertFalse($gap['read_only']);

        self::assertNotNull($add);
        self::assertContains('expected_cart_revision', $add['input_schema']['required']);
        self::assertArrayHasKey('expected_cart_revision', $add['input_schema']['properties']);

        self::assertNotNull($checkout);
        self::assertSame('prepare_checkout_handoff', $checkout['name']);
        self::assertNull($catalog->find('checkout_handoff'));
        self::assertSame(array('expected_cart_revision'), $checkout['input_schema']['required']);
        self::assertArrayHasKey('expected_cart_revision', $checkout['input_schema']['properties']);
        self::assertArrayNotHasKey('acknowledge_human_review', $checkout['input_schema']['properties']);
    }

    public function test_agent_guide_feedback_and_opportunity_signal_contracts_are_strict(): void
    {
        $catalog      = new ToolCatalog();
        $guide        = $catalog->find(ToolName::GET_AGENT_GUIDE);
        $feedback     = $catalog->find(ToolName::REPORT_AGENT_FEEDBACK);
        $opportunities = $catalog->find(ToolName::GET_OPPORTUNITY_SIGNALS);

        self::assertNotNull($guide);
        self::assertSame(RiskClass::READ, $guide['risk_class']);
        self::assertTrue($guide['read_only']);
        self::assertStringContainsStringIgnoringCase('start', (string) $guide['description']);
        self::assertFalse($guide['input_schema']['additionalProperties']);
        self::assertContains('version', $guide['output_schema']['required']);
        self::assertArrayHasKey('version', $guide['output_schema']['properties']);
        self::assertArrayNotHasKey('guide_version', $guide['output_schema']['properties']);
        self::assertArrayHasKey('execution', $guide['output_schema']['properties']);
        self::assertArrayHasKey('trust', $guide['output_schema']['properties']);
        self::assertArrayHasKey('sensitive_actions', $guide['output_schema']['properties']);
        self::assertArrayHasKey('pricing_boundary', $guide['output_schema']['properties']);
        $this->assert_strict_object_schemas($guide['output_schema']);

        self::assertNotNull($feedback);
        self::assertSame(RiskClass::TELEMETRY_WRITE, $feedback['risk_class']);
        self::assertFalse($feedback['read_only']);
        self::assertFalse($feedback['input_schema']['additionalProperties']);
        self::assertArrayHasKey('requested_metrics', $feedback['input_schema']['properties']);
        self::assertTrue($feedback['input_schema']['properties']['requested_metrics']['uniqueItems']);
        self::assertSame(5, $feedback['input_schema']['properties']['requested_metrics']['maxItems']);
        self::assertArrayHasKey('enum', $feedback['input_schema']['properties']['requested_metrics']['items']);
        self::assertArrayNotHasKey('comment', $feedback['input_schema']['properties']);
        self::assertArrayNotHasKey('user_goal', $feedback['input_schema']['properties']);
        self::assertArrayNotHasKey('metric_values', $feedback['input_schema']['properties']);
        self::assertArrayNotHasKey('measured_context', $feedback['input_schema']['properties']);
        self::assertArrayNotHasKey('trust', $feedback['input_schema']['properties']);
        self::assertArrayNotHasKey('workflow_id', $feedback['input_schema']['properties']);

        self::assertNotNull($opportunities);
        self::assertSame('agentops', $opportunities['surface']);
        self::assertSame(RiskClass::READ, $opportunities['risk_class']);
        self::assertTrue($opportunities['read_only']);
        self::assertFalse($opportunities['input_schema']['additionalProperties']);
        self::assertStringContainsString('One call returns unified', $opportunities['description']);
        self::assertStringContainsString(
            'omit it to include every signal category',
            $opportunities['input_schema']['properties']['category']['description']
        );
        self::assertStringContainsString(
            'omit it to include site-observed and agent-reported signals together',
            $opportunities['input_schema']['properties']['source']['description']
        );
        self::assertSame(
            array('site_observed', 'agent_reported'),
            $opportunities['input_schema']['properties']['source']['enum']
        );
        self::assertSame(8, $opportunities['input_schema']['properties']['limit']['maximum']);
        self::assertSame(8, $opportunities['input_schema']['properties']['limit']['default']);
        self::assertContains('truncated', $opportunities['output_schema']['required']);
    }

    public function test_tool_health_is_the_complete_single_call_for_slow_or_failing_tools(): void
    {
        $health = (new ToolCatalog())->find(ToolName::GET_TOOL_HEALTH);

        self::assertNotNull($health);
        self::assertStringContainsString('Use this one call', $health['description']);
        self::assertStringContainsString('slow or failing tools', $health['description']);
        self::assertStringContainsString('do not combine it with the overview', $health['description']);
    }

    public function test_session_policy_tool_cannot_request_site_scope(): void
    {
        $definition = (new ToolCatalog())->find(ToolName::SET_TOOL_ENABLED);

        self::assertNotNull($definition);
        self::assertStringContainsString('Do not call for permanent, sitewide, all-tools', $definition['description']);
        self::assertSame(
            array('demo_session'),
            $definition['input_schema']['properties']['scope']['enum']
        );
        self::assertSame(
            ToolName::storefrontPublic(),
            $definition['input_schema']['properties']['tool_name']['enum']
        );
        self::assertNotContains(
            ToolName::REPORT_CAPABILITY_GAP,
            $definition['input_schema']['properties']['tool_name']['enum']
        );
    }

    public function test_product_search_contract_separates_text_from_structured_filters(): void
    {
        $search = (new ToolCatalog())->find(ToolName::SEARCH_PRODUCTS);

        self::assertNotNull($search);
        self::assertStringStartsWith('Call once per constraint set', $search['description']);
        self::assertStringContainsString('wait for its result before refining', $search['description']);
        self::assertStringContainsString('attributes.water_rating', $search['description']);
        self::assertStringContainsString(
            '{"water_rating":"IPX5"}',
            $search['input_schema']['properties']['attributes']['description']
        );
    }

    public function test_empty_object_input_omits_array_shaped_properties(): void
    {
        $definition = (new ToolCatalog())->find(ToolName::GET_CART);

        self::assertNotNull($definition);
        self::assertArrayNotHasKey('properties', $definition['input_schema']);
        self::assertSame(
            '{"type":"object","additionalProperties":false}',
            json_encode($definition['input_schema'], JSON_UNESCAPED_SLASHES)
        );
    }

    /** @param array<string, mixed> $schema Input JSON Schema. */
    private function assert_input_property_contracts(array $schema): void
    {
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $property) {
                self::assertIsString($name);
                self::assertLessThanOrEqual(30, strlen($name), 'Input property name exceeds 30 characters.');
                self::assertIsArray($property);
                self::assertArrayHasKey('description', $property, 'Missing description for input property ' . $name);
                self::assertNotSame('', trim((string) $property['description']));
                self::assertLessThanOrEqual(
                    150,
                    strlen((string) $property['description']),
                    'Input property description exceeds 150 characters for ' . $name
                );
                $this->assert_input_property_contracts($property);
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $this->assert_input_property_contracts($schema['items']);
        }

        if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
            $this->assert_input_property_contracts($schema['additionalProperties']);
        }
    }

    /** @param array<string, mixed> $schema Output JSON Schema. */
    private function assert_strict_object_schemas(array $schema): void
    {
        if ('object' === ($schema['type'] ?? null)) {
            self::assertArrayHasKey('additionalProperties', $schema);
            self::assertFalse($schema['additionalProperties']);
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $property) {
                if (is_array($property)) {
                    $this->assert_strict_object_schemas($property);
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $this->assert_strict_object_schemas($schema['items']);
        }
    }
}
