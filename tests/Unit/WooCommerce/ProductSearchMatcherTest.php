<?php

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentOps\WooCommerce\ProductCatalog;
use WPWebMCP\AgentOps\WooCommerce\ProductSearchMatcher;

final class ProductSearchMatcherTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $facts;

    protected function setUp(): void
    {
        $this->facts = array(
            'price'        => 109.0,
            '_search_text' => 'AlpineFlow 24 Pack waterproof backpack IPX6 24 liter recycled nylon',
            '_category_names' => array('Backpacks'),
            'attributes'   => array(
                'water_rating'    => 'IPX6',
                'capacity_liters' => 24.0,
                'material'        => 'Recycled nylon',
            ),
        );
    }

    public function test_matches_all_text_terms_and_price_range(): void
    {
        $matcher = new ProductSearchMatcher();

        self::assertTrue(
            $matcher->matches(
                $this->facts,
                array('query' => 'waterproof backpack', 'min_price' => 80, 'max_price' => 120)
            )
        );
    }

    public function test_rejects_missing_text_term(): void
    {
        self::assertFalse((new ProductSearchMatcher())->matches($this->facts, array('query' => 'waterproof suitcase')));
    }

    public function test_rejects_price_outside_inclusive_bounds(): void
    {
        $matcher = new ProductSearchMatcher();

        self::assertFalse($matcher->matches($this->facts, array('query' => 'backpack', 'max_price' => 108.99)));
        self::assertFalse($matcher->matches($this->facts, array('query' => 'backpack', 'min_price' => 109.01)));
        self::assertTrue($matcher->matches($this->facts, array('query' => 'backpack', 'min_price' => 109, 'max_price' => 109)));
    }

    public function test_filters_only_against_normalized_public_attributes(): void
    {
        $matcher = new ProductSearchMatcher();

        self::assertTrue($matcher->matches($this->facts, array('query' => 'pack', 'attributes' => array('Water Rating' => 'ipx6'))));
        self::assertFalse($matcher->matches($this->facts, array('query' => 'pack', 'attributes' => array('supplier_cost' => '20'))));
    }

    public function test_redundant_structured_filters_do_not_become_product_name_terms(): void
    {
        $matcher = new ProductSearchMatcher();
        $input   = array(
            'query'         => 'waterproof backpack under $120 with at least IPX6 protection in stock',
            'max_price'     => 120,
            'attributes'    => array('water_rating' => 'IPX6'),
            'in_stock_only' => true,
        );

        self::assertTrue($matcher->matches($this->facts, $input));
        self::assertFalse(
            $matcher->matches(
                $this->facts,
                array_merge(
                    $input,
                    array('query' => 'waterproof suitcase under $120 with at least IPX6 protection in stock')
                )
            ),
            'A real unmatched product term must remain significant.'
        );
        self::assertFalse(
            $matcher->matches(
                $this->facts,
                array_merge($input, array('attributes' => array('water_rating' => 'IPX7')))
            ),
            'Removing a repeated query value must not weaken the structured attribute filter.'
        );
    }

    public function test_text_price_bounds_cannot_be_weakened_by_structured_values(): void
    {
        $facts          = $this->facts;
        $facts['price'] = 75.0;
        $matcher        = new ProductSearchMatcher();

        self::assertFalse(
            $matcher->matches($facts, array('query' => 'backpack under $50', 'max_price' => 100)),
            'The stricter textual maximum must win.'
        );
        self::assertFalse(
            $matcher->matches($facts, array('query' => 'backpack over $100', 'min_price' => 50)),
            'The stricter textual minimum must win.'
        );
        self::assertFalse(
            $matcher->matches(
                $facts,
                array('query' => 'backpack at least $100 and under $50', 'min_price' => 25, 'max_price' => 125)
            ),
            'Conflicting effective bounds must fail closed.'
        );
        self::assertTrue(
            $matcher->matches($facts, array('query' => 'backpack under $100', 'max_price' => 120))
        );
        self::assertTrue(
            $matcher->matches($facts, array('query' => 'backpack over $50', 'min_price' => 25))
        );
    }

    public function test_public_query_is_strictly_published_and_visible(): void
    {
        $args = ProductCatalog::public_query_args(array('query' => 'pack', 'in_stock_only' => true));

        self::assertSame('publish', $args['status']);
        self::assertSame('visible', $args['visibility']);
        self::assertSame('instock', $args['stock_status']);
        self::assertSame('objects', $args['return']);
        self::assertLessThanOrEqual(200, $args['limit']);
    }

    public function test_backpack_intent_rejects_accessory_descriptions(): void
    {
        $facts                    = $this->facts;
        $facts['_category_names'] = array('Accessories');
        $facts['_search_text']    = 'waterproof cover intentionally not a backpack';
        $facts['attributes']['water_rating'] = 'Waterproof cover';

        self::assertFalse(
            (new ProductSearchMatcher())->matches($facts, array('query' => 'waterproof backpack'))
        );
    }

    public function test_waterproof_intent_requires_ipx_four_or_explicit_waterproof_rating(): void
    {
        $resistant = $this->facts;
        $resistant['attributes']['water_rating'] = 'Water-resistant';
        $resistant['_search_text'] .= ' water-resistant rather than waterproof';

        $ipx_three = $this->facts;
        $ipx_three['attributes']['water_rating'] = 'IPX3';

        self::assertFalse(
            (new ProductSearchMatcher())->matches($resistant, array('query' => 'waterproof backpack'))
        );
        self::assertFalse(
            (new ProductSearchMatcher())->matches($ipx_three, array('query' => 'waterproof backpack'))
        );
        self::assertTrue(
            (new ProductSearchMatcher())->matches($this->facts, array('query' => 'waterproof backpack'))
        );
    }
}
