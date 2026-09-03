<?php

/**
 * Full-set catalog analysis and bounded out-of-stock fallback tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentSNR\WooCommerce\ProductCatalog;
use WPWebMCP\AgentSNR\WooCommerce\ProductNormalizer;
use WPWebMCP\AgentSNR\WooCommerce\ProductSearchMatcher;

require_once __DIR__ . '/CatalogProductDouble.php';

final class ProductCatalogAnalysisTest extends TestCase
{
    public function test_analysis_uses_every_match_before_public_limit_slice(): void
    {
        $products = array();
        for ($index = 1; $index <= 10; ++$index) {
            $products[] = new CatalogProductDouble(
                $index,
                'Waterproof Pack ' . $index,
                50 + $index,
                7 === $index ? 'IPX7' : 'IPX4',
                'instock'
            );
        }
        $catalog = $this->catalog($products);

        $search = $catalog->search_with_analysis(
            array('query' => 'waterproof', 'in_stock_only' => true, 'limit' => 1)
        );

        self::assertCount(1, $search['result']['products']);
        self::assertSame(10, $search['result']['result_count']);
        self::assertSame(10, $search['analysis']['eligible_product_count']);
        self::assertSame(10, $search['analysis']['in_stock_match_count']);
        self::assertSame('IPX7', $search['analysis']['highest_matching_water_rating']);
    }

    public function test_zero_in_stock_search_runs_one_secondary_scan_for_inventory_match(): void
    {
        $queries  = array();
        $products = array(new CatalogProductDouble(25, 'TerraRoll 25 Pack', 99, 'IPX6', 'outofstock'));
        $catalog  = new ProductCatalog(
            new ProductNormalizer(),
            new ProductSearchMatcher(),
            static function (array $arguments) use (&$queries, $products): array {
                $queries[] = $arguments;

                return isset($arguments['stock_status']) ? array() : $products;
            }
        );

        $search = $catalog->search_with_analysis(
            array('query' => 'TerraRoll', 'in_stock_only' => true, 'limit' => 6)
        );

        self::assertSame(0, $search['result']['result_count']);
        self::assertSame(1, $search['analysis']['out_of_stock_match_count']);
        self::assertSame('IPX6', $search['analysis']['highest_matching_water_rating']);
        self::assertSame(array(25), $search['analysis']['related_product_ids']);
        self::assertSame('TerraRoll 25 Pack', $search['analysis']['related_product_title']);
        self::assertSame(array(), $search['result']['products'], 'Internal OOS evidence must not leak into the public result.');
        self::assertCount(2, $queries, 'Only a zero-result in-stock search should receive the secondary scan.');
        self::assertSame('instock', $queries[0]['stock_status']);
        self::assertArrayNotHasKey('stock_status', $queries[1]);
    }

    /** @param list<CatalogProductDouble> $products */
    private function catalog(array $products): ProductCatalog
    {
        return new ProductCatalog(
            new ProductNormalizer(),
            new ProductSearchMatcher(),
            static function (array $arguments) use ($products): array {
                if (! isset($arguments['stock_status'])) {
                    return $products;
                }

                return array_values(
                    array_filter(
                        $products,
                        static fn (CatalogProductDouble $product): bool => $arguments['stock_status'] === $product->get_stock_status()
                    )
                );
            }
        );
    }
}
