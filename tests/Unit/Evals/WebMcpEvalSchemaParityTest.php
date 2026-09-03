<?php

/**
 * Public ToolCatalog to webmcp-evals schema parity.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Evals;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentSNR\Abilities\ToolCatalog;
use WPWebMCP\AgentSNR\Contract\ToolName;

final class WebMcpEvalSchemaParityTest extends TestCase
{
    public function test_storefront_eval_schema_matches_public_catalog(): void
    {
        $this->assert_surface_schema_matches_catalog('storefront', 12);
    }

    public function test_agentsnr_eval_schema_matches_public_catalog(): void
    {
        $this->assert_surface_schema_matches_catalog('agentsnr', 8);
    }

    private function assert_surface_schema_matches_catalog(string $surface, int $expected_count): void
    {
        $definitions = (new ToolCatalog())->public_surface($surface);
        $expected    = array(
            'tools' => array_map(
                static function (array $definition): array {
                    return array(
                        'name'         => (string) $definition['name'],
                        'description'  => (string) $definition['description'],
                        'inputSchema'  => $definition['input_schema'],
                        'outputSchema' => $definition['output_schema'],
                    );
                },
                $definitions
            ),
        );

        $path = dirname(__DIR__, 3) . '/evals/schemas/' . $surface . '-tools.json';
        self::assertFileIsReadable($path);

        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $actual = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount($expected_count, $expected['tools']);
        self::assertSame($expected, $actual, 'Regenerate eval schemas from the public ToolCatalog.');

        $names = array_column($actual['tools'], 'name');
        self::assertNotContains(ToolName::REPORT_CAPABILITY_GAP, $names);
        self::assertNotContains(ToolName::GET_CAPABILITY_GAPS, $names);

        if ('storefront' === $surface) {
            self::assertContains('prepare_checkout_handoff', $names);
            self::assertNotContains('checkout_handoff', $names);
        }
    }
}
