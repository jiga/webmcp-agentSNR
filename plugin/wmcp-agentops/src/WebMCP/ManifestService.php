<?php

/**
 * Dynamic, policy-filtered WebMCP manifest generation.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

use WPWebMCP\AgentOps\Abilities\ToolCatalog;
use WPWebMCP\AgentOps\Analytics\WorkflowService;
use WPWebMCP\AgentOps\Contract\Versions;
use WPWebMCP\AgentOps\Policy\PolicyEngine;
use WPWebMCP\AgentOps\Policy\SessionPolicyStore;
use WPWebMCP\AgentOps\Support\Json;
use WPWebMCP\AgentOps\WooCommerce\CartSession;

final class ManifestService
{
    public function __construct(
        private readonly ToolCatalog $catalog,
        private readonly PolicyEngine $policy,
        private readonly SessionPolicyStore $session_policies,
        private readonly WorkflowService $workflows,
        private readonly CsrfToken $csrf,
        private readonly CartSession $cart_session
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $surface, string $session_hash_hex): array
    {
        $tools    = $this->public_tools($surface, $session_hash_hex);
        $workflow = $this->workflows->current($session_hash_hex, $surface);

        $manifest = array(
            'schema_version'    => Versions::SCHEMA,
            'manifest_revision' => $this->revision_from_tools($surface, $session_hash_hex, $tools),
            'site_id'           => (string) get_option('wmcp_agentops_site_id', ''),
            'surface'           => $surface,
            'workflow_id'       => (string) $workflow['id'],
            'session'           => array(
                'mode'       => 'demo',
                'csrf_token' => $this->csrf->issue($session_hash_hex, $surface),
                'expires_at' => gmdate('c', $this->csrf->expires_at()),
            ),
            'governance'        => $this->governance($session_hash_hex),
            'tools'             => $tools,
        );

        if ('storefront' === $surface) {
            $manifest['cart'] = $this->cart_snapshot();
        }

        return $manifest;
    }

    public function revision(string $surface, string $session_hash_hex): string
    {
        $tools = $this->public_tools($surface, $session_hash_hex);

        return $this->revision_from_tools($surface, $session_hash_hex, $tools);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function public_tools(string $surface, string $session_hash_hex): array
    {
        $tools = array();
        foreach ($this->catalog->surface($surface) as $definition) {
            if ($definition['requires_woocommerce'] && ! class_exists('WooCommerce')) {
                continue;
            }

            $decision = $this->policy->evaluate($definition, $surface, $session_hash_hex);
            if (! $decision['allowed']) {
                continue;
            }

            $tools[] = array(
                'name'        => (string) $definition['name'],
                'title'       => (string) $definition['title'],
                'description' => (string) $definition['description'],
                'inputSchema' => $definition['input_schema'],
                'annotations' => array(
                    'readOnlyHint'         => (bool) $definition['read_only'],
                    'untrustedContentHint' => (bool) $definition['untrusted_content'],
                ),
                'version'     => (string) $definition['version'],
                'risk_class'  => (string) $definition['risk_class'],
            );
        }

        usort(
            $tools,
            static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name'])
        );

        return $tools;
    }

    /**
     * @param list<array<string, mixed>> $tools Public tool definitions.
     */
    private function revision_from_tools(string $surface, string $session_hash_hex, array $tools): string
    {
        $session_state = $this->session_policies->get($session_hash_hex);
        $hash          = hash(
            'sha256',
            Json::encode(
                array(
                    'schema'           => Versions::SCHEMA,
                    'plugin'           => Versions::PLUGIN,
                    'surface'          => $surface,
                    'session_revision' => $session_state['revision'],
                    'tools'            => $tools,
                )
            )
        );

        return 'rev_' . substr($hash, 0, 24);
    }

    /**
     * Return only safe, current-session policy facts. No session identifier is
     * exposed and this manifest is always private/no-store.
     *
     * @return array{session_overrides: array<string, array<string, mixed>>}
     */
    private function governance(string $session_hash_hex): array
    {
        $state     = $this->session_policies->get($session_hash_hex);
        $overrides = array();

        foreach ($state['tools'] as $tool_name => $override) {
            if (! is_string($tool_name) || ! is_array($override) || false !== ($override['enabled'] ?? null)) {
                continue;
            }

            $overrides[$tool_name] = array(
                'enabled'    => false,
                'reason'     => isset($override['reason']) ? sanitize_text_field((string) $override['reason']) : '',
                'changed_at' => isset($override['changed_at']) ? sanitize_text_field((string) $override['changed_at']) : '',
            );
        }

        return array('session_overrides' => $overrides);
    }

    /**
     * Return only the count needed to hydrate the shared storefront badge.
     * The manifest is private/no-store, so cacheable page HTML stays session-neutral.
     *
     * @return array{item_count: int}|null
     */
    private function cart_snapshot(): ?array
    {
        try {
            $cart = $this->cart_session->cart();
        } catch (ToolException $exception) {
            return null;
        }

        return array(
            'item_count' => method_exists($cart, 'get_cart_contents_count')
                ? max(0, (int) $cart->get_cart_contents_count())
                : 0,
        );
    }
}
