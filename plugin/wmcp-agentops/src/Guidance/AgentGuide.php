<?php

/**
 * Human-readable and machine-readable guidance for visiting agents.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Guidance;

use WPWebMCP\AgentOps\Contract\ToolName;
use WPWebMCP\AgentOps\Contract\Versions;

final class AgentGuide
{
    /**
     * Return the canonical public guide. The same data can be rendered for
     * people and returned to agents, so the two surfaces cannot drift.
     *
     * @return array<string, mixed>
     */
    public function guide(): array
    {
        $commerce_available = class_exists('WooCommerce') && function_exists('wc_get_products');

        return array(
            'version'       => Versions::AGENT_GUIDE,
            'start_here'    => true,
            'commerce_available' => $commerce_available,
            'purpose'       => 'Use public store evidence, keep reversible actions reversible, and stop when a person ' .
                'must decide, submit information, or commit money.',
            'execution'     => array(
                'supported_mode'              => 'top_level_co_browsing',
                'unattended_remote_execution' => 'unsupported',
                'state_rule'                  => 'Use the current visible page and browser session; refresh discovery ' .
                    'after navigation or a disabled-tool result.',
            ),
            'supported_journeys' => array(
                array(
                    'id'    => 'evidence_first_purchase',
                    'title' => 'Evidence-first purchase',
                    'available' => $commerce_available,
                    'steps' => array(
                        $this->journey_step('understand', array(ToolName::GET_AGENT_GUIDE), 'answer'),
                        $this->journey_step('discover', array(ToolName::SEARCH_PRODUCTS), 'answer'),
                        $this->journey_step(
                            'evaluate',
                            array(ToolName::GET_PRODUCT, ToolName::COMPARE_PRODUCTS),
                            'answer'
                        ),
                        $this->journey_step('verify_policy', array(ToolName::GET_STORE_POLICY), 'answer'),
                        $this->journey_step(
                            'prepare_cart',
                            array(
                                ToolName::GET_CART,
                                ToolName::ADD_TO_CART,
                                ToolName::REMOVE_FROM_CART,
                                ToolName::UPDATE_CART_QUANTITY,
                            ),
                            'action'
                        ),
                        $this->journey_step(
                            'handoff',
                            array(ToolName::CHECKOUT_HANDOFF),
                            'human_checkpoint',
                            true
                        ),
                        $this->journey_step(
                            'feedback',
                            array(ToolName::REPORT_AGENT_FEEDBACK),
                            'telemetry',
                            false,
                            true
                        ),
                    ),
                ),
                array(
                    'id'        => 'agent_outcome_monitoring',
                    'title'     => 'Agent outcome monitoring',
                    'available' => true,
                    'steps'     => array(
                        $this->journey_step(
                            'monitor',
                            array(ToolName::GET_ANALYTICS_OVERVIEW, ToolName::QUERY_WORKFLOWS),
                            'answer'
                        ),
                        $this->journey_step(
                            'investigate',
                            array(
                                ToolName::EXPLAIN_WORKFLOW,
                                ToolName::GET_CONVERSION_FUNNEL,
                                ToolName::GET_TOOL_HEALTH,
                                ToolName::GET_OPPORTUNITY_SIGNALS,
                            ),
                            'answer'
                        ),
                        $this->journey_step('diagnose', array(ToolName::RUN_DIAGNOSTICS), 'answer'),
                        $this->journey_step('govern', array(ToolName::SET_TOOL_ENABLED), 'action'),
                    ),
                ),
            ),
            'trust' => array(
                'merchant_content' => 'untrusted',
                'agent_feedback'   => 'agent_reported',
                'site_metrics'     => 'site_computed',
                'effects'          => array(
                    array(
                        'id'                     => 'answer',
                        'changes_commerce_state' => false,
                        'reversible'              => false,
                        'meaning'                 => 'Reads first-party facts; merchant-authored content remains explicitly untrusted.',
                    ),
                    array(
                        'id'                     => 'action',
                        'changes_commerce_state' => true,
                        'reversible'              => true,
                        'meaning'                 => 'Changes only reversible cart or demo-session state and never commits money.',
                    ),
                    array(
                        'id'                     => 'telemetry',
                        'changes_commerce_state' => false,
                        'reversible'              => false,
                        'meaning'                 => 'Records bounded analytics testimony without changing catalog, inventory, policy, or orders.',
                    ),
                    array(
                        'id'                     => 'human_checkpoint',
                        'changes_commerce_state' => false,
                        'reversible'              => false,
                        'meaning'                 => 'Reveals normal human UI without submitting data, accepting terms, creating an order, or processing money.',
                    ),
                    array(
                        'id'                     => 'sensitive_action',
                        'changes_commerce_state' => true,
                        'reversible'              => false,
                        'meaning'                 => 'No public WebMCP tool has authority for money, identity, legal ' .
                            'acceptance, cancellation, or refund.',
                    ),
                ),
            ),
            'sensitive_actions' => array(
                'tool_count'  => 0,
                'tools'       => array(),
                'human_owned' => array(
                    'submit_customer_data',
                    'accept_terms',
                    'place_order',
                    'cancel_order',
                    'refund_order',
                    'process_payment',
                ),
            ),
            'pricing_boundary' => array(
                'before_checkout' => 'cart_subtotal_or_estimate',
                'final_total_at'   => 'human_checkout',
                'may_include'      => array('address_dependent_tax', 'shipping', 'fees', 'discount_adjustments'),
                'agent_rule'       => 'Never describe the cart subtotal or estimate as the final all-in total.',
            ),
            'human_boundaries' => array(
                array(
                    'at'         => 'checkout',
                    'agent_may'  => array(ToolName::CHECKOUT_HANDOFF),
                    'human_must' => array('review_details', 'submit_customer_data', 'accept_terms', 'place_order'),
                ),
            ),
            'privacy' => array(
                'stored' => array('tool_name', 'outcome', 'latency', 'product_ids', 'commerce_evidence', 'structured_feedback'),
                'excluded' => array(
                    'raw_prompt',
                    'raw_search_text',
                    'free_form_feedback',
                    'identity',
                    'address',
                    'payment_data',
                    'cookies',
                    'tokens',
                    'raw_payload',
                ),
            ),
            'feedback' => array(
                'optional'                     => true,
                'tool'                         => ToolName::REPORT_AGENT_FEEDBACK,
                'recommended_when'             => array(
                    'journey_completed',
                    'journey_blocked',
                    'zero_results',
                    'low_coverage',
                    'missing_capability',
                    'policy_unclear',
                    'human_handoff',
                ),
                'max_reports_per_workflow'     => 2,
                'supported_metrics'            => array(
                    'eligible_product_count',
                    'highest_matching_water_rating',
                    'search_refinement_count',
                    'checkout_handoff',
                    'checkout_conversion',
                    'attributed_order_count',
                    'paid_order_value',
                    'net_attributed_value',
                ),
                'metric_rule'                  => 'Request metric names only. The site computes every value from its own evidence.',
            ),
        );
    }

    /** @return array<string, mixed> */
    public function discovery(): array
    {
        return array(
            'available'  => true,
            'tool'       => ToolName::GET_AGENT_GUIDE,
            'version'    => Versions::AGENT_GUIDE,
            'start_here' => true,
        );
    }

    /**
     * @param list<string> $tools Canonical public tool names.
     * @return array<string, mixed>
     */
    private function journey_step(
        string $id,
        array $tools,
        string $effect,
        bool $human_required = false,
        bool $optional = false
    ): array {
        return array(
            'id'             => $id,
            'tools'          => $tools,
            'effect'         => $effect,
            'human_required' => $human_required,
            'optional'       => $optional,
        );
    }

    /**
     * Return a bounded, contextual suggestion after a completed tool call.
     * Evidence always points to the controller-created terminal event rather
     * than accepting a caller-provided workflow or session identifier.
     *
     * @param array<string, mixed> $result Validated Ability result.
     * @return list<array<string, mixed>>
     */
    public function next_actions(string $tool_name, array $result, string $event_id): array
    {
        if (ToolName::SEARCH_PRODUCTS === $tool_name) {
            $count = isset($result['result_count']) ? max(0, (int) $result['result_count']) : 0;
            if (0 === $count) {
                return array($this->feedback_action($event_id, 'blocked', 'missing_product', 'discovery', 'zero_results', 'improve_product_coverage'));
            }
        }

        if (ToolName::CHECKOUT_HANDOFF === $tool_name) {
            return array(
                $this->feedback_action($event_id, 'success', 'worked_well', 'checkout_handoff', 'smooth_handoff', 'no_action'),
            );
        }

        return array();
    }

    /** @return array<string, mixed> */
    private function feedback_action(
        string $event_id,
        string $outcome,
        string $feedback_type,
        string $step,
        string $reason_code,
        string $suggested_action,
        string $recommended_when = 'now'
    ): array {
        return array(
            'tool'               => ToolName::REPORT_AGENT_FEEDBACK,
            'reason'             => $reason_code,
            'recommended_when'   => $recommended_when,
            'evidence_event_ids' => array($event_id),
            'suggested_input'     => array(
                'outcome'                => $outcome,
                'feedback_type'          => $feedback_type,
                'step'                   => $step,
                'reason_code'            => $reason_code,
                'evidence_event_ids'      => array($event_id),
                'requested_metrics'       => array('eligible_product_count', 'highest_matching_water_rating', 'checkout_conversion'),
                'suggested_owner_action'  => $suggested_action,
            ),
        );
    }
}
