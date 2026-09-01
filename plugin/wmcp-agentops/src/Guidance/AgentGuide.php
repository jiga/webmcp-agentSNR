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
            'purpose'       => 'Use public store evidence, keep reversible actions reversible, and stop when a person must decide or submit information.',
            'supported_journeys' => array(
                array(
                    'id'    => 'evidence_first_purchase',
                    'title' => 'Evidence-first purchase',
                    'available' => $commerce_available,
                    'steps' => array(
                        array('id' => 'discover', 'tools' => array(ToolName::SEARCH_PRODUCTS), 'effect' => 'read', 'human_required' => false),
                        array('id' => 'compare', 'tools' => array(ToolName::COMPARE_PRODUCTS), 'effect' => 'read', 'human_required' => false),
                        array('id' => 'verify_policy', 'tools' => array(ToolName::GET_STORE_POLICY), 'effect' => 'read', 'human_required' => false),
                        array('id' => 'prepare_cart', 'tools' => array(ToolName::ADD_TO_CART, ToolName::REMOVE_FROM_CART, ToolName::UPDATE_CART_QUANTITY), 'effect' => 'reversible_session_write', 'human_required' => false),
                        array('id' => 'handoff', 'tools' => array(ToolName::CHECKOUT_HANDOFF), 'effect' => 'human_checkpoint', 'human_required' => true),
                    ),
                ),
            ),
            'human_boundaries' => array(
                array(
                    'at'         => 'checkout',
                    'agent_may'  => array('prepare_checkout_handoff'),
                    'human_must' => array('review_details', 'submit_customer_data', 'accept_terms', 'place_order'),
                ),
            ),
            'privacy' => array(
                'stored' => array('tool_name', 'outcome', 'latency', 'product_ids', 'commerce_evidence', 'structured_feedback'),
                'excluded' => array('raw_prompt', 'raw_search_text', 'free_form_feedback', 'identity', 'address', 'payment_data', 'cookies', 'tokens', 'raw_payload'),
            ),
            'feedback' => array(
                'tool'                         => ToolName::REPORT_AGENT_FEEDBACK,
                'recommended_when'             => array('journey_completed', 'journey_blocked', 'zero_results', 'low_coverage', 'missing_capability', 'policy_unclear', 'human_handoff'),
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
