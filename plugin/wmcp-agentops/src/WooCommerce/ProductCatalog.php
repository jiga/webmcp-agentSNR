<?php

/**
 * Public WooCommerce catalog lookup, search, and comparison.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WooCommerce;

use Closure;
use WPWebMCP\AgentOps\WebMCP\ToolException;

final class ProductCatalog
{
    private const CATALOG_SCAN_LIMIT = 200;

    private Closure $query_products;

    private bool $uses_default_query;

    public function __construct(
        private readonly ProductNormalizer $normalizer,
        private readonly ProductSearchMatcher $matcher,
        ?callable $query_products = null
    ) {
        $this->uses_default_query = null === $query_products;
        $this->query_products = null === $query_products
            ? static fn (array $args): array => (array) wc_get_products($args)
            : Closure::fromCallable($query_products);
    }

    /**
     * @param array<string, mixed> $input Validated tool input.
     * @return array{products: list<array<string, mixed>>, result_count: int, query: string}
     */
    public function search(array $input): array
    {
        return $this->search_with_analysis($input)['result'];
    }

    /**
     * Search once over the bounded catalog and retain full-set aggregate facts
     * for trusted telemetry before the public product page is sliced. A second
     * bounded scan runs only when an in-stock search returned zero, allowing an
     * existing out-of-stock match to be classified as inventory demand.
     *
     * @param array<string, mixed> $input Validated tool input.
     * @return array{result:array<string,mixed>,analysis:array<string,mixed>}
     */
    public function search_with_analysis(array $input): array
    {
        $this->assert_available();

        $limit    = max(1, min(8, (int) ($input['limit'] ?? 6)));
        $matches  = $this->matches($input);
        $analysis = $this->aggregate($matches);
        if (0 === count($matches) && true === ($input['in_stock_only'] ?? true)) {
            $all_stock_input                  = $input;
            $all_stock_input['in_stock_only'] = false;
            $all_stock_matches                = $this->matches($all_stock_input);
            $out_of_stock                     = array_values(
                array_filter(
                    $all_stock_matches,
                    static fn (array $facts): bool => 'instock' !== ($facts['stock_status'] ?? null)
                        || true !== ($facts['purchasable'] ?? false)
                )
            );
            if (array() !== $out_of_stock) {
                $secondary = $this->aggregate($out_of_stock);
                $analysis['out_of_stock_match_count']       = count($out_of_stock);
                $analysis['highest_matching_water_rating']  = $secondary['highest_matching_water_rating'];
                $analysis['related_product_ids']            = $secondary['matched_product_ids'];
                $analysis['related_product_title']          = $secondary['first_product_title'];
            }
        }

        return array(
            'result' => array(
                'products'     => array_values(
                    array_map(
                        array($this->normalizer, 'without_internal'),
                        array_slice($matches, 0, $limit)
                    )
                ),
                'result_count' => count($matches),
                'query'        => (string) $input['query'],
            ),
            'analysis' => $analysis,
        );
    }

    /**
     * @param array<string, mixed> $input Validated search input.
     * @return list<array<string, mixed>>
     */
    private function matches(array $input): array
    {
        $matches = array();
        foreach (($this->query_products)(self::public_query_args($input)) as $product) {
            if (! is_object($product) || ! $this->normalizer->is_public($product)) {
                continue;
            }
            $facts = $this->normalizer->summary($product);
            if ($this->matcher->matches($facts, $input)) {
                $matches[] = $facts;
            }
        }
        usort(
            $matches,
            static function (array $left, array $right): int {
                $price = ((float) $left['price']) <=> ((float) $right['price']);

                return 0 !== $price ? $price : strcasecmp((string) $left['name'], (string) $right['name']);
            }
        );

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $matches Full matched fact set.
     * @return array<string, mixed>
     */
    private function aggregate(array $matches): array
    {
        $in_stock = 0;
        $out_of_stock = 0;
        $best_rating = null;
        $best_score  = -1;
        foreach ($matches as $facts) {
            if ('instock' === ($facts['stock_status'] ?? null) && true === ($facts['purchasable'] ?? false)) {
                ++$in_stock;
            } else {
                ++$out_of_stock;
            }
            $rating = $facts['attributes']['water_rating'] ?? null;
            if (! is_string($rating) || '' === trim($rating)) {
                continue;
            }
            $score = $this->water_score($rating);
            if ($score > $best_score) {
                $best_score  = $score;
                $best_rating = $rating;
            }
        }

        return array(
            'eligible_product_count'         => count($matches),
            'highest_matching_water_rating' => $best_rating,
            'in_stock_match_count'           => $in_stock,
            'out_of_stock_match_count'       => $out_of_stock,
            'matched_product_ids'            => array_values(
                array_slice(
                    array_filter(array_map(static fn (array $facts): int => (int) ($facts['id'] ?? 0), $matches)),
                    0,
                    20
                )
            ),
            'first_product_title'            => isset($matches[0]['name']) ? (string) $matches[0]['name'] : null,
        );
    }

    /**
     * @param array<string, mixed> $input Validated lookup input.
     * @return array<string, mixed>
     */
    public function get(array $input): array
    {
        $this->assert_available();

        $has_id   = isset($input['product_id']);
        $has_slug = isset($input['slug']) && is_string($input['slug']) && '' !== trim($input['slug']);
        if ($has_id === $has_slug) {
            throw new ToolException('invalid_product_lookup', 'Provide exactly one product ID or product slug.', 400);
        }

        $args = array(
            'status'     => 'publish',
            'visibility' => 'visible',
            'limit'      => $has_id ? 1 : self::CATALOG_SCAN_LIMIT,
            'return'     => 'objects',
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        if ($has_id) {
            $args['include'] = array((int) $input['product_id']);
        }

        $wanted_slug = $has_slug ? strtolower(trim((string) $input['slug'])) : '';
        foreach (($this->query_products)($args) as $product) {
            if (! is_object($product) || ! $this->normalizer->is_public($product)) {
                continue;
            }

            if ($has_slug && (! method_exists($product, 'get_slug') || strtolower((string) $product->get_slug()) !== $wanted_slug)) {
                continue;
            }

            return $this->normalizer->without_internal($this->normalizer->detail($product));
        }

        throw new ToolException('product_not_found', 'The requested public product was not found.', 404);
    }

    public function public_product(int $product_id): object
    {
        $this->assert_available();

        $products = ($this->query_products)(
            array(
                'status'     => 'publish',
                'visibility' => 'visible',
                'include'    => array($product_id),
                'limit'      => 1,
                'return'     => 'objects',
            )
        );

        foreach ($products as $product) {
            if (is_object($product) && $this->normalizer->is_public($product)) {
                return $product;
            }
        }

        throw new ToolException('product_not_found', 'The requested public product was not found.', 404);
    }

    /**
     * @param list<int>    $product_ids Product IDs.
     * @param list<string> $criteria Criteria.
     * @return array<string, mixed>
     */
    public function compare(array $product_ids, array $criteria = array()): array
    {
        $product_ids = array_values(array_unique(array_map('intval', $product_ids)));
        if (2 > count($product_ids) || 4 < count($product_ids)) {
            throw new ToolException('invalid_comparison', 'Compare between two and four unique public products.', 400);
        }

        $criteria = array_values(array_intersect(
            array_unique($criteria),
            array('price', 'capacity', 'water_rating', 'weight', 'laptop_size', 'return_days')
        ));
        if (array() === $criteria) {
            $criteria = array('price', 'capacity', 'water_rating', 'return_days');
        }

        $products = array();
        foreach ($product_ids as $product_id) {
            $product = $this->get(array('product_id' => $product_id));
            unset($product['slug'], $product['short_description'], $product['images']);
            $products[] = $product;
        }

        $matrix        = array();
        $missing_facts = array();
        foreach ($products as $product) {
            $row = array('product_id' => $product['id']);
            foreach ($criteria as $criterion) {
                $value           = $this->criterion($product, $criterion);
                $row[$criterion] = $value;
                if (null === $value) {
                    $missing_facts[] = array('product_id' => $product['id'], 'criterion' => $criterion);
                }
            }
            $matrix[] = $row;
        }

        return array(
            'products'          => $products,
            'criteria'          => $criteria,
            'matrix'            => $matrix,
            'missing_facts'     => $missing_facts,
            'value_scores'      => $this->value_scores($matrix, $criteria),
            'score_explanation' => 'Demo value score normalizes only the selected facts that are present: lower price plus higher capacity, water rating, laptop size, return window, or lower weight. It is a transparent demo heuristic, not an objective universal ranking.',
        );
    }

    /**
     * @param array<string, mixed> $input Search input.
     * @return array<string, mixed>
     */
    public static function public_query_args(array $input): array
    {
        $args = array(
            'status'     => 'publish',
            'visibility' => 'visible',
            'limit'      => self::CATALOG_SCAN_LIMIT,
            'return'     => 'objects',
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        if (true === ($input['in_stock_only'] ?? true)) {
            $args['stock_status'] = 'instock';
        }

        if (isset($input['categories']) && is_array($input['categories'])) {
            $categories = array_values(array_filter(array_map('sanitize_title', $input['categories'])));
            if (array() !== $categories) {
                $args['category'] = $categories;
            }
        }

        return $args;
    }

    /**
     * @param array<string, mixed> $product Product facts.
     * @return mixed
     */
    private function criterion(array $product, string $criterion)
    {
        $attributes = isset($product['attributes']) && is_array($product['attributes']) ? $product['attributes'] : array();

        return match ($criterion) {
            'price'        => isset($product['price']) ? (float) $product['price'] : null,
            'capacity'     => isset($attributes['capacity_liters']) ? (float) $attributes['capacity_liters'] : null,
            'water_rating' => $attributes['water_rating'] ?? null,
            'weight'       => isset($attributes['weight']) ? (float) $attributes['weight'] : null,
            'laptop_size'  => isset($attributes['laptop_inches']) ? (float) $attributes['laptop_inches'] : null,
            'return_days'  => isset($product['return_days']) ? (int) $product['return_days'] : null,
            default        => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $matrix Comparison matrix.
     * @param list<string>               $criteria Criteria.
     * @return list<array{product_id: int, score: float|null}>
     */
    private function value_scores(array $matrix, array $criteria): array
    {
        $numeric = array();
        foreach ($criteria as $criterion) {
            foreach ($matrix as $row) {
                $value = $row[$criterion] ?? null;
                if ('water_rating' === $criterion && is_string($value)) {
                    $value = $this->water_score($value);
                }
                if (is_numeric($value)) {
                    $numeric[$criterion][] = (float) $value;
                }
            }
        }

        $scores = array();
        foreach ($matrix as $row) {
            $parts = array();
            foreach ($criteria as $criterion) {
                $value = $row[$criterion] ?? null;
                if ('water_rating' === $criterion && is_string($value)) {
                    $value = $this->water_score($value);
                }
                if (! is_numeric($value) || empty($numeric[$criterion])) {
                    continue;
                }

                $minimum    = min($numeric[$criterion]);
                $maximum    = max($numeric[$criterion]);
                $normalized = $maximum === $minimum ? 1.0 : (((float) $value - $minimum) / ($maximum - $minimum));
                if (in_array($criterion, array('price', 'weight'), true)) {
                    $normalized = 1.0 - $normalized;
                }
                $parts[] = $normalized;
            }

            $scores[] = array(
                'product_id' => (int) $row['product_id'],
                'score'      => array() === $parts ? null : round((array_sum($parts) / count($parts)) * 100, 1),
            );
        }

        return $scores;
    }

    private function water_score(string $rating): float
    {
        if (1 === preg_match('/ipx\s*([0-9])/i', $rating, $matches)) {
            return (float) $matches[1];
        }

        return false !== stripos($rating, 'waterproof') ? 6.0 : 3.0;
    }

    private function assert_available(): void
    {
        if ($this->uses_default_query && ! function_exists('wc_get_products')) {
            throw new ToolException('woocommerce_unavailable', 'WooCommerce catalog tools are unavailable.', 503, true);
        }
    }
}
