<?php

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentOps\WooCommerce\AttributionRules;

final class AttributionRulesTest extends TestCase
{
    private const EARLY = '01K00000000000000000000000';
    private const LATE = '01K00000000000000000000001';

    public function test_direct_beats_newer_assisted_evidence(): void
    {
        $selected = (new AttributionRules())->select_primary(
            array(
                $this->candidate(self::LATE, array(42), array(), false, '2026-08-29 12:10:00'),
                $this->candidate(self::EARLY, array(42), array(), true, '2026-08-29 12:00:00'),
            ),
            array(42)
        );

        self::assertNotNull($selected);
        self::assertSame(self::EARLY, $selected['workflow_id']);
        self::assertSame('direct', $selected['attribution_class']);
        self::assertSame('high', $selected['confidence']);
    }

    public function test_latest_touch_wins_within_same_class(): void
    {
        $selected = (new AttributionRules())->select_primary(
            array(
                $this->candidate(self::EARLY, array(42), array(), true, '2026-08-29 12:00:00'),
                $this->candidate(self::LATE, array(42), array(), true, '2026-08-29 12:10:00'),
            ),
            array(42)
        );

        self::assertSame(self::LATE, $selected['workflow_id']);
    }

    public function test_lexical_workflow_id_is_stable_tie_break(): void
    {
        $selected = (new AttributionRules())->select_primary(
            array(
                $this->candidate(self::LATE, array(), array(42), false, '2026-08-29 12:00:00'),
                $this->candidate(self::EARLY, array(), array(42), false, '2026-08-29 12:00:00'),
            ),
            array(42)
        );

        self::assertSame(self::EARLY, $selected['workflow_id']);
        self::assertSame('influenced', $selected['attribution_class']);
        self::assertSame('medium', $selected['confidence']);
    }

    public function test_unmatched_products_are_not_attributed(): void
    {
        $selected = (new AttributionRules())->select_primary(
            array($this->candidate(self::EARLY, array(41), array(40), true, '2026-08-29 12:00:00')),
            array(42)
        );

        self::assertNull($selected);
    }

    /**
     * @param list<int> $cart Cart product IDs.
     * @param list<int> $influence Influence product IDs.
     * @return array<string, mixed>
     */
    private function candidate(string $workflow_id, array $cart, array $influence, bool $handoff, string $last_touch): array
    {
        return array(
            'workflow_id'           => $workflow_id,
            'cart_product_ids'      => $cart,
            'influence_product_ids' => $influence,
            'checkout_handoff'      => $handoff,
            'first_touch_at'        => '2026-08-29 11:00:00',
            'last_touch_at'         => $last_touch,
            'evidence_event_ids'    => array('evt_01K00000000000000000000000'),
        );
    }
}
