<?php

/**
 * Anonymous demo-session bootstrap endpoint.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WebMCP;

use WP_REST_Request;
use WP_REST_Response;
use WPWebMCP\AgentSNR\Demo\DemoMode;
use WPWebMCP\AgentSNR\Demo\DemoSession;
use WPWebMCP\AgentSNR\Policy\RateLimiter;
use WPWebMCP\AgentSNR\Privacy\ActorHasher;

final class SessionController
{
    public function __construct(
        private readonly DemoSession $sessions,
        private readonly OriginValidator $origins,
        private readonly ManifestService $manifests,
        private readonly RestResponseFactory $responses,
        private readonly RateLimiter $rate_limiter,
        private readonly ActorHasher $hasher
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        if (! DemoMode::enabled()) {
            return $this->responses->make(
                array('ok' => false, 'error' => array('code' => 'demo_mode_disabled', 'message' => 'Public demo sessions are unavailable.')),
                403
            );
        }

        if (! $this->origins->validate($request)) {
            return $this->responses->make(
                array('ok' => false, 'error' => array('code' => 'origin_denied', 'message' => 'The request origin is not allowed.')),
                403
            );
        }

        $remote_address = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : 'unknown';
        $actor_hash     = $this->hasher->hex('session-bootstrap:' . $remote_address);
        $actor_rate     = $this->rate_limiter->consume($actor_hash, 'session_bootstrap', 30, 60);
        $global_rate    = $this->rate_limiter->consume('global', 'session_bootstrap', 500, 60);
        if (! $actor_rate['allowed'] || ! $global_rate['allowed']) {
            $retry_after = max((int) $actor_rate['retry_after'], (int) $global_rate['retry_after']);
            $response    = $this->responses->make(
                array('ok' => false, 'error' => array('code' => 'rate_limit_exceeded', 'message' => 'Too many demo sessions were requested.')),
                429
            );
            $response->header('Retry-After', (string) max(1, $retry_after));

            return $response;
        }

        $surface = sanitize_key((string) $request->get_param('surface'));
        if (! in_array($surface, array('storefront', 'agentsnr'), true)) {
            return $this->responses->make(
                array('ok' => false, 'error' => array('code' => 'invalid_surface', 'message' => 'Choose storefront or agentsnr.')),
                400
            );
        }

        $session  = $this->sessions->ensure();
        $manifest = $this->manifests->build($surface, $session['hash_hex']);

        return $this->responses->make(
            array(
                'ok'                => true,
                'schema_version'    => $manifest['schema_version'],
                'site_id'           => $manifest['site_id'],
                'surface'           => $surface,
                'workflow_id'       => $manifest['workflow_id'],
                'manifest_revision' => $manifest['manifest_revision'],
                'session'           => $manifest['session'],
            )
        );
    }
}
