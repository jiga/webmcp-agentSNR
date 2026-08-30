<?php

/**
 * Public reset rotates only the current demo scope.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Demo;

use Throwable;
use WP_REST_Request;
use WP_REST_Response;
use WPWebMCP\AgentOps\Policy\RateLimiter;
use WPWebMCP\AgentOps\Policy\SessionPolicyStore;
use WPWebMCP\AgentOps\WebMCP\CsrfToken;
use WPWebMCP\AgentOps\WebMCP\ManifestService;
use WPWebMCP\AgentOps\WebMCP\OriginValidator;
use WPWebMCP\AgentOps\WebMCP\RestResponseFactory;
use WPWebMCP\AgentOps\WooCommerce\CartSession;
use WPWebMCP\AgentOps\WooCommerce\SessionCorrelator;
use WPWebMCP\AgentOps\Support\Logger;

final class DemoReset
{
    public function __construct(
        private readonly DemoSession $sessions,
        private readonly OriginValidator $origins,
        private readonly CsrfToken $csrf,
        private readonly SessionPolicyStore $session_policies,
        private readonly RateLimiter $rate_limiter,
        private readonly ManifestService $manifests,
        private readonly RestResponseFactory $responses
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        if (! DemoMode::enabled()) {
            return $this->responses->make(array('ok' => false, 'error' => array('code' => 'demo_mode_disabled', 'message' => 'Demo reset is unavailable.')), 403);
        }

        if (! $this->origins->validate($request)) {
            return $this->responses->make(array('ok' => false, 'error' => array('code' => 'origin_denied', 'message' => 'The request origin is not allowed.')), 403);
        }

        $surface = sanitize_key((string) $request->get_param('surface'));
        if (! in_array($surface, array('storefront', 'agentops'), true)) {
            return $this->responses->make(array('ok' => false, 'error' => array('code' => 'invalid_surface', 'message' => 'Choose storefront or agentops.')), 400);
        }

        $current = $this->sessions->peek();
        if (null === $current) {
            return $this->responses->make(
                array('ok' => false, 'error' => array('code' => 'session_required', 'message' => 'Start a demo session before resetting it.')),
                401
            );
        }
        $token   = (string) $request->get_header('x-wmcp-csrf');
        if (! $this->csrf->verify($token, $current['hash_hex'], $surface)) {
            return $this->responses->make(array('ok' => false, 'error' => array('code' => 'csrf_invalid', 'message' => 'The session token is invalid or expired.')), 403);
        }

        $rate = $this->rate_limiter->consume($current['hash_hex'], 'demo_reset', 5, 600);
        if (! $rate['allowed']) {
            $response = $this->responses->make(array('ok' => false, 'error' => array('code' => 'rate_limit_exceeded', 'message' => 'Too many reset requests.')), 429);
            $response->header('Retry-After', (string) $rate['retry_after']);
            return $response;
        }

        if (class_exists('WooCommerce')) {
            try {
                $cart_session = new CartSession();
                $cart         = $cart_session->cart();
                if (method_exists($cart, 'empty_cart')) {
                    $cart->empty_cart(true);
                }

                $woo_session = $cart_session->session();
                if (method_exists($woo_session, '__unset')) {
                    $woo_session->__unset(SessionCorrelator::SESSION_KEY);
                } elseif (method_exists($woo_session, 'set')) {
                    $woo_session->set(SessionCorrelator::SESSION_KEY, null);
                }
                $cart_session->persist();
            } catch (Throwable $exception) {
                Logger::error('demo_cart_reset_failed', array('exception' => get_class($exception)));

                return $this->responses->make(
                    array(
                        'ok'    => false,
                        'error' => array(
                            'code'      => 'cart_reset_failed',
                            'message'   => 'The current cart could not be cleared safely.',
                            'retryable' => true,
                        ),
                    ),
                    503
                );
            }
        }

        $this->session_policies->clear($current['hash_hex']);
        $fresh    = $this->sessions->rotate();
        if (false === wp_next_scheduled('wmcp_agentops_cleanup_session', array($current['hash_hex']))) {
            $scheduled = wp_schedule_single_event(
                time() + MINUTE_IN_SECONDS,
                'wmcp_agentops_cleanup_session',
                array($current['hash_hex']),
                true
            );
            if (is_wp_error($scheduled)) {
                Logger::error('demo_cleanup_schedule_failed', array('error_code' => $scheduled->get_error_code()));
            }
        }
        $manifest = $this->manifests->build($surface, $fresh['hash_hex']);

        return $this->responses->make(
            array(
                'ok'       => true,
                'message'  => 'A fresh private demo session is ready. The prior scoped records are queued for bounded cleanup.',
                'manifest' => $manifest,
            )
        );
    }
}
