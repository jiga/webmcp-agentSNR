<?php

/**
 * Public non-sensitive readiness endpoint.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

use WP_REST_Request;
use WP_REST_Response;

final class DiagnosticsController
{
    public function __construct(
        private readonly DiagnosticsService $diagnostics,
        private readonly RestResponseFactory $responses
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        return $this->responses->make(
            array(
                'ok'          => true,
                'diagnostics' => $this->diagnostics->run(),
            )
        );
    }
}
