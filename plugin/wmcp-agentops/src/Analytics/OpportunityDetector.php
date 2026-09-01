<?php

/**
 * Deterministic opportunity detection from validated commerce results.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Analytics;

final class OpportunityDetector
{
    public function __construct(private readonly DemandSignature $signatures = new DemandSignature())
    {
    }

    /**
     * @param array<string, mixed> $input Search input.
     * @param array<string, mixed> $result Search result.
     * @return array{demand:array<string,mixed>,metrics:array<string,mixed>,signals:list<array<string,string>>}
     */
    public function search(array $input, array $result, array $catalog_analysis = array()): array
    {
        $demand   = $this->signatures->from_search($input, $result);
        if (
            0 === (int) ($result['result_count'] ?? 0)
            && 0 < (int) ($catalog_analysis['out_of_stock_match_count'] ?? 0)
            && isset($catalog_analysis['related_product_title'])
            && is_string($catalog_analysis['related_product_title'])
        ) {
            $demand['title'] = mb_substr($catalog_analysis['related_product_title'] . ' · out of stock', 0, 300);
        }
        $products = isset($result['products']) && is_array($result['products']) ? $result['products'] : array();
        $count    = max(0, (int) ($result['result_count'] ?? count($products)));
        $in_stock = 0;
        $out_of_stock = 0;
        $ratings = array();
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            if ('instock' === ($product['stock_status'] ?? null) && true === ($product['purchasable'] ?? false)) {
                ++$in_stock;
            } else {
                ++$out_of_stock;
            }
            $rating = $product['attributes']['water_rating'] ?? null;
            if (is_string($rating) && '' !== trim($rating)) {
                $ratings[] = $rating;
            }
        }

        $metrics = array(
            'eligible_product_count'         => isset($catalog_analysis['eligible_product_count'])
                ? max(0, (int) $catalog_analysis['eligible_product_count'])
                : $count,
            'highest_matching_water_rating' => isset($catalog_analysis['highest_matching_water_rating'])
                && is_string($catalog_analysis['highest_matching_water_rating'])
                    ? $catalog_analysis['highest_matching_water_rating']
                    : $this->highest_rating($ratings),
            'in_stock_match_count'           => isset($catalog_analysis['in_stock_match_count'])
                ? max(0, (int) $catalog_analysis['in_stock_match_count'])
                : $in_stock,
            'out_of_stock_match_count'       => isset($catalog_analysis['out_of_stock_match_count'])
                ? max(0, (int) $catalog_analysis['out_of_stock_match_count'])
                : $out_of_stock,
        );
        $signals = array();
        if (0 === $count && 0 === $metrics['out_of_stock_match_count']) {
            $signals[] = array(
                'category' => 'demand_gap',
                'code'     => 'zero_results',
                'action'   => 'improve_product_coverage',
            );
        } elseif (0 < $count && 2 >= $count && $this->constrained($input)) {
            $signals[] = array(
                'category' => 'demand_gap',
                'code'     => 'low_coverage',
                'action'   => 'improve_product_coverage',
            );
        }
        if (0 < $metrics['out_of_stock_match_count']) {
            $signals[] = array(
                'category' => 'inventory_gap',
                'code'     => 'out_of_stock_match',
                'action'   => 'review_inventory',
            );
        }

        return array('demand' => $demand, 'metrics' => $metrics, 'signals' => $signals);
    }

    /**
     * @param array<string, mixed> $result Comparison result.
     * @return list<array<string, mixed>>
     */
    public function comparison(array $result): array
    {
        $missing = isset($result['missing_facts']) && is_array($result['missing_facts']) ? count($result['missing_facts']) : 0;
        if (0 === $missing) {
            return array();
        }

        return array(
            array(
                'category' => 'experience_friction',
                'code'     => 'missing_product_data',
                'title'    => 'Product comparison has missing facts',
                'action'   => 'improve_product_data',
                'metrics'  => array('missing_fact_count' => $missing),
            ),
        );
    }

    /** @param array<string, mixed> $input Search input. */
    private function constrained(array $input): bool
    {
        return isset($input['max_price'])
            || isset($input['min_price'])
            || ! empty($input['categories'])
            || ! empty($input['attributes']);
    }

    /** @param list<string> $ratings Water ratings. */
    private function highest_rating(array $ratings): ?string
    {
        $best = null;
        $score = -1;
        foreach ($ratings as $rating) {
            $candidate = 1 === preg_match('/ipx\s*([0-9])/i', $rating, $matches)
                ? (int) $matches[1]
                : (false !== stripos($rating, 'waterproof') ? 6 : 0);
            if ($candidate > $score) {
                $score = $candidate;
                $best  = $rating;
            }
        }

        return $best;
    }
}
