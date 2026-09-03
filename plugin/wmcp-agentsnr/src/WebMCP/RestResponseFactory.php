<?php

/**
 * Non-cacheable REST envelopes for session-scoped data.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WebMCP;

use WP_REST_Response;

final class RestResponseFactory
{
    /**
     * @param array<string, mixed> $body Response payload.
     */
    public function make(array $body, int $status = 200): WP_REST_Response
    {
        $response = new WP_REST_Response($body, $status);
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Vary', 'Cookie, Origin');
        $response->header('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
