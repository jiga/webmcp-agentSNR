<?php

/**
 * Native WordPress Abilities registration generated from the tool catalog.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Abilities;

use WP_Error;
use WPWebMCP\AgentSNR\WebMCP\ExecutionContext;
use WPWebMCP\AgentSNR\WebMCP\ToolException;

final class AbilityRegistry
{
    public function __construct(
        private readonly ToolCatalog $catalog,
        private readonly CallbackRouter $callbacks
    ) {
    }

    public function hooks(): void
    {
        add_action('wp_abilities_api_categories_init', array($this, 'register_categories'));
        add_action('wp_abilities_api_init', array($this, 'register_abilities'));
    }

    public function register_categories(): void
    {
        wp_register_ability_category(
            'wmcp-storefront',
            array(
                'label'       => __('WebMCP storefront', 'wmcp-agentsnr'),
                'description' => __('Safe public storefront and commerce-session capabilities.', 'wmcp-agentsnr'),
            )
        );

        wp_register_ability_category(
            'wmcp-agentsnr',
            array(
                'label'       => __('Agent SNR monitor', 'wmcp-agentsnr'),
                'description' => __('Session-scoped monitoring, diagnostics, outcomes, and governance capabilities.', 'wmcp-agentsnr'),
            )
        );
    }

    public function register_abilities(): void
    {
        foreach ($this->catalog->all() as $definition) {
            $tool_name   = (string) $definition['name'];
            $callback_id = (string) $definition['callback'];

            wp_register_ability(
                (string) $definition['ability_id'],
                array(
                    'label'               => (string) $definition['title'],
                    'description'         => (string) $definition['description'],
                    'category'            => 'storefront' === $definition['surface'] ? 'wmcp-storefront' : 'wmcp-agentsnr',
                    'input_schema'         => $definition['input_schema'],
                    'output_schema'        => $definition['output_schema'],
                    'execute_callback'     => function (array $input = array()) use ($callback_id) {
                        try {
                            return $this->callbacks->execute($callback_id, $input);
                        } catch (ToolException $exception) {
                            return new WP_Error(
                                $exception->error_code(),
                                $exception->getMessage(),
                                array(
                                    'status'     => $exception->http_status(),
                                    'retryable'  => $exception->retryable(),
                                    'recovery'   => $exception->recovery(),
                                    'wmcp_safe'  => true,
                                )
                            );
                        }
                    },
                    'permission_callback'  => static fn (): bool => ExecutionContext::allows($tool_name),
                    'meta'                 => array(
                        'show_in_rest'             => false,
                        'public'                   => false,
                        'annotations'              => array(
                            'readonly'    => (bool) $definition['read_only'],
                            'destructive' => false,
                            'idempotent'  => (bool) $definition['read_only'],
                        ),
                        'wmcp.public'              => (bool) $definition['discoverable'],
                        'wmcp.tool_name'           => $tool_name,
                        'wmcp.tool_version'        => (string) $definition['version'],
                        'wmcp.risk_class'          => (string) $definition['risk_class'],
                        'wmcp.surface'             => (string) $definition['surface'],
                        'wmcp.read_only_hint'      => (bool) $definition['read_only'],
                        'wmcp.untrusted_content'   => (bool) $definition['untrusted_content'],
                        'wmcp.rate_limit_requests' => (int) $definition['rate_limit'],
                        'wmcp.rate_limit_window'   => (int) $definition['rate_window'],
                        'wmcp.max_input_bytes'     => (int) $definition['max_input_bytes'],
                        'wmcp.max_output_bytes'    => (int) $definition['max_output_bytes'],
                    ),
                )
            );
        }
    }
}
