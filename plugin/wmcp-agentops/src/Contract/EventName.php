<?php

/**
 * Stable analytics event identifiers.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Contract;

final class EventName
{
    public const WORKFLOW_STARTED = 'workflow.started';
    public const WORKFLOW_COMPLETED = 'workflow.completed';
    public const WORKFLOW_ABANDONED = 'workflow.abandoned';
    public const WORKFLOW_EXPIRED = 'workflow.expired';

    public const TOOL_CALL_STARTED = 'tool.call.started';
    public const TOOL_CALL_SUCCEEDED = 'tool.call.succeeded';
    public const TOOL_CALL_FAILED = 'tool.call.failed';
    public const TOOL_CALL_CANCELLED = 'tool.call.cancelled';
    public const TOOL_CALL_DENIED = 'tool.call.denied';

    public const POLICY_EVALUATED = 'policy.evaluated';
    public const POLICY_CHANGED = 'policy.changed';
    public const KILL_SWITCH_CHANGED = 'kill_switch.changed';

    public const PRODUCT_SEARCHED = 'commerce.product.searched';
    public const PRODUCT_VIEWED = 'commerce.product.viewed';
    public const PRODUCTS_COMPARED = 'commerce.products.compared';
    public const POLICY_VIEWED = 'commerce.policy.viewed';
    public const CART_CHANGED = 'commerce.cart.changed';
    public const CHECKOUT_HANDOFF = 'commerce.checkout.handoff';
    public const ORDER_CREATED = 'commerce.order.created';
    public const ORDER_PAID = 'commerce.order.paid';
    public const ORDER_CANCELLED = 'commerce.order.cancelled';
    public const ORDER_REFUNDED = 'commerce.order.refunded';

    public const CAPABILITY_GAP_REPORTED = 'capability_gap.reported';
    public const DIAGNOSTICS_COMPLETED = 'diagnostics.completed';
    public const DEMO_RESET = 'demo.reset';

    /**
     * @return list<string>
     */
    public static function tool_terminal(): array
    {
        return array(
            self::TOOL_CALL_SUCCEEDED,
            self::TOOL_CALL_FAILED,
            self::TOOL_CALL_CANCELLED,
            self::TOOL_CALL_DENIED,
        );
    }
}
