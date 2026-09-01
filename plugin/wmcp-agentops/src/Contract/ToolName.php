<?php

/**
 * Stable public WebMCP tool identifiers.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Contract;

final class ToolName
{
    public const GET_STOREFRONT_CONTEXT = 'get_storefront_context';
    public const GET_AGENT_GUIDE = 'get_agent_guide';
    public const SEARCH_PRODUCTS = 'search_products';
    public const GET_PRODUCT = 'get_product';
    public const COMPARE_PRODUCTS = 'compare_products';
    public const GET_STORE_POLICY = 'get_store_policy';
    public const GET_CART = 'get_cart';
    public const ADD_TO_CART = 'add_to_cart';
    public const REMOVE_FROM_CART = 'remove_from_cart';
    public const UPDATE_CART_QUANTITY = 'update_cart_quantity';
    public const CHECKOUT_HANDOFF = 'prepare_checkout_handoff';
    public const REPORT_CAPABILITY_GAP = 'report_capability_gap';
    public const REPORT_AGENT_FEEDBACK = 'report_agent_feedback';

    public const GET_ANALYTICS_OVERVIEW = 'get_agent_analytics_overview';
    public const GET_CONVERSION_FUNNEL = 'get_agent_conversion_funnel';
    public const QUERY_WORKFLOWS = 'query_agent_workflows';
    public const EXPLAIN_WORKFLOW = 'explain_agent_workflow';
    public const GET_TOOL_HEALTH = 'get_tool_health';
    public const GET_CAPABILITY_GAPS = 'get_capability_gaps';
    public const GET_OPPORTUNITY_SIGNALS = 'get_opportunity_signals';
    public const RUN_DIAGNOSTICS = 'run_webmcp_diagnostics';
    public const SET_TOOL_ENABLED = 'set_tool_enabled';

    /**
     * @return list<string>
     */
    public static function storefront(): array
    {
        return array(
            self::GET_STOREFRONT_CONTEXT,
            self::GET_AGENT_GUIDE,
            self::SEARCH_PRODUCTS,
            self::GET_PRODUCT,
            self::COMPARE_PRODUCTS,
            self::GET_STORE_POLICY,
            self::GET_CART,
            self::ADD_TO_CART,
            self::REMOVE_FROM_CART,
            self::UPDATE_CART_QUANTITY,
            self::CHECKOUT_HANDOFF,
            self::REPORT_CAPABILITY_GAP,
            self::REPORT_AGENT_FEEDBACK,
        );
    }

    /**
     * Canonical storefront tools exposed through WebMCP discovery.
     *
     * @return list<string>
     */
    public static function storefrontPublic(): array
    {
        return array_values(
            array_diff(
                self::storefront(),
                array(self::REPORT_CAPABILITY_GAP)
            )
        );
    }

    /**
     * @return list<string>
     */
    public static function agentops(): array
    {
        return array(
            self::GET_ANALYTICS_OVERVIEW,
            self::GET_CONVERSION_FUNNEL,
            self::QUERY_WORKFLOWS,
            self::EXPLAIN_WORKFLOW,
            self::GET_TOOL_HEALTH,
            self::GET_CAPABILITY_GAPS,
            self::GET_OPPORTUNITY_SIGNALS,
            self::RUN_DIAGNOSTICS,
            self::SET_TOOL_ENABLED,
        );
    }
}
