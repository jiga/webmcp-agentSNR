<?php

/**
 * Dynamic WebMCP manifest endpoint.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WebMCP;

use WP_REST_Request;
use WP_REST_Response;
use WPWebMCP\AgentSNR\Demo\DemoMode;
use WPWebMCP\AgentSNR\Demo\DemoSession;

final class ManifestController
{
    public function __construct(
        private readonly DemoSession $sessions,
        private readonly OriginValidator $origins,
        private readonly ManifestService $manifests,
        private readonly RestResponseFactory $responses
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        if (! DemoMode::enabled()) {
            return $this->responses->make(
                array('ok' => false, 'error' => array('code' => 'demo_mode_disabled', 'message' => 'Public WebMCP tools are unavailable.')),
                403
            );
        }

        if (! $this->origins->validate($request)) {
            return $this->responses->make(
                array('ok' => false, 'error' => array('code' => 'origin_denied', 'message' => 'The request origin is not allowed.')),
                403
            );
        }

        $surface = sanitize_key((string) $request->get_param('surface'));
        if (! in_array($surface, array('storefront', 'agentsnr'), true)) {
            return $this->responses->make(
                array('ok' => false, 'error' => array('code' => 'invalid_surface', 'message' => 'Choose storefront or agentsnr.')),
                400
            );
        }

        $session = $this->sessions->peek();
        if (null === $session) {
            return $this->responses->make(
                array(
                    'ok'    => false,
                    'error' => array(
                        'code'    => 'session_required',
                        'message' => 'Start a same-origin demo session before requesting the tool manifest.',
                    ),
                ),
                401
            );
        }

        return $this->responses->make($this->manifests->build($surface, $session['hash_hex']));
    }
}
