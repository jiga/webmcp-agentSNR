<?php

/**
 * First-party Ability callback implementations for WooCommerce tools.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WooCommerce;

use WPWebMCP\AgentOps\Abilities\CallbackRouter;
use WPWebMCP\AgentOps\Analytics\OpportunityDetector;
use WPWebMCP\AgentOps\Analytics\SignalService;
use WPWebMCP\AgentOps\Contract\EventName;

final class CommerceAbilities
{
    public function __construct(
        private readonly ProductCatalog $products,
        private readonly StorePolicyService $policies,
        private readonly CartService $cart,
        private readonly CommerceTelemetry $telemetry,
        private readonly ?SignalService $signals = null,
        private readonly ?OpportunityDetector $opportunities = null
    ) {
    }

    public function register(CallbackRouter $router): void
    {
        $router->register('commerce.search_products', array($this, 'search_products'));
        $router->register('commerce.get_product', array($this, 'get_product'));
        $router->register('commerce.compare_products', array($this, 'compare_products'));
        $router->register('commerce.get_policy', array($this, 'get_policy'));
        $router->register('commerce.get_cart', array($this, 'get_cart'));
        $router->register('commerce.add_to_cart', array($this, 'add_to_cart'));
        $router->register('commerce.remove_from_cart', array($this, 'remove_from_cart'));
        $router->register('commerce.update_cart', array($this, 'update_cart'));
        $router->register('commerce.checkout_handoff', array($this, 'checkout_handoff'));
    }

    /**
     * @param array<string, mixed> $input Tool input.
     * @return array<string, mixed>
     */
    public function search_products(array $input): array
    {
        $search      = $this->products->search_with_analysis($input);
        $result      = $search['result'];
        $product_ids = array_values(array_map(static fn (array $product): int => (int) $product['id'], $result['products']));
        $analysis    = null === $this->opportunities
            ? null
            : $this->opportunities->search($input, $result, $search['analysis']);
        $metrics     = is_array($analysis) && isset($analysis['metrics']) && is_array($analysis['metrics']) ? $analysis['metrics'] : array();
        $demand      = is_array($analysis) && isset($analysis['demand']) && is_array($analysis['demand']) ? $analysis['demand'] : array();

        $event = $this->telemetry->record(
            EventName::PRODUCT_SEARCHED,
            'search',
            $product_ids,
            array(
                'result_count'               => (int) $result['result_count'],
                'demand_key'                 => $demand['key'] ?? null,
                'highest_water_rating'       => $metrics['highest_matching_water_rating'] ?? null,
                'in_stock_match_count'       => $metrics['in_stock_match_count'] ?? null,
                'out_of_stock_match_count'   => $metrics['out_of_stock_match_count'] ?? null,
            )
        );
        $result['opportunity_signal'] = null;
        if (null !== $event && null !== $analysis && null !== $this->signals) {
            $signal_product_ids = $product_ids;
            if (array() === $signal_product_ids && isset($search['analysis']['related_product_ids']) && is_array($search['analysis']['related_product_ids'])) {
                $signal_product_ids = array_values(array_map('intval', $search['analysis']['related_product_ids']));
            }
            $recorded = $this->signals->observe_search($event, $analysis, $signal_product_ids);
            if (array() !== $recorded) {
                $first = $recorded[0];
                $result['opportunity_signal'] = array(
                    'id'              => (string) $first['id'],
                    'signal_code'     => (string) $first['capability_slug'],
                    'source'          => 'site_observed',
                    'evidence_status' => 'verified',
                );
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $input Tool input.
     * @return array<string, mixed>
     */
    public function get_product(array $input): array
    {
        $product = $this->products->get($input);
        $this->telemetry->record(
            EventName::PRODUCT_VIEWED,
            'view',
            array((int) $product['id']),
            array(),
            (string) $product['currency'],
            (float) $product['price']
        );

        return array('product' => $product);
    }

    /**
     * @param array<string, mixed> $input Tool input.
     * @return array<string, mixed>
     */
    public function compare_products(array $input): array
    {
        $ids    = array_values(array_map('intval', (array) $input['product_ids']));
        $result = $this->products->compare($ids, isset($input['criteria']) && is_array($input['criteria']) ? $input['criteria'] : array());

        $signals = null === $this->opportunities ? array() : $this->opportunities->comparison($result);
        $event = $this->telemetry->record(
            EventName::PRODUCTS_COMPARED,
            'compare',
            $ids,
            array(
                'result_count'      => count($ids),
                'missing_fact_count' => count((array) ($result['missing_facts'] ?? array())),
            )
        );
        if (null !== $event && array() !== $signals && null !== $this->signals) {
            $this->signals->observe_comparison($event, $signals, $ids);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $input Tool input.
     * @return array<string, mixed>
     */
    public function get_policy(array $input): array
    {
        $product = null;
        $ids     = array();
        if (isset($input['product_id'])) {
            $product = $this->products->get(array('product_id' => (int) $input['product_id']));
            $ids[]   = (int) $input['product_id'];
        }

        $result = $this->policies->get($input, $product);
        $this->telemetry->record(
            EventName::POLICY_VIEWED,
            'policy',
            $ids,
            array('policy_type' => (string) $input['policy_type'])
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_cart(array $input = array()): array
    {
        unset($input);

        return $this->cart->get();
    }

    /**
     * @param array<string, mixed> $input Tool input.
     * @return array<string, mixed>
     */
    public function add_to_cart(array $input): array
    {
        $result = $this->cart->add($input);
        $cart   = $result['cart'];
        $this->record_cart_change(
            'add',
            array((int) $input['product_id']),
            (int) ($input['quantity'] ?? 1),
            $cart
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $input Tool input.
     * @return array<string, mixed>
     */
    public function remove_from_cart(array $input): array
    {
        $result = $this->cart->remove(
            (string) $input['cart_item_key'],
            (string) $input['expected_cart_revision']
        );
        $this->record_cart_change(
            'remove',
            array((int) $result['removed_item']['product_id']),
            0,
            $result['cart']
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $input Tool input.
     * @return array<string, mixed>
     */
    public function update_cart(array $input): array
    {
        $before = $this->cart->get();
        $ids    = array();
        foreach ($before['items'] as $item) {
            if ((string) $item['cart_item_key'] === (string) $input['cart_item_key']) {
                $ids[] = (int) $item['product_id'];
                break;
            }
        }

        $result = $this->cart->update(
            (string) $input['cart_item_key'],
            (int) $input['quantity'],
            (string) $input['expected_cart_revision']
        );
        $this->record_cart_change(0 === (int) $input['quantity'] ? 'remove' : 'update', $ids, (int) $input['quantity'], $result);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout_handoff(array $input = array()): array
    {
        $result      = $this->cart->checkout_handoff((string) $input['expected_cart_revision']);
        $product_ids = array_values(array_map(static fn (array $item): int => (int) $item['product_id'], $result['cart']['items']));

        $this->telemetry->record(
            EventName::CHECKOUT_HANDOFF,
            'checkout_handoff',
            $product_ids,
            array(
                'cart_item_count' => (int) $result['cart']['item_count'],
                'cart_revision'   => (string) $result['cart']['cart_revision'],
                'checkout_ready'  => true,
            ),
            (string) $result['cart']['currency'],
            max(0.0, (float) $result['cart']['subtotal'] - (float) $result['cart']['discount_total'])
        );

        return $result;
    }

    /**
     * @param list<int>            $product_ids Product IDs.
     * @param array<string, mixed> $cart Cart result.
     */
    private function record_cart_change(string $mutation, array $product_ids, int $quantity, array $cart): void
    {
        $this->telemetry->record(
            EventName::CART_CHANGED,
            'cart_' . $mutation,
            $product_ids,
            array(
                'mutation'        => $mutation,
                'quantity'        => $quantity,
                'cart_item_count' => (int) $cart['item_count'],
                'cart_revision'   => (string) $cart['cart_revision'],
                'checkout_ready'  => (bool) $cart['checkout_ready'],
            ),
            (string) $cart['currency'],
            max(0.0, (float) $cart['subtotal'] - (float) $cart['discount_total'])
        );
    }
}
