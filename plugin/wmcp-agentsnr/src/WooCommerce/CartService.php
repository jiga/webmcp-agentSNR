<?php

/**
 * Current-session WooCommerce cart reads and reversible mutations.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

use Throwable;
use WPWebMCP\AgentSNR\WebMCP\ToolException;

final class CartService
{
    public function __construct(
        private readonly CartSession $cart_session,
        private readonly ProductCatalog $products,
        private readonly SessionCorrelator $correlator
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $cart  = $this->cart_session->cart();
        $items = array();

        foreach ((array) $cart->get_cart() as $cart_item_key => $cart_item) {
            if (! is_array($cart_item)) {
                continue;
            }

            $items[] = $this->line((string) $cart_item_key, $cart_item);
        }

        return array(
            'items'          => $items,
            'item_count'     => (int) $cart->get_cart_contents_count(),
            'subtotal'       => (float) $cart->get_subtotal(),
            'discount_total' => (float) $cart->get_discount_total(),
            'currency'       => strtoupper((string) get_woocommerce_currency()),
            'cart_revision'  => $this->revision($cart),
            'checkout_ready' => ! $cart->is_empty() && $this->all_items_purchasable($cart),
        );
    }

    /**
     * @param array<string, mixed> $input Validated add input.
     * @return array<string, mixed>
     */
    public function add(array $input): array
    {
        if (! array_key_exists('expected_cart_revision', $input)) {
            throw new ToolException('invalid_cart_revision', 'The expected cart revision is required.', 400);
        }

        $product_id  = (int) $input['product_id'];
        $quantity    = max(1, min(10, (int) ($input['quantity'] ?? 1)));
        $product     = $this->products->public_product($product_id);
        $variation   = array();
        $variation_id = isset($input['variation_id']) ? (int) $input['variation_id'] : 0;
        $stock_product = $product;

        if (method_exists($product, 'is_type') && $product->is_type('variable')) {
            if (0 >= $variation_id) {
                throw new ToolException('variation_required', 'Choose a specific available product variation.', 400);
            }

            $stock_product = $this->variation($product, $variation_id, isset($input['variation']) && is_array($input['variation']) ? $input['variation'] : array());
            $variation     = (array) $stock_product->get_variation_attributes();
        } elseif (0 < $variation_id) {
            throw new ToolException('invalid_variation', 'That variation does not belong to the requested product.', 400);
        }

        $this->assert_can_purchase($stock_product, $quantity);
        $cart = $this->cart_session->cart();
        $this->assert_revision($cart, (string) $input['expected_cart_revision']);

        try {
            $key = $cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
        } catch (Throwable $throwable) {
            throw new ToolException('cart_add_rejected', 'WooCommerce rejected that cart addition.', 409, false, 'Review the product, variation, stock, and quantity.');
        }

        if (! is_string($key) || '' === $key) {
            throw new ToolException('cart_add_rejected', 'WooCommerce rejected that cart addition.', 409, false, 'Review the product, variation, stock, and quantity.');
        }

        $contents   = (array) $cart->get_cart();
        $existing   = isset($contents[$key][SessionCorrelator::CART_ITEM_KEY]) && is_array($contents[$key][SessionCorrelator::CART_ITEM_KEY])
            ? $contents[$key][SessionCorrelator::CART_ITEM_KEY]
            : null;
        $provenance = $this->correlator->line_provenance('add', $existing);
        if (null !== $provenance && isset($contents[$key])) {
            $contents[$key][SessionCorrelator::CART_ITEM_KEY] = $provenance;
            $cart->set_cart_contents($contents);
        }

        $this->correlator->touch('cart_add', array($product_id));
        $this->bump_revision();
        $this->cart_session->persist();
        $added = $cart->get_cart_item($key);

        return array(
            'added_line' => $this->line($key, is_array($added) ? $added : array()),
            'cart'       => $this->get(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(string $cart_item_key, string $expected_revision): array
    {
        $this->assert_key($cart_item_key);
        $cart = $this->cart_session->cart();
        $this->assert_revision($cart, $expected_revision);
        $item = $cart->get_cart_item($cart_item_key);
        if (! is_array($item) || array() === $item) {
            throw new ToolException('stale_cart_item', 'That cart line no longer exists.', 409, true, 'Refresh the current cart.');
        }

        $removed = $this->line($cart_item_key, $item);
        if (! $cart->remove_cart_item($cart_item_key)) {
            throw new ToolException('cart_remove_rejected', 'WooCommerce could not remove that cart line.', 409, true);
        }

        $this->correlator->touch('cart_remove', array((int) ($item['product_id'] ?? 0)));
        $this->bump_revision();
        $this->cart_session->persist();

        return array('removed_item' => $removed, 'cart' => $this->get());
    }

    /**
     * @return array<string, mixed>
     */
    public function update(string $cart_item_key, int $quantity, string $expected_revision): array
    {
        $this->assert_key($cart_item_key);
        $quantity = max(0, min(10, $quantity));
        $cart     = $this->cart_session->cart();
        $this->assert_revision($cart, $expected_revision);
        $item     = $cart->get_cart_item($cart_item_key);
        if (! is_array($item) || array() === $item) {
            throw new ToolException('stale_cart_item', 'That cart line no longer exists.', 409, true, 'Refresh the current cart.');
        }

        $product = $item['data'] ?? null;
        if (0 < $quantity) {
            if (! is_object($product)) {
                throw new ToolException('cart_item_invalid', 'That cart line no longer references a valid product.', 409);
            }
            $this->assert_can_purchase($product, $quantity);
        }

        if (0 === $quantity) {
            if (! $cart->remove_cart_item($cart_item_key)) {
                throw new ToolException('cart_remove_rejected', 'WooCommerce could not remove that cart line.', 409, true);
            }
            $this->correlator->touch('cart_remove', array((int) ($item['product_id'] ?? 0)));
        } else {
            if (false === $cart->set_quantity($cart_item_key, $quantity, false)) {
                throw new ToolException('cart_update_rejected', 'WooCommerce could not update that cart quantity.', 409);
            }
            $provenance = $this->correlator->line_provenance(
                'update',
                isset($item[SessionCorrelator::CART_ITEM_KEY]) && is_array($item[SessionCorrelator::CART_ITEM_KEY])
                    ? $item[SessionCorrelator::CART_ITEM_KEY]
                    : null
            );
            if (null !== $provenance) {
                $contents = (array) $cart->get_cart();
                $contents[$cart_item_key][SessionCorrelator::CART_ITEM_KEY] = $provenance;
                $cart->set_cart_contents($contents);
            }
            $this->correlator->touch('cart_update', array((int) ($item['product_id'] ?? 0)));
        }

        $this->bump_revision();
        $this->cart_session->persist();

        return $this->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout_handoff(string $expected_revision): array
    {
        $cart_object = $this->cart_session->cart();
        $this->assert_revision($cart_object, $expected_revision);
        $cart = $this->get();
        if (0 === $cart['item_count']) {
            throw new ToolException('empty_cart', 'Add a purchasable product before continuing to checkout.', 409);
        }
        if (! $cart['checkout_ready']) {
            throw new ToolException('cart_not_ready', 'The cart contains an unavailable or invalid item.', 409, true, 'Review the current cart before checkout.');
        }

        $this->correlator->touch('checkout_handoff', array_map(static fn (array $item): int => (int) $item['product_id'], $cart['items']));

        return array(
            'checkout_url' => (string) wc_get_checkout_url(),
            'cart'         => $cart,
            'requirements' => array('Review customer and shipping details.', 'Confirm the payment method and terms yourself.', 'Select the no-charge demo method only on the demo site.'),
            'message'      => 'Continue to checkout for human review. No order was placed and no payment was processed.',
        );
    }

    private function variation(object $parent, int $variation_id, array $provided): object
    {
        $variation = wc_get_product($variation_id);
        if (
            ! is_object($variation)
            || ! method_exists($variation, 'is_type')
            || ! $variation->is_type('variation')
            || ! method_exists($variation, 'get_parent_id')
            || (int) $variation->get_parent_id() !== (int) $parent->get_id()
            || ! method_exists($variation, 'get_status')
            || 'publish' !== (string) $variation->get_status()
        ) {
            throw new ToolException('invalid_variation', 'That variation does not belong to the requested public product.', 400);
        }

        $expected = (array) $variation->get_variation_attributes();
        foreach ($provided as $name => $value) {
            $key = 0 === strpos((string) $name, 'attribute_') ? (string) $name : 'attribute_' . sanitize_title((string) $name);
            if (! array_key_exists($key, $expected) || (string) $expected[$key] !== (string) $value) {
                throw new ToolException('variation_mismatch', 'The supplied variation attributes do not exactly match the selected variation.', 400);
            }
        }

        return $variation;
    }

    private function assert_can_purchase(object $product, int $quantity): void
    {
        if (! method_exists($product, 'is_purchasable') || ! $product->is_purchasable()) {
            throw new ToolException('product_not_purchasable', 'That product is not currently purchasable.', 409);
        }
        if (! method_exists($product, 'is_in_stock') || ! $product->is_in_stock()) {
            throw new ToolException('product_out_of_stock', 'That product is currently out of stock.', 409);
        }
        if (method_exists($product, 'has_enough_stock') && ! $product->has_enough_stock($quantity)) {
            throw new ToolException('insufficient_stock', 'The requested quantity is not currently in stock.', 409);
        }
        if (1 < $quantity && method_exists($product, 'is_sold_individually') && $product->is_sold_individually()) {
            throw new ToolException('sold_individually', 'That product is limited to one per order.', 409);
        }
    }

    private function assert_key(string $cart_item_key): void
    {
        if (1 !== preg_match('/\A[a-f0-9]{32}\z/', $cart_item_key)) {
            throw new ToolException('invalid_cart_item', 'The cart line identifier is invalid.', 400);
        }
    }

    private function assert_revision(object $cart, string $expected_revision): void
    {
        if (1 !== preg_match('/\Acartrev_[a-f0-9]{24}\z/', $expected_revision)) {
            throw new ToolException('invalid_cart_revision', 'The expected cart revision is invalid.', 400);
        }

        if (! hash_equals($this->revision($cart), $expected_revision)) {
            throw new ToolException(
                'stale_cart_revision',
                'The cart changed after the agent last read it.',
                409,
                true,
                'Read the current cart and repeat the action with its latest revision.'
            );
        }
    }

    /**
     * @param array<string, mixed> $item Cart line.
     * @return array<string, mixed>
     */
    private function line(string $key, array $item): array
    {
        $product    = $item['data'] ?? null;
        $provenance = isset($item[SessionCorrelator::CART_ITEM_KEY]) && is_array($item[SessionCorrelator::CART_ITEM_KEY])
            ? $item[SessionCorrelator::CART_ITEM_KEY]
            : array();

        return array(
            'cart_item_key'  => $key,
            'product_id'     => (int) ($item['product_id'] ?? 0),
            'variation_id'   => (int) ($item['variation_id'] ?? 0),
            'name'           => is_object($product) && method_exists($product, 'get_name') ? wp_strip_all_tags((string) $product->get_name(), true) : '',
            'url'            => is_object($product) && method_exists($product, 'get_permalink') ? (string) $product->get_permalink() : '',
            'quantity'       => (int) ($item['quantity'] ?? 0),
            'line_subtotal'  => (float) ($item['line_subtotal'] ?? 0),
            'line_total'     => (float) ($item['line_total'] ?? 0),
            'agent_assisted' => true === ($provenance['added_by_agent'] ?? false)
                || true === ($provenance['modified_by_agent'] ?? false),
        );
    }

    private function all_items_purchasable(object $cart): bool
    {
        foreach ((array) $cart->get_cart() as $item) {
            if (! is_array($item) || ! is_object($item['data'] ?? null)) {
                return false;
            }

            $product  = $item['data'];
            $quantity = (int) ($item['quantity'] ?? 0);
            if (
                1 > $quantity
                || ! method_exists($product, 'is_purchasable')
                || ! $product->is_purchasable()
                || ! method_exists($product, 'is_in_stock')
                || ! $product->is_in_stock()
                || (method_exists($product, 'has_enough_stock') && ! $product->has_enough_stock($quantity))
            ) {
                return false;
            }
        }

        return true;
    }

    private function revision(object $cart): string
    {
        $session  = $this->cart_session->session();
        $revision = method_exists($session, 'get') ? (int) $session->get('wmcp_agentsnr_cart_revision', 0) : 0;
        $hash     = method_exists($cart, 'get_cart_hash') ? (string) $cart->get_cart_hash() : '';

        return 'cartrev_' . substr(hash('sha256', $hash . '|' . $revision), 0, 24);
    }

    private function bump_revision(): void
    {
        $session = $this->cart_session->session();
        if (method_exists($session, 'get') && method_exists($session, 'set')) {
            $session->set('wmcp_agentsnr_cart_revision', ((int) $session->get('wmcp_agentsnr_cart_revision', 0)) + 1);
        }
    }
}
