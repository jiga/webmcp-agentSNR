<?php

/**
 * Privacy-safe demand-signature tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Analytics;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentOps\Analytics\DemandSignature;

final class DemandSignatureTest extends TestCase
{
    public function test_signature_keeps_only_canonical_public_demand_facts(): void
    {
        $signature = (new DemandSignature())->from_search(
            array(
                'query'         => 'Email shopper@example.test about compact waterproof backpacks',
                'max_price'     => 100,
                'in_stock_only' => true,
                'categories'    => array('Backpacks'),
                'attributes'    => array(
                    'water_rating' => 'IPX5',
                    'email'        => 'shopper@example.test',
                    'phone'        => '+1 (415) 555-0199',
                ),
            ),
            array('products' => array(), 'result_count' => 0)
        );

        self::assertSame(array('backpack', 'compact', 'waterproof'), $signature['context']['terms']);
        self::assertSame(array('backpacks'), $signature['context']['categories']);
        self::assertSame(array('water_rating' => 'ipx5'), $signature['context']['attributes']);
        self::assertSame('under_100', $signature['context']['price_bucket']);
        self::assertTrue($signature['context']['in_stock_only']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $signature['key']);

        $encoded = json_encode($signature, JSON_UNESCAPED_SLASHES);
        self::assertIsString($encoded);
        self::assertStringNotContainsString('shopper@example.test', $encoded);
        self::assertStringNotContainsString('415', $encoded);
    }

    public function test_raw_queries_with_the_same_public_facts_converge(): void
    {
        $signatures = new DemandSignature();
        $first = $signatures->from_search(
            array('query' => 'waterproof backpack secret-one', 'max_price' => 100),
            array('products' => array(), 'result_count' => 0)
        );
        $second = $signatures->from_search(
            array('query' => 'secret-two waterproof backpacks', 'max_price' => 100),
            array('products' => array(), 'result_count' => 0)
        );

        self::assertSame($first['key'], $second['key']);
        self::assertSame($first['context'], $second['context']);
        self::assertStringNotContainsString('secret', strtolower($first['title']));
    }

    public function test_unknown_free_text_becomes_other_instead_of_being_retained(): void
    {
        $signature = (new DemandSignature())->from_search(
            array('query' => 'bespoke-private-goal-93847'),
            array('products' => array(), 'result_count' => 0)
        );

        self::assertSame(array('other'), $signature['context']['terms']);
        self::assertSame('Product search', $signature['title']);
        self::assertStringNotContainsString('93847', json_encode($signature));
    }

    public function test_unknown_queries_receive_distinct_opaque_grouping_keys(): void
    {
        $signatures = new DemandSignature();
        $first = $signatures->from_search(
            array('query' => 'private-novel-demand-one'),
            array('products' => array(), 'result_count' => 0)
        );
        $second = $signatures->from_search(
            array('query' => 'private-novel-demand-two'),
            array('products' => array(), 'result_count' => 0)
        );

        self::assertNotSame($first['key'], $second['key']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $first['context']['unknown_query_key']);
        self::assertStringNotContainsString('private-novel', json_encode($first));
        self::assertSame('Product search', $first['title']);
    }
}
