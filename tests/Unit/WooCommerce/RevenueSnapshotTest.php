<?php

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentSNR\WooCommerce\RevenueSnapshot;

final class RevenueSnapshotTest extends TestCase
{
    public function test_paid_partial_refund_recomputes_gross_refund_and_net(): void
    {
        $order = $this->order('processing', 120.0, 25.0, 'EUR', true);

        self::assertSame(
            array('gross_value' => 120.0, 'refund_value' => 25.0, 'net_value' => 95.0, 'currency' => 'EUR'),
            (new RevenueSnapshot())->from_order($order)
        );
    }

    public function test_created_unpaid_order_is_not_revenue(): void
    {
        $order = $this->order('pending', 120.0, 0.0, 'USD', false);

        self::assertSame(0.0, (new RevenueSnapshot())->from_order($order)['gross_value']);
    }

    public function test_cancelled_order_does_not_remain_paid_revenue(): void
    {
        $order = $this->order('cancelled', 120.0, 0.0, 'USD', true);
        $value = (new RevenueSnapshot())->from_order($order);

        self::assertSame(0.0, $value['gross_value']);
        self::assertSame(0.0, $value['net_value']);
    }

    public function test_full_refund_retains_gross_and_reduces_net_to_zero(): void
    {
        $order = $this->order('refunded', 120.0, 120.0, 'GBP', true);
        $value = (new RevenueSnapshot())->from_order($order);

        self::assertSame(120.0, $value['gross_value']);
        self::assertSame(120.0, $value['refund_value']);
        self::assertSame(0.0, $value['net_value']);
        self::assertSame('GBP', $value['currency']);
    }

    private function order(string $status, float $total, float $refunded, string $currency, bool $paid): object
    {
        return new class ($status, $total, $refunded, $currency, $paid) {
            public function __construct(
                private readonly string $status,
                private readonly float $total,
                private readonly float $refunded,
                private readonly string $currency,
                private readonly bool $paid
            ) {
            }

            public function get_status(): string
            {
                return $this->status;
            }

            public function get_total(): float
            {
                return $this->total;
            }

            public function get_total_refunded(): float
            {
                return $this->refunded;
            }

            public function get_currency(): string
            {
                return $this->currency;
            }

            public function get_date_paid(): ?object
            {
                return $this->paid ? (object) array('paid' => true) : null;
            }
        };
    }
}
