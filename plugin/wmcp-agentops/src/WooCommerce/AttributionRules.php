<?php

/**
 * Pure deterministic direct/assisted/influenced attribution rules.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WooCommerce;

final class AttributionRules
{
    /**
     * @param list<array<string, mixed>> $candidates Candidate workflow evidence.
     * @param list<int>                  $purchased_product_ids Purchased product IDs.
     * @return array<string, mixed>|null
     */
    public function select_primary(array $candidates, array $purchased_product_ids): ?array
    {
        $purchased_product_ids = array_values(array_unique(array_filter(array_map('intval', $purchased_product_ids))));
        $qualified             = array();

        foreach ($candidates as $candidate) {
            $workflow_id = strtoupper((string) ($candidate['workflow_id'] ?? ''));
            if (1 !== preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $workflow_id)) {
                continue;
            }

            $cart_matches = array_values(array_intersect(
                $purchased_product_ids,
                array_values(array_unique(array_map('intval', (array) ($candidate['cart_product_ids'] ?? array()))))
            ));
            $view_matches = array_values(array_intersect(
                $purchased_product_ids,
                array_values(array_unique(array_map('intval', (array) ($candidate['influence_product_ids'] ?? array()))))
            ));

            if (array() !== $cart_matches && true === ($candidate['checkout_handoff'] ?? false)) {
                $class      = 'direct';
                $confidence = 'high';
                $rank       = 3;
                $matches    = $cart_matches;
            } elseif (array() !== $cart_matches) {
                $class      = 'assisted';
                $confidence = 'high';
                $rank       = 2;
                $matches    = $cart_matches;
            } elseif (array() !== $view_matches) {
                $class      = 'influenced';
                $confidence = 'medium';
                $rank       = 1;
                $matches    = $view_matches;
            } else {
                continue;
            }

            $qualified[] = array(
                'workflow_id'        => $workflow_id,
                'attribution_class'  => $class,
                'confidence'         => $confidence,
                'rank'               => $rank,
                'first_touch_at'     => (string) ($candidate['first_touch_at'] ?? ''),
                'last_touch_at'      => (string) ($candidate['last_touch_at'] ?? ''),
                'matched_products'   => array_values(array_unique($matches)),
                'evidence_event_ids' => array_values(array_unique(array_filter(array_map('strval', (array) ($candidate['evidence_event_ids'] ?? array()))))),
            );
        }

        if (array() === $qualified) {
            return null;
        }

        usort(
            $qualified,
            static function (array $left, array $right): int {
                if ($left['rank'] !== $right['rank']) {
                    return $right['rank'] <=> $left['rank'];
                }

                $touch = strcmp((string) $right['last_touch_at'], (string) $left['last_touch_at']);
                if (0 !== $touch) {
                    return $touch;
                }

                return strcmp((string) $left['workflow_id'], (string) $right['workflow_id']);
            }
        );

        unset($qualified[0]['rank']);

        return $qualified[0];
    }
}
