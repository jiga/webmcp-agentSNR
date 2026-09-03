<?php

/**
 * First-party non-commerce, analytics, diagnostics, and policy callbacks.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Abilities;

use InvalidArgumentException;
use WPWebMCP\AgentSNR\Analytics\CapabilityGapService;
use WPWebMCP\AgentSNR\Analytics\EventRecorder;
use WPWebMCP\AgentSNR\Analytics\FunnelService;
use WPWebMCP\AgentSNR\Analytics\QueryService;
use WPWebMCP\AgentSNR\Analytics\SignalService;
use WPWebMCP\AgentSNR\Contract\EventName;
use WPWebMCP\AgentSNR\Guidance\AgentGuide;
use WPWebMCP\AgentSNR\Policy\PolicyStore;
use WPWebMCP\AgentSNR\Policy\SessionPolicyStore;
use WPWebMCP\AgentSNR\WebMCP\DiagnosticsService;
use WPWebMCP\AgentSNR\WebMCP\ExecutionContext;
use WPWebMCP\AgentSNR\WebMCP\ManifestService;
use WPWebMCP\AgentSNR\WebMCP\ToolException;
use WPWebMCP\AgentSNR\WooCommerce\CartSession;

final class CoreAbilities
{
    public function __construct(
        private readonly QueryService $queries,
        private readonly FunnelService $funnel,
        private readonly CapabilityGapService $gaps,
        private readonly SignalService $signals,
        private readonly AgentGuide $guide,
        private readonly DiagnosticsService $diagnostics,
        private readonly PolicyStore $policies,
        private readonly SessionPolicyStore $session_policies,
        private readonly ManifestService $manifests,
        private readonly EventRecorder $events
    ) {
    }

    public function register(CallbackRouter $router): void
    {
        $router->register('storefront.context', array($this, 'storefront_context'));
        $router->register('storefront.agent_guide', array($this, 'agent_guide'));
        $router->register('analytics.report_capability_gap', array($this, 'report_capability_gap'));
        $router->register('analytics.report_agent_feedback', array($this, 'report_agent_feedback'));
        $router->register('analytics.overview', array($this, 'analytics_overview'));
        $router->register('analytics.funnel', array($this, 'analytics_funnel'));
        $router->register('analytics.query_workflows', array($this, 'query_workflows'));
        $router->register('analytics.explain_workflow', array($this, 'explain_workflow'));
        $router->register('analytics.tool_health', array($this, 'tool_health'));
        $router->register('analytics.capability_gaps', array($this, 'capability_gaps'));
        $router->register('analytics.opportunity_signals', array($this, 'opportunity_signals'));
        $router->register('diagnostics.run', array($this, 'run_diagnostics'));
        $router->register('policy.set_tool_enabled', array($this, 'set_tool_enabled'));
    }

    /**
     * @param array<string, mixed> $input Validated input.
     * @return array<string, mixed>
     */
    public function storefront_context(array $input): array
    {
        $include = isset($input['include']) && is_array($input['include']) ? $input['include'] : array();
        $all     = array() === $include;
        $page    = $this->page_context();

        return array(
            'store'               => array(
                'name' => (string) get_bloginfo('name'),
                'url'  => home_url('/'),
            ),
            'page'                => $page,
            'currency'            => ($all || in_array('currency', $include, true)) && function_exists('get_woocommerce_currency')
                ? (string) get_woocommerce_currency()
                : null,
            'categories'          => $all || in_array('categories', $include, true) ? $this->categories() : array(),
            'policy_summary'      => $all || in_array('policy_summary', $include, true) ? $this->policy_summary() : null,
            'cart_summary'        => $all || in_array('cart_summary', $include, true) ? $this->cart_summary() : null,
            'supported_workflows' => class_exists('WooCommerce')
                ? array('product_discovery', 'product_comparison', 'policy_evidence', 'cart_preparation', 'human_checkout_handoff')
                : array(),
            'agent_guide'         => $this->guide->discovery(),
        );
    }

    /** @return array<string, mixed> */
    public function agent_guide(array $input = array()): array
    {
        unset($input);

        return $this->guide->guide();
    }

    /**
     * @param array<string, mixed> $input Validated input.
     * @return array<string, mixed>
     */
    public function report_capability_gap(array $input): array
    {
        $context = $this->context();

        try {
            $gap = $this->gaps->report(
                $context['workflow_id'],
                $context['session_hash_hex'],
                (string) $input['requested_capability'],
                (string) $input['user_goal'],
                isset($input['related_product_id']) ? (int) $input['related_product_id'] : null,
                isset($input['context']) && is_array($input['context']) ? $input['context'] : array()
            );
        } catch (InvalidArgumentException $exception) {
            throw new ToolException('invalid_capability_gap', 'The unsupported request could not be recorded safely.', 400);
        }

        return array(
            'gap_id'    => (string) $gap['gap_id'],
            'recorded'  => true,
            'fulfilled' => false,
            'message'   => (string) $gap['message'],
        );
    }

    /**
     * @param array<string, mixed> $input Validated input.
     * @return array<string, mixed>
     */
    public function report_agent_feedback(array $input): array
    {
        $context = $this->context();

        try {
            return $this->signals->report_feedback(
                $context['workflow_id'],
                $context['session_hash_hex'],
                $context['request_id'],
                $input
            );
        } catch (InvalidArgumentException $exception) {
            throw new ToolException(
                'invalid_agent_feedback',
                'The structured feedback or its evidence could not be recorded safely.',
                400,
                false,
                'Use only event IDs from this storefront workflow and request supported metric names.'
            );
        }
    }

    /** @param array<string, mixed> $input Validated input. */
    public function analytics_overview(array $input): array
    {
        return $this->analytics_query(
            fn (): array => $this->queries->overview($this->context()['session_hash_hex'], $input)
        );
    }

    /** @param array<string, mixed> $input Validated input. */
    public function analytics_funnel(array $input): array
    {
        return $this->analytics_query(
            fn (): array => $this->funnel->get($this->context()['session_hash_hex'], $input)
        );
    }

    /** @param array<string, mixed> $input Validated input. */
    public function query_workflows(array $input): array
    {
        return $this->analytics_query(
            fn (): array => $this->queries->workflows($this->context()['session_hash_hex'], $input)
        );
    }

    /** @param array<string, mixed> $input Validated input. */
    public function explain_workflow(array $input): array
    {
        $result = $this->analytics_query(
            fn (): ?array => $this->queries->explain(
                $this->context()['session_hash_hex'],
                (string) $input['workflow_id']
            )
        );
        if (null === $result) {
            throw new ToolException('workflow_not_found', 'The workflow was not found in this demo session.', 404);
        }

        return $result;
    }

    /** @param array<string, mixed> $input Validated input. */
    public function tool_health(array $input): array
    {
        return $this->analytics_query(
            fn (): array => $this->queries->tool_health($this->context()['session_hash_hex'], $input)
        );
    }

    /** @param array<string, mixed> $input Validated input. */
    public function capability_gaps(array $input): array
    {
        return $this->analytics_query(
            fn (): array => $this->gaps->grouped($this->context()['session_hash_hex'], $input)
        );
    }

    /** @param array<string, mixed> $input Validated input. */
    public function opportunity_signals(array $input): array
    {
        return $this->analytics_query(
            fn (): array => $this->signals->grouped($this->context()['session_hash_hex'], $input)
        );
    }

    /** @param array<string, mixed> $input Validated input. */
    public function run_diagnostics(array $input): array
    {
        $requested = isset($input['checks']) && is_array($input['checks']) ? array_values($input['checks']) : array();
        $result    = $this->diagnostics->run($requested);
        $context   = $this->context();

        $this->events->record(
            $context['workflow_id'],
            EventName::DIAGNOSTICS_COMPLETED,
            array('properties' => array('diagnostic_count' => count($result['checks']))),
            'diagnostics:' . $context['request_id']
        );

        return $result;
    }

    /** @param array<string, mixed> $input Validated input. */
    public function set_tool_enabled(array $input): array
    {
        $context   = $this->context();
        $tool_name = (string) $input['tool_name'];
        $before    = $this->policies->enabled($tool_name)
            && ! $this->session_policies->disabled($context['session_hash_hex'], $tool_name);

        $state = $this->session_policies->set(
            $context['session_hash_hex'],
            $tool_name,
            (bool) $input['enabled'],
            (string) $input['reason']
        );
        $after = $this->policies->enabled($tool_name)
            && ! $this->session_policies->disabled($context['session_hash_hex'], $tool_name);

        $audit = $this->events->record(
            $context['workflow_id'],
            EventName::POLICY_CHANGED,
            array(
                'properties' => array(
                    'scope'            => 'demo_session',
                    'enabled'          => $after,
                    'previous_enabled' => $before,
                    'reason_code'      => 'merchant_demo_override',
                    'target_tool'      => $tool_name,
                ),
            ),
            'policy:' . $context['request_id']
        );

        return array(
            'tool_name'             => $tool_name,
            'before'                => array('enabled' => $before),
            'after'                 => array('enabled' => $after),
            'requested_enabled'     => (bool) $input['enabled'],
            'scope'                 => 'demo_session',
            'effective_revision'    => $this->manifests->revision('storefront', $context['session_hash_hex']),
            'session_policy_revision' => (string) $state['revision'],
            'actor_type'            => 'anonymous_demo',
            'audit_event_id'        => (string) $audit['event_id'],
        );
    }

    /**
     * @return array{workflow_id:string,request_id:string,session_hash_hex:string}
     */
    private function context(): array
    {
        $context = ExecutionContext::current();
        if (
            ! is_array($context)
            || ! isset($context['workflow_id'], $context['request_id'], $context['session_hash_hex'])
            || ! is_string($context['workflow_id'])
            || ! is_string($context['request_id'])
            || ! is_string($context['session_hash_hex'])
        ) {
            throw new ToolException('execution_context_missing', 'The verified execution context is unavailable.', 403);
        }

        return array(
            'workflow_id'      => $context['workflow_id'],
            'request_id'       => $context['request_id'],
            'session_hash_hex' => $context['session_hash_hex'],
        );
    }

    /**
     * @template T of array|null
     * @param callable():T $callback Scoped analytics operation.
     * @return T
     */
    private function analytics_query(callable $callback)
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $exception) {
            throw new ToolException(
                'invalid_analytics_query',
                'The analytics filters or cursor are invalid.',
                400,
                false,
                'Use the documented date, status, tool, cursor, and limit fields.'
            );
        }
    }

    /**
     * @return array{type:string,title:string,url:string}
     */
    private function page_context(): array
    {
        $context = ExecutionContext::current();
        $url     = is_array($context) && isset($context['page_url']) && is_string($context['page_url'])
            ? esc_url_raw($context['page_url'])
            : home_url('/');
        $url     = is_string($url) && '' !== $url ? $url : home_url('/');
        $post_id = url_to_postid($url);
        $type    = $post_id > 0 ? (string) get_post_type($post_id) : 'page';
        $title   = $post_id > 0 ? (string) get_the_title($post_id) : (string) get_bloginfo('name');

        if ('product' === $type) {
            $type = 'product';
        } elseif (function_exists('wc_get_page_permalink') && untrailingslashit($url) === untrailingslashit((string) wc_get_page_permalink('shop'))) {
            $type = 'shop';
        } elseif (untrailingslashit($url) === untrailingslashit(home_url('/'))) {
            $type = 'front_page';
        } else {
            $type = 'page';
        }

        return array('type' => $type, 'title' => sanitize_text_field($title), 'url' => $url);
    }

    /** @return list<array<string, string>> */
    private function categories(): array
    {
        if (! taxonomy_exists('product_cat')) {
            return array();
        }

        $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 20));
        if (is_wp_error($terms) || ! is_array($terms)) {
            return array();
        }

        return array_map(
            static fn (object $term): array => array(
                'name' => sanitize_text_field((string) $term->name),
                'slug' => sanitize_key((string) $term->slug),
            ),
            $terms
        );
    }

    /** @return array<string, mixed>|null */
    private function policy_summary(): ?array
    {
        $policies = get_option('wmcp_agentsnr_store_policies', array());
        $returns  = is_array($policies) && isset($policies['returns']) && is_array($policies['returns'])
            ? $policies['returns']
            : null;
        if (null === $returns) {
            return null;
        }

        return array(
            'return_days' => isset($returns['facts']['return_days']) ? (int) $returns['facts']['return_days'] : null,
            'url'         => isset($returns['page_id']) ? (string) get_permalink((int) $returns['page_id']) : '',
        );
    }

    /** @return array<string, mixed>|null */
    private function cart_summary(): ?array
    {
        if (! class_exists('WooCommerce')) {
            return null;
        }

        try {
            $cart = (new CartSession())->cart();

            return array(
                'item_count' => method_exists($cart, 'get_cart_contents_count') ? (int) $cart->get_cart_contents_count() : 0,
                'subtotal'   => method_exists($cart, 'get_subtotal') ? (float) $cart->get_subtotal() : 0.0,
            );
        } catch (ToolException $exception) {
            return null;
        }
    }
}
