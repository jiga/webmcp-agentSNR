<?php

declare(strict_types=1);

namespace {
    if (! function_exists('WC')) {
        function WC(): object
        {
            return $GLOBALS['wmcp_cart_session_woocommerce'];
        }
    }

    if (! function_exists('wc_load_cart')) {
        function wc_load_cart(): void
        {
        }
    }
}

namespace WPWebMCP\AgentSNR\Tests\Unit\WooCommerce {

    use PHPUnit\Framework\Attributes\RunInSeparateProcess;
    use PHPUnit\Framework\TestCase;
    use WPWebMCP\AgentSNR\WooCommerce\CartSession;

    final class CartSessionTest extends TestCase
    {
        #[RunInSeparateProcess]
        public function test_cookie_priming_does_not_recalculate_or_write_cart(): void
        {
            $cart = new class () {
                public int $calculate_calls = 0;
                public int $set_session_calls = 0;

                public function calculate_totals(): void
                {
                    ++$this->calculate_calls;
                }

                public function set_session(): void
                {
                    ++$this->set_session_calls;
                }
            };
            $session = new class () {
                public int $cookie_calls = 0;

                public function set_customer_session_cookie(bool $set): void
                {
                    if ($set) {
                        ++$this->cookie_calls;
                    }
                }
            };
            $GLOBALS['wmcp_cart_session_woocommerce'] = (object) array(
                'cart'    => $cart,
                'session' => $session,
            );

            (new CartSession())->prime_cookie();

            self::assertSame(0, $cart->calculate_calls);
            self::assertSame(0, $cart->set_session_calls);
            self::assertSame(1, $session->cookie_calls);
        }
    }
}
