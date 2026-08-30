<?php

/**
 * Cart service concurrency contract tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WPWebMCP\AgentOps\WebMCP\ToolException;
use WPWebMCP\AgentOps\WooCommerce\CartService;

final class CartServiceTest extends TestCase
{
    public function test_add_rejects_missing_expected_revision_before_using_dependencies(): void
    {
        $service = (new ReflectionClass(CartService::class))->newInstanceWithoutConstructor();

        try {
            $service->add(array('product_id' => 123));
            self::fail('A cart add without optimistic concurrency control must be rejected.');
        } catch (ToolException $exception) {
            self::assertSame('invalid_cart_revision', $exception->error_code());
            self::assertSame(400, $exception->http_status());
            self::assertSame('The expected cart revision is required.', $exception->getMessage());
        }
    }
}
