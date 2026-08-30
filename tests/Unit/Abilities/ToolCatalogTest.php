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

        self::assertCount(19, $definitions);
        self::assertSame(array_keys($definitions), array_values(array_unique(array_keys($definitions))));

        foreach ($definitions as $name => $definition) {
            self::assertMatchesRegularExpression('/\A[A-Za-z0-9_.-]{1,128}\z/', $name);
            self::assertSame($name, $definition['name']);
            self::assertMatchesRegularExpression(
                '/\A[a-z0-9-]+\/[a-z0-9-]+\z/',
                $definition['ability_id']
            );
            self::assertLessThanOrEqual(500, strlen((string) $definition['description']));
            self::assertContains($definition['risk_class'], RiskClass::all());
            self::assertSame('object', $definition['input_schema']['type']);
            self::assertFalse($definition['input_schema']['additionalProperties']);
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
        self::assertSame(array('expected_cart_revision'), $checkout['input_schema']['required']);
        self::assertArrayHasKey('expected_cart_revision', $checkout['input_schema']['properties']);
        self::assertArrayNotHasKey('acknowledge_human_review', $checkout['input_schema']['properties']);
    }

    public function test_session_policy_tool_cannot_request_site_scope(): void
    {
        $definition = (new ToolCatalog())->find(ToolName::SET_TOOL_ENABLED);

        self::assertNotNull($definition);
        self::assertSame(
            array('demo_session'),
            $definition['input_schema']['properties']['scope']['enum']
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
}
