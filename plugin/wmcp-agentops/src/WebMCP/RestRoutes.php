<?php

/**
 * Versioned REST route registration.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

use WPWebMCP\AgentOps\Demo\DemoReset;

final class RestRoutes
{
    public const NAMESPACE = 'wmcp-agentops/v1';

    public function __construct(
        private readonly SessionController $sessions,
        private readonly ManifestController $manifests,
        private readonly ExecutionController $execution,
        private readonly DiagnosticsController $diagnostics,
        private readonly DemoReset $reset
    ) {
    }

    public function hooks(): void
    {
        add_action('rest_api_init', array($this, 'register'));
    }

    public function register(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/session',
            array(
                'methods'             => 'POST',
                'callback'            => array($this->sessions, 'handle'),
                'permission_callback' => '__return_true',
                'args'                => $this->surface_args(),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/manifest',
            array(
                'methods'             => 'GET',
                'callback'            => array($this->manifests, 'handle'),
                'permission_callback' => '__return_true',
                'args'                => $this->surface_args(),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/tools/(?P<tool_name>[a-z0-9_.-]{1,128})',
            array(
                'methods'             => 'POST',
                'callback'            => array($this->execution, 'handle'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'tool_name' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/health',
            array(
                'methods'             => 'GET',
                'callback'            => array($this->diagnostics, 'handle'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/demo/reset',
            array(
                'methods'             => 'POST',
                'callback'            => array($this->reset, 'handle'),
                'permission_callback' => '__return_true',
                'args'                => $this->surface_args(),
            )
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function surface_args(): array
    {
        return array(
            'surface' => array(
                'required'          => true,
                'type'              => 'string',
                'enum'              => array('storefront', 'agentops'),
                'sanitize_callback' => 'sanitize_key',
            ),
        );
    }
}
