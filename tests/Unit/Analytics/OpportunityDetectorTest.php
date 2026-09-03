<?php

/**
 * Deterministic opportunity-detection tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Analytics;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentSNR\Analytics\OpportunityDetector;

final class OpportunityDetectorTest extends TestCase
{
    public function test_zero_result_search_is_a_site_observed_demand_gap(): void
    {
        $detected = (new OpportunityDetector())->search(
            array(
                'query'         => 'waterproof backpack',
                'max_price'     => 100,
                'attributes'    => array('water_rating' => 'IPX5'),
                'in_stock_only' => true,
            ),
            array('products' => array(), 'result_count' => 0)
        );

        self::assertSame(0, $detected['metrics']['eligible_product_count']);
        self::assertNull($detected['metrics']['highest_matching_water_rating']);
        self::assertSame('demand_gap', $detected['signals'][0]['category']);
        self::assertSame('zero_results', $detected['signals'][0]['code']);
        self::assertSame('improve_product_coverage', $detected['signals'][0]['action']);
    }

    public function test_constrained_two_result_search_records_low_coverage_and_site_metrics(): void
    {
        $detected = (new OpportunityDetector())->search(
            array('query' => 'waterproof backpack', 'max_price' => 100, 'in_stock_only' => true),
            array(
                'result_count' => 2,
                'products'     => array(
                    $this->product('RainTrail 20 Pack', 'IPX4', 'instock', true),
                    $this->product('HarborLite 16 Pack', 'IPX4', 'instock', true),
                ),
            )
        );

        self::assertSame(2, $detected['metrics']['eligible_product_count']);
        self::assertSame('IPX4', $detected['metrics']['highest_matching_water_rating']);
        self::assertSame(2, $detected['metrics']['in_stock_match_count']);
        self::assertSame('low_coverage', $detected['signals'][0]['code']);
    }

    public function test_out_of_stock_match_is_an_inventory_signal(): void
    {
        $detected = (new OpportunityDetector())->search(
            array('query' => 'TerraRoll', 'in_stock_only' => false),
            array(
                'result_count' => 1,
                'products'     => array($this->product('TerraRoll 25 Pack', 'IPX4', 'outofstock', false)),
            )
        );

        self::assertSame(1, $detected['metrics']['out_of_stock_match_count']);
        self::assertContains('out_of_stock_match', array_column($detected['signals'], 'code'));
    }

    public function test_catalog_analysis_wins_over_the_truncated_public_page(): void
    {
        $detected = (new OpportunityDetector())->search(
            array('query' => 'waterproof', 'max_price' => 200),
            array(
                'result_count' => 6,
                'products'     => array($this->product('First result', 'IPX4', 'instock', true)),
            ),
            array(
                'eligible_product_count'         => 6,
                'highest_matching_water_rating' => 'IPX7',
                'in_stock_match_count'           => 5,
                'out_of_stock_match_count'       => 1,
            )
        );

        self::assertSame(6, $detected['metrics']['eligible_product_count']);
        self::assertSame('IPX7', $detected['metrics']['highest_matching_water_rating']);
        self::assertSame(5, $detected['metrics']['in_stock_match_count']);
        self::assertSame(1, $detected['metrics']['out_of_stock_match_count']);
    }

    public function test_secondary_out_of_stock_match_replaces_zero_result_demand_gap(): void
    {
        $detected = (new OpportunityDetector())->search(
            array('query' => 'TerraRoll', 'max_price' => 120, 'in_stock_only' => true),
            array('result_count' => 0, 'products' => array()),
            array(
                'eligible_product_count'         => 0,
                'highest_matching_water_rating' => 'IPX6',
                'in_stock_match_count'           => 0,
                'out_of_stock_match_count'       => 1,
                'related_product_title'          => 'TerraRoll 25 Pack',
            )
        );

        self::assertSame(array('out_of_stock_match'), array_column($detected['signals'], 'code'));
        self::assertNotContains('zero_results', array_column($detected['signals'], 'code'));
        self::assertNotContains('low_coverage', array_column($detected['signals'], 'code'));
        self::assertSame('TerraRoll 25 Pack · out of stock', $detected['demand']['title']);
    }

    public function test_exact_multiword_query_without_structured_constraints_is_not_low_coverage(): void
    {
        $detected = (new OpportunityDetector())->search(
            array('query' => 'HarborLite 16'),
            array(
                'result_count' => 1,
                'products'     => array($this->product('HarborLite 16 Pack', 'IPX4', 'instock', true)),
            )
        );

        self::assertSame(array(), $detected['signals']);
    }

    public function test_missing_comparison_facts_are_experience_friction(): void
    {
        $signals = (new OpportunityDetector())->comparison(
            array(
                'missing_facts' => array(
                    array('product_id' => 10, 'criterion' => 'weight'),
                    array('product_id' => 11, 'criterion' => 'laptop_size'),
                ),
            )
        );

        self::assertCount(1, $signals);
        self::assertSame('experience_friction', $signals[0]['category']);
        self::assertSame('missing_product_data', $signals[0]['code']);
        self::assertSame(2, $signals[0]['metrics']['missing_fact_count']);
        self::assertSame(array(), (new OpportunityDetector())->comparison(array('missing_facts' => array())));
    }

    /** @return array<string, mixed> */
    private function product(string $name, string $rating, string $stock, bool $purchasable): array
    {
        return array(
            'name'         => $name,
            'stock_status' => $stock,
            'purchasable'  => $purchasable,
            'attributes'   => array('water_rating' => $rating),
        );
    }
}
