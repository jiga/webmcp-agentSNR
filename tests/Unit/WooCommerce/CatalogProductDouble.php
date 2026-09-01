<?php

/**
 * Minimal public WooCommerce product double for catalog-analysis tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\WooCommerce;

final class CatalogProductDouble
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly float $price,
        private readonly string $water_rating,
        private readonly string $stock_status
    ) {
    }

    public function get_status(): string
    {
        return 'publish';
    }

    public function get_catalog_visibility(): string
    {
        return 'visible';
    }

    public function is_visible(): bool
    {
        return true;
    }

    public function is_type(string $type): bool
    {
        unset($type);

        return false;
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_slug(): string
    {
        return strtolower(str_replace(' ', '-', $this->name));
    }

    public function get_price(): float
    {
        return $this->price;
    }

    public function get_stock_status(): string
    {
        return $this->stock_status;
    }

    public function is_purchasable(): bool
    {
        return 'instock' === $this->stock_status;
    }

    public function is_in_stock(): bool
    {
        return 'instock' === $this->stock_status;
    }

    public function get_permalink(): string
    {
        return 'https://store.test/product/' . $this->id;
    }

    public function get_short_description(): string
    {
        return 'A waterproof public catalog product.';
    }

    /** @return array<int, mixed> */
    public function get_attributes(): array
    {
        return array();
    }

    public function get_meta(string $key, bool $single): mixed
    {
        unset($single);

        return match ($key) {
            '_wmcp_water_rating' => $this->water_rating,
            '_wmcp_demo_product' => 'yes',
            default => '',
        };
    }
}
