<?php

/**
 * Event-schema tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Analytics;

use InvalidArgumentException;
use WPWebMCP\AgentSNR\Analytics\EventSchema;
use WPWebMCP\AgentSNR\Contract\EventName;

require_once __DIR__ . '/AnalyticsTestCase.php';

final class EventSchemaTest extends AnalyticsTestCase
{
    public function test_identifiers_are_strict_and_normalized(): void
    {
        $schema = new EventSchema();

        self::assertSame(str_repeat('a', 64), $schema->hash_hex(str_repeat('A', 64)));
        self::assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            $schema->request_id('550E8400-E29B-41D4-A716-446655440000')
        );
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $schema->workflow_id('01arz3ndektsv4rrffq69g5fav'));
    }

    public function test_request_ids_must_be_uuid36(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new EventSchema())->request_id('req_01ARZ3NDEKTSV4RRFFQ69G5FAV');
    }

    public function test_properties_are_allowlisted_and_prohibited_keys_are_removed(): void
    {
        $properties = (new EventSchema())->properties(
            EventName::TOOL_CALL_STARTED,
            array(
                'quantity' => 2,
                'prompt'   => 'raw shopper prompt',
                'email'    => 'person@example.test',
                'unknown'  => 'not part of the event schema',
            )
        );

        self::assertSame(array('quantity' => 2), $properties);
    }

    public function test_opportunity_and_feedback_events_keep_only_bounded_provenance_fields(): void
    {
        $schema = new EventSchema();
        $opportunity = $schema->properties(
            EventName::OPPORTUNITY_DETECTED,
            array(
                'signal_id'       => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'signal_source'   => 'site_observed',
                'signal_category' => 'demand_gap',
                'signal_code'     => 'zero_results',
                'signal_key'      => str_repeat('a', 64),
                'result_count'    => 0,
                'prompt'          => 'find a private medical product',
                'email'           => 'shopper@example.test',
                'query'           => 'raw visitor query',
            )
        );
        $feedback = $schema->properties(
            EventName::AGENT_FEEDBACK_REPORTED,
            array(
                'feedback_id'      => '01ARZ3NDEKTSV4RRFFQ69G5FAA',
                'feedback_outcome' => 'partial',
                'feedback_type'    => 'constraint_encountered',
                'evidence_count'   => 2,
                'evidence_status'  => 'linked',
                'metric_count'     => 3,
                'reason_code'      => 'low_coverage',
                'step_id'          => 'discovery',
                'suggested_action' => 'improve_product_coverage',
                'comment'          => 'Call +1 415 555 0199',
                'metric_values'    => array('paid_order_value' => 999999),
            )
        );

        self::assertSame(
            array(
                'result_count'    => 0,
                'signal_category' => 'demand_gap',
                'signal_code'     => 'zero_results',
                'signal_id'       => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'signal_key'      => str_repeat('a', 64),
                'signal_source'   => 'site_observed',
            ),
            $opportunity
        );
        self::assertSame(
            array(
                'evidence_count'   => 2,
                'evidence_status'  => 'linked',
                'feedback_id'      => '01ARZ3NDEKTSV4RRFFQ69G5FAA',
                'feedback_outcome' => 'partial',
                'feedback_type'    => 'constraint_encountered',
                'metric_count'     => 3,
                'reason_code'      => 'low_coverage',
                'step_id'          => 'discovery',
                'suggested_action' => 'improve_product_coverage',
            ),
            $feedback
        );
    }

    public function test_terminal_dedupe_is_independent_of_terminal_outcome(): void
    {
        $schema = new EventSchema();
        $workflow_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $request_id  = '550e8400-e29b-41d4-a716-446655440000';

        self::assertSame(
            $schema->dedupe_key('tool-terminal', $workflow_id, $request_id),
            $schema->dedupe_key('tool-terminal', $workflow_id, $request_id)
        );
    }
}
