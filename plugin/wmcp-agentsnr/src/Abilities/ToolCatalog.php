<?php

/**
 * Single source of truth for native Abilities and WebMCP tools.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Abilities;

use WPWebMCP\AgentSNR\Contract\RiskClass;
use WPWebMCP\AgentSNR\Contract\ToolName;

final class ToolCatalog
{
    public const ABILITY_NAMESPACE = 'wmcp-agentsnr';

    /**
     * Human-readable descriptions for every public input-schema property.
     * Reused names intentionally have one meaning across tools so schema
     * guidance cannot drift between the native Ability and WebMCP manifest.
     *
     * @var array<string, string>
     */
    private const INPUT_PROPERTY_DESCRIPTIONS = array(
        'include'                => 'Optional result sections to include.',
        'query'                  => 'Product name or type words only; put price, stock, categories, and attributes in their dedicated fields.',
        'max_price'              => 'Highest acceptable product price in the store currency.',
        'min_price'              => 'Lowest acceptable product price in the store currency.',
        'categories'             => 'Public product category slugs to require.',
        'attributes'             => 'Structured product filters; use canonical keys such as water_rating, for example {"water_rating":"IPX5"}.',
        'in_stock_only'          => 'Whether to exclude products that are not currently in stock.',
        'limit'                  => 'Maximum number of matching records to return.',
        'product_id'             => 'Positive WooCommerce product ID to read or filter by.',
        'slug'                   => 'Public WooCommerce product slug to read.',
        'product_ids'            => 'Two to four positive WooCommerce product IDs to compare.',
        'criteria'               => 'Stored product facts to include in the comparison.',
        'policy_type'            => 'Published store-policy topic to read.',
        'quantity'               => 'Desired cart quantity; zero removes an existing line.',
        'variation_id'           => 'Positive WooCommerce variation ID when the product requires one.',
        'variation'              => 'Exact public variation attributes selected by the shopper.',
        'expected_cart_revision' => 'Latest cart revision from get_cart or a cart-changing result.',
        'cart_item_key'          => 'Opaque cart line key returned by get_cart.',
        'requested_capability'   => 'Legacy enum for the unsupported store capability encountered.',
        'user_goal'              => 'Short redacted goal that the unsupported capability blocked.',
        'related_product_id'     => 'Optional positive product ID related to the unsupported request.',
        'context'                => 'Small redacted key-value context for the unsupported request.',
        'outcome'                => 'Agent-reported outcome of the current journey.',
        'feedback_type'          => 'Structured category for the optional journey feedback.',
        'step'                   => 'Journey stage to which the feedback applies.',
        'reason_code'            => 'Structured reason supporting the reported outcome.',
        'evidence_event_ids'     => 'Same-workflow event IDs returned by this site as evidence.',
        'ratings'                => 'Optional closed-set ratings for evidence, policy, handoff, and effort.',
        'evidence_quality'       => 'Whether site evidence was sufficient for the agent task.',
        'policy_clarity'         => 'Whether the cited store policy was clear for the task.',
        'handoff_quality'        => 'Whether the human checkout handoff was smooth or blocked.',
        'effort'                 => 'Agent-reported effort required to complete the journey.',
        'requested_metrics'      => 'Metric names for the site to compute from its own evidence.',
        'suggested_owner_action' => 'Closed-set follow-up the agent suggests to the site owner.',
        'date_from'              => 'Optional inclusive start date in YYYY-MM-DD format.',
        'date_to'                => 'Optional inclusive end date in YYYY-MM-DD format.',
        'tool_name'              => 'Canonical tool name to filter or govern.',
        'status'                 => 'Optional lifecycle status used to filter matching records.',
        'cursor'                 => 'Opaque pagination cursor returned by the preceding call.',
        'workflow_id'            => 'ULID of one workflow in the authorized session scope.',
        'category'               => 'Optional category filter; omit it to include every signal category in one result.',
        'source'                 => 'Optional evidence-source filter; omit it to include site-observed and agent-reported signals together.',
        'checks'                 => 'Read-only diagnostic checks to run; omit to run all checks.',
        'enabled'                => 'False restricts the tool for this session; true clears that override.',
        'scope'                  => 'Required governance scope; only this demo session is supported.',
        'reason'                 => 'Short operator reason recorded with the session-only change.',
    );

    /**
     * @return array<string, array<string, mixed>> Definitions keyed by WebMCP name.
     */
    public function all(): array
    {
        $definitions = array(
            $this->definition(
                ToolName::GET_STOREFRONT_CONTEXT,
                'Get storefront context',
                'Use on storefront entry to read current public page, store, policy, cart, and ' .
                    'guide-discovery context. This read does not change the cart or place an order.',
                'storefront',
                RiskClass::READ,
                $this->object(
                    array(
                        'include' => array(
                            'type'        => 'array',
                            'items'       => array(
                                'type' => 'string',
                                'enum' => array('page', 'currency', 'categories', 'policy_summary', 'cart_summary'),
                            ),
                            'maxItems'    => 5,
                            'uniqueItems' => true,
                        ),
                    )
                ),
                $this->object(
                    array(
                        'store'               => array('type' => 'object'),
                        'page'                => array('type' => 'object'),
                        'currency'            => array('type' => array('string', 'null')),
                        'categories'          => array('type' => 'array'),
                        'policy_summary'      => array('type' => array('object', 'null')),
                        'cart_summary'        => array('type' => array('object', 'null')),
                        'supported_workflows' => array('type' => 'array'),
                        'agent_guide'         => array('type' => 'object'),
                    ),
                    array('store', 'page', 'supported_workflows', 'agent_guide')
                ),
                'storefront.context',
                true,
                true,
                false,
                30
            ),
            $this->definition(
                ToolName::GET_AGENT_GUIDE,
                'Get the agent guide',
                'Use once at the start of a co-browsing journey to learn shopper and operator paths, ' .
                    'tool effects, privacy, pricing, and human boundaries. This read changes no state.',
                'storefront',
                RiskClass::READ,
                $this->object(array()),
                $this->agent_guide_output_schema(),
                'storefront.agent_guide',
                true,
                false,
                false,
                30
            ),
            $this->definition(
                ToolName::SEARCH_PRODUCTS,
                'Search products',
                'Call once per constraint set to search the public WooCommerce catalog, and wait for its result before refining. Put price, stock, category, and attribute filters in dedicated fields instead of repeating them in query; use attributes.water_rating for IPX constraints. This tool does not modify the cart.',
                'storefront',
                RiskClass::READ,
                $this->object(
                    array(
                        'query'         => array('type' => 'string', 'minLength' => 1, 'maxLength' => 200),
                        'max_price'     => array('type' => 'number', 'minimum' => 0, 'maximum' => 1000000),
                        'min_price'     => array('type' => 'number', 'minimum' => 0, 'maximum' => 1000000),
                        'categories'    => array(
                            'type'        => 'array',
                            'items'       => array('type' => 'string', 'maxLength' => 80),
                            'maxItems'    => 5,
                            'uniqueItems' => true,
                        ),
                        'attributes'    => array(
                            'type'                 => 'object',
                            'maxProperties'        => 8,
                            'additionalProperties' => array('type' => 'string', 'maxLength' => 80),
                        ),
                        'in_stock_only' => array('type' => 'boolean', 'default' => true),
                        'limit'         => array('type' => 'integer', 'minimum' => 1, 'maximum' => 8, 'default' => 6),
                    ),
                    array('query')
                ),
                $this->object(
                    array(
                        'products'     => array('type' => 'array', 'items' => $this->product_summary_schema(), 'maxItems' => 8),
                        'result_count' => array('type' => 'integer', 'minimum' => 0),
                        'query'        => array('type' => 'string'),
                        'opportunity_signal' => array('type' => array('object', 'null')),
                    ),
                    array('products', 'result_count', 'query', 'opportunity_signal')
                ),
                'commerce.search_products',
                true,
                true,
                true,
                20
            ),
            $this->definition(
                ToolName::GET_PRODUCT,
                'Get product',
                'Read one public WooCommerce product by ID or slug. Merchant-authored text is returned as untrusted content; no private product metadata is exposed.',
                'storefront',
                RiskClass::READ,
                $this->object(
                    array(
                        'product_id' => array('type' => 'integer', 'minimum' => 1),
                        'slug'       => array('type' => 'string', 'minLength' => 1, 'maxLength' => 200),
                        'include'    => array(
                            'type'        => 'array',
                            'items'       => array('type' => 'string', 'enum' => array('description', 'attributes', 'images', 'policy')),
                            'maxItems'    => 4,
                            'uniqueItems' => true,
                        ),
                    )
                ),
                $this->object(array('product' => $this->product_detail_schema()), array('product')),
                'commerce.get_product',
                true,
                true,
                true,
                30
            ),
            $this->definition(
                ToolName::COMPARE_PRODUCTS,
                'Compare products',
                'Compare two to four public products using stored facts. Missing facts remain null, and any demo value score is explained rather than presented as objective.',
                'storefront',
                RiskClass::READ,
                $this->object(
                    array(
                        'product_ids' => array(
                            'type'        => 'array',
                            'items'       => array('type' => 'integer', 'minimum' => 1),
                            'minItems'    => 2,
                            'maxItems'    => 4,
                            'uniqueItems' => true,
                        ),
                        'criteria'    => array(
                            'type'        => 'array',
                            'items'       => array(
                                'type' => 'string',
                                'enum' => array('price', 'capacity', 'water_rating', 'weight', 'laptop_size', 'return_days'),
                            ),
                            'maxItems'    => 6,
                            'uniqueItems' => true,
                        ),
                    ),
                    array('product_ids')
                ),
                $this->object(
                    array(
                        'products'          => array('type' => 'array', 'items' => $this->product_summary_schema(), 'minItems' => 2, 'maxItems' => 4),
                        'criteria'          => array('type' => 'array'),
                        'matrix'            => array('type' => 'array'),
                        'missing_facts'     => array('type' => 'array'),
                        'value_scores'      => array('type' => 'array'),
                        'score_explanation' => array('type' => 'string'),
                    ),
                    array('products', 'criteria', 'matrix', 'missing_facts', 'value_scores', 'score_explanation')
                ),
                'commerce.compare_products',
                true,
                true,
                true,
                20
            ),
            $this->definition(
                ToolName::GET_STORE_POLICY,
                'Get store policy',
                'Read published store-policy facts and a short merchant-authored evidence excerpt. This tool does not make or change a purchase.',
                'storefront',
                RiskClass::READ,
                $this->object(
                    array(
                        'policy_type' => array('type' => 'string', 'enum' => array('returns', 'shipping', 'warranty', 'privacy', 'all')),
                        'product_id'  => array('type' => 'integer', 'minimum' => 1),
                    ),
                    array('policy_type')
                ),
                $this->object(
                    array(
                        'policies' => array('type' => 'array', 'items' => array('type' => 'object'), 'maxItems' => 4),
                    ),
                    array('policies')
                ),
                'commerce.get_policy',
                true,
                true,
                false,
                30
            ),
            $this->definition(
                ToolName::GET_CART,
                'Get cart',
                'Read the current WooCommerce session cart without customer identity, address, or payment data. This tool does not change the cart.',
                'storefront',
                RiskClass::READ,
                $this->object(array()),
                $this->cart_schema(),
                'commerce.get_cart',
                true,
                true,
                true,
                30
            ),
            $this->definition(
                ToolName::ADD_TO_CART,
                'Add product to cart',
                'Add a public purchasable product to this browser session cart using the latest cart revision. This reversible action does not create an order, accept terms, or process payment.',
                'storefront',
                RiskClass::SESSION_WRITE,
                $this->object(
                    array(
                        'product_id'   => array('type' => 'integer', 'minimum' => 1),
                        'quantity'     => array('type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 1),
                        'variation_id' => array('type' => 'integer', 'minimum' => 1),
                        'variation'    => array(
                            'type'                 => 'object',
                            'maxProperties'        => 8,
                            'additionalProperties' => array('type' => 'string', 'maxLength' => 100),
                        ),
                        'expected_cart_revision' => array('type' => 'string', 'pattern' => '^cartrev_[a-f0-9]{24}$'),
                    ),
                    array('product_id', 'expected_cart_revision')
                ),
                $this->object(array('added_line' => array('type' => 'object'), 'cart' => $this->cart_schema()), array('added_line', 'cart')),
                'commerce.add_to_cart',
                false,
                true,
                true,
                10
            ),
            $this->definition(
                ToolName::REMOVE_FROM_CART,
                'Remove cart item',
                'Remove one line from this browser session cart. This reversible action does not cancel an order or process money.',
                'storefront',
                RiskClass::SESSION_WRITE,
                $this->object(
                    array(
                        'cart_item_key' => array('type' => 'string', 'pattern' => '^[a-f0-9]{32}$', 'maxLength' => 32),
                        'expected_cart_revision' => array('type' => 'string', 'pattern' => '^cartrev_[a-f0-9]{24}$'),
                    ),
                    array('cart_item_key', 'expected_cart_revision')
                ),
                $this->object(array('removed_item' => array('type' => 'object'), 'cart' => $this->cart_schema()), array('removed_item', 'cart')),
                'commerce.remove_from_cart',
                false,
                true,
                true,
                10
            ),
            $this->definition(
                ToolName::UPDATE_CART_QUANTITY,
                'Update cart quantity',
                'Change one line quantity in this browser session cart; zero removes it. This reversible action does not create or change an order.',
                'storefront',
                RiskClass::SESSION_WRITE,
                $this->object(
                    array(
                        'cart_item_key' => array('type' => 'string', 'pattern' => '^[a-f0-9]{32}$', 'maxLength' => 32),
                        'quantity'      => array('type' => 'integer', 'minimum' => 0, 'maximum' => 10),
                        'expected_cart_revision' => array('type' => 'string', 'pattern' => '^cartrev_[a-f0-9]{24}$'),
                    ),
                    array('cart_item_key', 'quantity', 'expected_cart_revision')
                ),
                $this->cart_schema(),
                'commerce.update_cart',
                false,
                true,
                true,
                10
            ),
            $this->definition(
                ToolName::CHECKOUT_HANDOFF,
                'Prepare checkout handoff',
                'Validate this session cart and reveal the normal checkout link for human review. This tool never places an order, accepts terms, submits customer data, or processes payment.',
                'storefront',
                RiskClass::SESSION_WRITE,
                $this->object(
                    array('expected_cart_revision' => array('type' => 'string', 'pattern' => '^cartrev_[a-f0-9]{24}$')),
                    array('expected_cart_revision')
                ),
                $this->object(
                    array(
                        'checkout_url' => array('type' => 'string', 'format' => 'uri'),
                        'cart'         => $this->cart_schema(),
                        'requirements' => array('type' => 'array', 'items' => array('type' => 'string')),
                        'message'      => array('type' => 'string'),
                    ),
                    array('checkout_url', 'cart', 'requirements', 'message')
                ),
                'commerce.checkout_handoff',
                false,
                true,
                true,
                10
            ),
            $this->definition(
                ToolName::REPORT_CAPABILITY_GAP,
                'Report unsupported request',
                'Legacy compatibility telemetry for a known unsupported store capability. It is not ' .
                    'publicly discoverable; new journeys use report_agent_feedback. It never creates a ' .
                    'notification, reservation, or order.',
                'storefront',
                RiskClass::TELEMETRY_WRITE,
                $this->object(
                    array(
                        'requested_capability' => array(
                            'type' => 'string',
                            'enum' => array('back_in_stock_notification', 'price_drop_alert', 'product_reservation', 'custom_product_configuration', 'other'),
                        ),
                        'user_goal'            => array('type' => 'string', 'minLength' => 3, 'maxLength' => 300),
                        'related_product_id'   => array('type' => 'integer', 'minimum' => 1),
                        'context'              => array(
                            'type'                 => 'object',
                            'maxProperties'        => 4,
                            'additionalProperties' => array('type' => 'string', 'maxLength' => 80),
                        ),
                    ),
                    array('requested_capability', 'user_goal')
                ),
                $this->object(
                    array(
                        'gap_id'    => array('type' => 'string'),
                        'recorded'  => array('type' => 'boolean'),
                        'fulfilled' => array('type' => 'boolean'),
                        'message'   => array('type' => 'string'),
                    ),
                    array('gap_id', 'recorded', 'fulfilled', 'message')
                ),
                'analytics.report_capability_gap',
                false,
                false,
                false,
                5,
                60,
                false
            ),
            $this->definition(
                ToolName::REPORT_AGENT_FEEDBACK,
                'Report structured agent feedback',
                'Optionally report a completed or blocked journey using site-issued evidence IDs. This ' .
                    'bounded telemetry cannot request products, alter commerce, or supply metric values; ' .
                    'the site computes them.',
                'storefront',
                RiskClass::TELEMETRY_WRITE,
                $this->object(
                    array(
                        'outcome' => array('type' => 'string', 'enum' => array('success', 'partial', 'blocked', 'abandoned')),
                        'feedback_type' => array(
                            'type' => 'string',
                            'enum' => array('worked_well', 'missing_product', 'missing_capability', 'policy_unclear', 'insufficient_product_data', 'confusing_result', 'constraint_encountered', 'too_many_steps', 'slow_response', 'handoff_issue'),
                        ),
                        'step' => array('type' => 'string', 'enum' => array('discovery', 'comparison', 'policy', 'cart', 'checkout_handoff', 'journey_summary')),
                        'reason_code' => array(
                            'type' => 'string',
                            'enum' => array('useful_evidence', 'smooth_handoff', 'zero_results', 'low_coverage', 'out_of_stock', 'budget_tradeoff', 'missing_fact', 'unsupported_capability', 'unclear_policy', 'slow_tool', 'difficult_handoff'),
                        ),
                        'evidence_event_ids' => array(
                            'type'        => 'array',
                            'items'       => array('type' => 'string', 'pattern' => '^evt_[0-9A-HJKMNP-TV-Z]{26}$'),
                            'minItems'    => 1,
                            'maxItems'    => 6,
                            'uniqueItems' => true,
                        ),
                        'ratings' => $this->object(
                            array(
                                'evidence_quality' => array('type' => 'string', 'enum' => array('sufficient', 'insufficient', 'unknown')),
                                'policy_clarity'   => array('type' => 'string', 'enum' => array('clear', 'unclear', 'not_applicable')),
                                'handoff_quality'  => array('type' => 'string', 'enum' => array('smooth', 'difficult', 'blocked', 'not_applicable')),
                                'effort'           => array('type' => 'string', 'enum' => array('low', 'medium', 'high')),
                            )
                        ),
                        'requested_metrics' => array(
                            'type'        => 'array',
                            'items'       => array('type' => 'string', 'enum' => array('eligible_product_count', 'highest_matching_water_rating', 'search_refinement_count', 'checkout_handoff', 'checkout_conversion', 'attributed_order_count', 'paid_order_value', 'net_attributed_value')),
                            'maxItems'    => 5,
                            'uniqueItems' => true,
                        ),
                        'suggested_owner_action' => array(
                            'type' => 'string',
                            'enum' => array('clarify_policy', 'improve_product_data', 'add_capability', 'fix_tool_error', 'reduce_steps', 'improve_product_coverage', 'review_inventory', 'review_handoff', 'no_action'),
                        ),
                    ),
                    array('outcome', 'feedback_type', 'step', 'reason_code', 'evidence_event_ids')
                ),
                $this->object(
                    array(
                        'feedback_id'            => array('type' => 'string'),
                        'recorded'               => array('type' => 'boolean'),
                        'replayed'               => array('type' => 'boolean'),
                        'trust'                  => array('type' => 'string', 'enum' => array('agent_reported')),
                        'evidence_status'        => array('type' => 'string', 'enum' => array('linked')),
                        'evidence_event_ids'     => array('type' => 'array', 'items' => array('type' => 'string')),
                        'measured_context'       => array('type' => 'object'),
                        'suggested_owner_action' => array('type' => 'string'),
                        'message'                => array('type' => 'string'),
                    ),
                    array('feedback_id', 'recorded', 'replayed', 'trust', 'evidence_status', 'evidence_event_ids', 'measured_context', 'suggested_owner_action', 'message')
                ),
                'analytics.report_agent_feedback',
                false,
                false,
                false,
                4
            ),
        );

        foreach ($this->agentsnr_definitions() as $definition) {
            $definitions[] = $definition;
        }

        $indexed = array();
        foreach ($definitions as $definition) {
            $indexed[$definition['name']] = $definition;
        }

        return $indexed;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $name): ?array
    {
        $all = $this->all();

        return $all[$name] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function surface(string $surface): array
    {
        return array_values(
            array_filter(
                $this->all(),
                static fn (array $definition): bool => $surface === $definition['surface']
            )
        );
    }

    /**
     * Return only canonical tools intended for public WebMCP discovery.
     * Registered legacy Abilities remain available through all() and surface().
     *
     * @return list<array<string, mixed>>
     */
    public function public_surface(string $surface): array
    {
        return array_values(
            array_filter(
                $this->surface($surface),
                static fn (array $definition): bool => true === $definition['discoverable']
            )
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function agentsnr_definitions(): array
    {
        $read_schema = $this->object(
            array(
                'date_from' => array('type' => 'string', 'format' => 'date'),
                'date_to'   => array('type' => 'string', 'format' => 'date'),
                'tool_name' => array('type' => 'string', 'maxLength' => 128),
            )
        );
        $open_object = array('type' => 'object', 'additionalProperties' => true);
        $overview_output = $this->object(
            array(
                'scope'           => $open_object,
                'workflows'       => $open_object,
                'tool_calls'      => $open_object,
                'commerce'        => $open_object,
                'revenue'         => $open_object,
                'capability_gaps' => $open_object,
                'signals'         => $open_object,
                'policy_changes'  => array('type' => 'integer', 'minimum' => 0),
            ),
            array('scope', 'workflows', 'tool_calls', 'commerce', 'revenue', 'capability_gaps', 'signals', 'policy_changes')
        );
        $funnel_output = $this->object(
            array(
                'scope'            => $open_object,
                'stages'           => array('type' => 'array', 'items' => $open_object),
                'workflow_count'   => array('type' => 'integer', 'minimum' => 0),
                'truncated'        => array('type' => 'boolean'),
                'value_definition' => array('type' => 'string'),
            ),
            array('scope', 'stages', 'workflow_count', 'truncated', 'value_definition')
        );
        $collection_output = $this->object(
            array(
                'items'       => array('type' => 'array', 'items' => $open_object),
                'next_cursor' => array('type' => array('string', 'null')),
                'has_more'    => array('type' => 'boolean'),
                'limit'       => array('type' => 'integer', 'minimum' => 1),
            ),
            array('items', 'next_cursor', 'has_more', 'limit')
        );
        $explanation_output = $this->object(
            array(
                'workflow'          => $open_object,
                'explanation'       => array('type' => 'string'),
                'first_problem'     => array('type' => array('object', 'null')),
                'recovery'          => array('type' => array('object', 'null')),
                'commerce_outcome'  => $open_object,
                'capability_gaps'   => array('type' => 'array', 'items' => $open_object),
                'opportunity_signals' => array('type' => 'array', 'items' => $open_object),
                'agent_feedback'    => array('type' => 'array', 'items' => $open_object),
                'timeline'          => array('type' => 'array', 'items' => $open_object),
                'truncated'         => array('type' => 'boolean'),
            ),
            array('workflow', 'explanation', 'first_problem', 'recovery', 'commerce_outcome', 'capability_gaps', 'opportunity_signals', 'agent_feedback', 'timeline', 'truncated')
        );
        $health_output = $this->object(
            array(
                'items'     => array('type' => 'array', 'items' => $open_object),
                'truncated' => array('type' => 'boolean'),
                'event_cap' => array('type' => 'integer', 'minimum' => 1),
            ),
            array('items', 'truncated', 'event_cap')
        );
        $diagnostics_output = $this->object(
            array(
                'plugin_version'          => array('type' => 'string'),
                'schema_version'          => array('type' => 'string'),
                'wordpress_version'       => array('type' => 'string'),
                'php_version'             => array('type' => 'string'),
                'checks'                  => $open_object,
                'browser_checks_required' => array('type' => 'array', 'items' => array('type' => 'string')),
            ),
            array('plugin_version', 'schema_version', 'wordpress_version', 'php_version', 'checks', 'browser_checks_required')
        );
        $policy_output = $this->object(
            array(
                'tool_name'               => array('type' => 'string'),
                'before'                  => $open_object,
                'after'                   => $open_object,
                'requested_enabled'       => array('type' => 'boolean'),
                'scope'                   => array('type' => 'string'),
                'effective_revision'      => array('type' => 'string'),
                'session_policy_revision' => array('type' => 'string'),
                'actor_type'              => array('type' => 'string'),
                'audit_event_id'           => array('type' => 'string'),
            ),
            array('tool_name', 'before', 'after', 'requested_enabled', 'scope', 'effective_revision', 'session_policy_revision', 'actor_type', 'audit_event_id')
        );

        return array(
            $this->definition(ToolName::GET_ANALYTICS_OVERVIEW, 'Get analytics overview', 'Read workflow, tool, commerce, attribution, and capability-gap totals for the authorized scope. Public demo results contain only this browser session.', 'agentsnr', RiskClass::READ, $read_schema, $overview_output, 'analytics.overview', true, false, false, 30),
            $this->definition(ToolName::GET_CONVERSION_FUNNEL, 'Get conversion funnel', 'Read the deterministic agent-commerce funnel for the authorized scope, including stage conversion, timing, and recorded exits. Public demo results contain only this browser session.', 'agentsnr', RiskClass::READ, $read_schema, $funnel_output, 'analytics.funnel', true, false, false, 30),
            $this->definition(
                ToolName::QUERY_WORKFLOWS,
                'Query agent workflows',
                'List compact redacted workflow summaries for the authorized scope. Public demo results contain only this browser session and never include raw prompts or payloads.',
                'agentsnr',
                RiskClass::READ,
                $this->object(
                    array(
                        'status'    => array('type' => 'string', 'enum' => array('active', 'completed', 'abandoned', 'expired')),
                        'tool_name' => array('type' => 'string', 'maxLength' => 128),
                        'cursor'    => array('type' => 'string', 'maxLength' => 100),
                        'limit'     => array('type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20),
                    )
                ),
                $collection_output,
                'analytics.query_workflows',
                true,
                true,
                false,
                30
            ),
            $this->definition(
                ToolName::EXPLAIN_WORKFLOW,
                'Explain agent workflow',
                'Return a deterministic redacted timeline, first error, recovery, commerce outcome, attribution evidence, and capability gaps for one workflow in the authorized scope.',
                'agentsnr',
                RiskClass::READ,
                $this->object(array('workflow_id' => array('type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$')), array('workflow_id')),
                $explanation_output,
                'analytics.explain_workflow',
                true,
                true,
                false,
                30
            ),
            $this->definition(
                ToolName::GET_TOOL_HEALTH,
                'Get tool health',
                'Use this one call for per-tool calls, outcomes, latency, errors, commerce contribution, attribution, and policy state. It already contains the evidence needed to identify slow or failing tools; do not combine it with the overview unless the user also requests a sitewide summary.',
                'agentsnr',
                RiskClass::READ,
                $read_schema,
                $health_output,
                'analytics.tool_health',
                true,
                false,
                false,
                30
            ),
            $this->definition(
                ToolName::GET_CAPABILITY_GAPS,
                'Get capability gaps',
                'Legacy operator view of grouped unsupported requests. It is not publicly discoverable; ' .
                    'use get_opportunity_signals for current unified analysis. Product value is context, ' .
                    'never lost revenue.',
                'agentsnr',
                RiskClass::READ,
                $this->object(
                    array(
                        'status'     => array('type' => 'string', 'enum' => array('open', 'planned', 'resolved', 'dismissed')),
                        'product_id' => array('type' => 'integer', 'minimum' => 1),
                    )
                ),
                $collection_output,
                'analytics.capability_gaps',
                true,
                true,
                false,
                30,
                60,
                false
            ),
            $this->definition(
                ToolName::GET_OPPORTUNITY_SIGNALS,
                'Get opportunity signals',
                'One call returns unified demand, inventory, capability, and experience signals while ' .
                    'keeping agent testimony separate from site-observed evidence. Omit category and ' .
                    'source to include every segment; use filters only when the user asks to narrow the ' .
                    'result. This read changes no state.',
                'agentsnr',
                RiskClass::READ,
                $this->object(
                    array(
                        'category' => array('type' => 'string', 'enum' => array('demand_gap', 'inventory_gap', 'capability_gap', 'experience_friction', 'worked_well')),
                        'source'   => array('type' => 'string', 'enum' => array('site_observed', 'agent_reported')),
                        'status'   => array('type' => 'string', 'enum' => array('open', 'planned', 'resolved', 'dismissed')),
                        'date_from' => array('type' => 'string', 'format' => 'date'),
                        'date_to'  => array('type' => 'string', 'format' => 'date'),
                        'cursor'   => array('type' => 'string', 'maxLength' => 100),
                        'limit'    => array('type' => 'integer', 'minimum' => 1, 'maximum' => 8, 'default' => 8),
                    )
                ),
                $this->object(
                    array(
                        'scope'       => $open_object,
                        'items'       => array('type' => 'array', 'items' => $open_object),
                        'next_cursor' => array('type' => array('string', 'null')),
                        'has_more'    => array('type' => 'boolean'),
                        'limit'       => array('type' => 'integer', 'minimum' => 1),
                        'truncated'   => array('type' => 'boolean'),
                    ),
                    array('scope', 'items', 'next_cursor', 'has_more', 'limit', 'truncated')
                ),
                'analytics.opportunity_signals',
                true,
                true,
                false,
                30
            ),
            $this->definition(
                ToolName::RUN_DIAGNOSTICS,
                'Run WebMCP diagnostics',
                'Run read-only compatibility checks for browser context, manifest, REST, session isolation, WordPress, WooCommerce, HPOS, and database readiness without exposing secrets.',
                'agentsnr',
                RiskClass::READ,
                $this->object(
                    array(
                        'checks' => array(
                            'type'        => 'array',
                            'items'       => array('type' => 'string', 'enum' => array('manifest', 'rest', 'session', 'woocommerce', 'hpos', 'database', 'headers')),
                            'maxItems'    => 7,
                            'uniqueItems' => true,
                        ),
                    )
                ),
                $diagnostics_output,
                'diagnostics.run',
                true,
                false,
                false,
                10
            ),
            $this->definition(
                ToolName::SET_TOOL_ENABLED,
                'Set demo tool availability',
                'Use only when the user names one storefront tool and explicitly requests a change for this demo session. Do not call for permanent, sitewide, all-tools, or other-visitor requests; explain that boundary without making a change. This cannot enable a site-disabled tool.',
                'agentsnr',
                RiskClass::SESSION_WRITE,
                $this->object(
                    array(
                        'tool_name' => array('type' => 'string', 'enum' => ToolName::storefrontPublic()),
                        'enabled'   => array('type' => 'boolean'),
                        'scope'     => array('type' => 'string', 'enum' => array('demo_session')),
                        'reason'    => array('type' => 'string', 'minLength' => 3, 'maxLength' => 300),
                    ),
                    array('tool_name', 'enabled', 'scope', 'reason')
                ),
                $policy_output,
                'policy.set_tool_enabled',
                false,
                false,
                false,
                5,
                600
            ),
        );
    }

    /**
     * @param array<string, mixed> $input_schema Input JSON Schema.
     * @param array<string, mixed> $output_schema Output JSON Schema.
     * @return array<string, mixed>
     */
    private function definition(
        string $name,
        string $title,
        string $description,
        string $surface,
        string $risk,
        array $input_schema,
        array $output_schema,
        string $callback,
        bool $read_only,
        bool $untrusted_content,
        bool $requires_woocommerce,
        int $rate_limit,
        int $rate_window = 60,
        bool $discoverable = true
    ): array {
        return array(
            'ability_id'           => self::ABILITY_NAMESPACE . '/' . str_replace('_', '-', $name),
            'name'                 => $name,
            'title'                => $title,
            'description'          => $description,
            'surface'              => $surface,
            'risk_class'           => $risk,
            'version'              => '1.0.0',
            'input_schema'         => $this->describe_input_schema($input_schema),
            'output_schema'        => $output_schema,
            'callback'             => $callback,
            'read_only'            => $read_only,
            'untrusted_content'    => $untrusted_content,
            'requires_woocommerce' => $requires_woocommerce,
            'discoverable'         => $discoverable,
            'rate_limit'           => $rate_limit,
            'rate_window'          => $rate_window,
            'max_input_bytes'      => 8192,
            'max_output_bytes'     => 8192,
        );
    }

    /**
     * @param array<string, mixed> $properties Schema properties.
     * @param list<string>         $required Required property names.
     * @return array<string, mixed>
     */
    private function object(array $properties, array $required = array()): array
    {
        $schema = array(
            'type'                 => 'object',
            'additionalProperties' => false,
        );

        if (array() !== $properties) {
            $schema['properties'] = $properties;
        }

        if (array() !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Add canonical descriptions to every named input property, including
     * properties nested inside other objects or array items.
     *
     * @param array<string, mixed> $schema Input JSON Schema.
     * @return array<string, mixed>
     */
    private function describe_input_schema(array $schema): array
    {
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $property) {
                if (! is_string($name) || ! is_array($property)) {
                    continue;
                }

                if (! isset($property['description']) && isset(self::INPUT_PROPERTY_DESCRIPTIONS[$name])) {
                    $property['description'] = self::INPUT_PROPERTY_DESCRIPTIONS[$name];
                }

                $schema['properties'][$name] = $this->describe_input_schema($property);
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->describe_input_schema($schema['items']);
        }

        if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
            $schema['additionalProperties'] = $this->describe_input_schema($schema['additionalProperties']);
        }

        return $schema;
    }

    /**
     * Strict output contract shared by the Agent Guide Ability and manifest.
     *
     * @return array<string, mixed>
     */
    private function agent_guide_output_schema(): array
    {
        $string_list = array(
            'type'  => 'array',
            'items' => array('type' => 'string'),
        );
        $step_schema = $this->object(
            array(
                'id'             => array('type' => 'string'),
                'tools'          => $string_list,
                'effect'         => array(
                    'type' => 'string',
                    'enum' => array('answer', 'action', 'telemetry', 'human_checkpoint'),
                ),
                'human_required' => array('type' => 'boolean'),
                'optional'       => array('type' => 'boolean'),
            ),
            array('id', 'tools', 'effect', 'human_required', 'optional')
        );
        $journey_schema = $this->object(
            array(
                'id'        => array('type' => 'string'),
                'title'     => array('type' => 'string'),
                'available' => array('type' => 'boolean'),
                'steps'     => array('type' => 'array', 'items' => $step_schema, 'minItems' => 1),
            ),
            array('id', 'title', 'available', 'steps')
        );
        $effect_schema = $this->object(
            array(
                'id'                     => array(
                    'type' => 'string',
                    'enum' => array('answer', 'action', 'telemetry', 'human_checkpoint', 'sensitive_action'),
                ),
                'changes_commerce_state' => array('type' => 'boolean'),
                'reversible'              => array('type' => 'boolean'),
                'meaning'                 => array('type' => 'string'),
            ),
            array('id', 'changes_commerce_state', 'reversible', 'meaning')
        );
        $boundary_schema = $this->object(
            array(
                'at'         => array('type' => 'string'),
                'agent_may'  => $string_list,
                'human_must' => $string_list,
            ),
            array('at', 'agent_may', 'human_must')
        );

        return $this->object(
            array(
                'version'            => array('type' => 'string'),
                'start_here'         => array('type' => 'boolean'),
                'commerce_available' => array('type' => 'boolean'),
                'purpose'            => array('type' => 'string'),
                'execution'          => $this->object(
                    array(
                        'supported_mode'              => array(
                            'type' => 'string',
                            'enum' => array('top_level_co_browsing'),
                        ),
                        'unattended_remote_execution' => array(
                            'type' => 'string',
                            'enum' => array('unsupported'),
                        ),
                        'state_rule'                  => array('type' => 'string'),
                    ),
                    array('supported_mode', 'unattended_remote_execution', 'state_rule')
                ),
                'supported_journeys' => array(
                    'type'     => 'array',
                    'items'    => $journey_schema,
                    'minItems' => 2,
                    'maxItems' => 2,
                ),
                'trust'              => $this->object(
                    array(
                        'merchant_content' => array(
                            'type' => 'string',
                            'enum' => array('untrusted'),
                        ),
                        'agent_feedback'   => array(
                            'type' => 'string',
                            'enum' => array('agent_reported'),
                        ),
                        'site_metrics'     => array(
                            'type' => 'string',
                            'enum' => array('site_computed'),
                        ),
                        'effects'          => array(
                            'type'     => 'array',
                            'items'    => $effect_schema,
                            'minItems' => 5,
                            'maxItems' => 5,
                        ),
                    ),
                    array('merchant_content', 'agent_feedback', 'site_metrics', 'effects')
                ),
                'sensitive_actions'  => $this->object(
                    array(
                        'tool_count'  => array('type' => 'integer', 'minimum' => 0, 'maximum' => 0),
                        'tools'       => array('type' => 'array', 'items' => array('type' => 'string'), 'maxItems' => 0),
                        'human_owned' => $string_list,
                    ),
                    array('tool_count', 'tools', 'human_owned')
                ),
                'pricing_boundary'   => $this->object(
                    array(
                        'before_checkout' => array(
                            'type' => 'string',
                            'enum' => array('cart_subtotal_or_estimate'),
                        ),
                        'final_total_at'   => array(
                            'type' => 'string',
                            'enum' => array('human_checkout'),
                        ),
                        'may_include'      => $string_list,
                        'agent_rule'       => array('type' => 'string'),
                    ),
                    array('before_checkout', 'final_total_at', 'may_include', 'agent_rule')
                ),
                'human_boundaries'   => array(
                    'type'     => 'array',
                    'items'    => $boundary_schema,
                    'minItems' => 1,
                ),
                'privacy'            => $this->object(
                    array(
                        'stored'   => $string_list,
                        'excluded' => $string_list,
                    ),
                    array('stored', 'excluded')
                ),
                'feedback'           => $this->object(
                    array(
                        'optional'                 => array('type' => 'boolean'),
                        'tool'                     => array('type' => 'string'),
                        'recommended_when'         => $string_list,
                        'max_reports_per_workflow' => array('type' => 'integer', 'minimum' => 0),
                        'supported_metrics'        => $string_list,
                        'metric_rule'              => array('type' => 'string'),
                    ),
                    array(
                        'optional',
                        'tool',
                        'recommended_when',
                        'max_reports_per_workflow',
                        'supported_metrics',
                        'metric_rule',
                    )
                ),
            ),
            array(
                'version',
                'start_here',
                'commerce_available',
                'purpose',
                'execution',
                'supported_journeys',
                'trust',
                'sensitive_actions',
                'pricing_boundary',
                'human_boundaries',
                'privacy',
                'feedback',
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function product_summary_schema(): array
    {
        return $this->object(
            array(
                'id'             => array('type' => 'integer'),
                'name'           => array('type' => 'string'),
                'url'            => array('type' => 'string'),
                'price'          => array('type' => 'number'),
                'currency'       => array('type' => 'string'),
                'stock_status'   => array('type' => 'string'),
                'purchasable'    => array('type' => 'boolean'),
                'attributes'     => array('type' => 'object'),
                'return_days'    => array('type' => array('integer', 'null')),
                'evidence'       => array('type' => 'array'),
            ),
            array('id', 'name', 'url', 'price', 'currency', 'stock_status', 'purchasable', 'attributes', 'return_days', 'evidence')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function product_detail_schema(): array
    {
        $schema                       = $this->product_summary_schema();
        $schema['properties']['slug'] = array('type' => 'string');
        $schema['properties']['short_description'] = array('type' => 'string');
        $schema['properties']['images'] = array('type' => 'array', 'items' => array('type' => 'string'), 'maxItems' => 3);

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function cart_schema(): array
    {
        return $this->object(
            array(
                'items'              => array('type' => 'array', 'items' => array('type' => 'object')),
                'item_count'         => array('type' => 'integer'),
                'subtotal'           => array('type' => 'number'),
                'discount_total'     => array('type' => 'number'),
                'currency'           => array('type' => 'string'),
                'cart_revision'      => array('type' => 'string'),
                'checkout_ready'     => array('type' => 'boolean'),
            ),
            array('items', 'item_count', 'subtotal', 'discount_total', 'currency', 'cart_revision', 'checkout_ready')
        );
    }
}
