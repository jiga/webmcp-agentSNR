<?php

/**
 * Feedback evidence and server-computed metric tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Analytics;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentOps\Analytics\FeedbackMetricResolver;
use WPWebMCP\AgentOps\Contract\EventName;

final class FeedbackMetricResolverTest extends TestCase
{
    private const SESSION = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const WORKFLOW = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const EVENT = 'evt_01ARZ3NDEKTSV4RRFFQ69G5FAA';

    public function test_evidence_is_scoped_to_the_current_storefront_workflow(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets[] = array(
            array(
                'event_id'        => self::EVENT,
                'request_id'      => '550e8400-e29b-41d4-a716-446655440000',
                'event_name'      => EventName::PRODUCT_SEARCHED,
                'tool_name'       => 'search_products',
                'product_ids_json' => '[10,11]',
                'properties_json' => '{"result_count":2}',
                'occurred_at'     => '2026-08-31 12:00:00',
            ),
        );
        $resolver = new FeedbackMetricResolver($database);

        $rows = $resolver->evidence(self::SESSION, self::WORKFLOW, array(self::EVENT));

        self::assertSame(self::EVENT, $rows[0]['event_id']);
        self::assertStringContainsString("w.demo_session_hash = %s", $database->queries[0]['sql']);
        self::assertStringContainsString("w.surface = 'storefront'", $database->queries[0]['sql']);
        self::assertSame(self::SESSION, $database->queries[0]['args'][0]);
        self::assertSame(self::WORKFLOW, $database->queries[0]['args'][1]);
        self::assertSame(self::EVENT, $database->queries[0]['args'][2]);
    }

    public function test_evidence_rejects_duplicates_foreign_rows_and_feedback_recursion(): void
    {
        $resolver = new FeedbackMetricResolver(new ScriptedAnalyticsDatabase());

        try {
            $resolver->evidence(self::SESSION, self::WORKFLOW, array(self::EVENT, self::EVENT));
            self::fail('Duplicate evidence IDs must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('unique', $exception->getMessage());
        }

        $foreign = new ScriptedAnalyticsDatabase();
        $foreign->result_sets[] = array();
        $resolver = new FeedbackMetricResolver($foreign);
        try {
            $resolver->evidence(self::SESSION, self::WORKFLOW, array(self::EVENT));
            self::fail('Missing scoped evidence must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('not valid', $exception->getMessage());
        }

        $recursive = new ScriptedAnalyticsDatabase();
        $recursive->result_sets[] = array(
            array(
                'event_id'    => self::EVENT,
                'event_name'  => EventName::AGENT_FEEDBACK_REPORTED,
                'tool_name'   => 'report_agent_feedback',
                'occurred_at' => '2026-08-31 12:00:00',
            ),
        );
        $resolver = new FeedbackMetricResolver($recursive);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid');
        $resolver->evidence(self::SESSION, self::WORKFLOW, array(self::EVENT));
    }

    public function test_metrics_are_computed_from_site_evidence_and_keep_conversion_pending(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets = array(
            array(
                array('properties_json' => '{"result_count":0}'),
                array('properties_json' => '{"result_count":2,"highest_water_rating":"IPX4"}'),
            ),
            array(),
        );
        $database->var_results = array(1);
        $resolver = new FeedbackMetricResolver($database);

        $metrics = $resolver->resolve(
            self::WORKFLOW,
            array(
                'eligible_product_count',
                'highest_matching_water_rating',
                'search_refinement_count',
                'checkout_handoff',
                'checkout_conversion',
                'attributed_order_count',
            )
        );

        self::assertSame(array('value' => 2, 'status' => 'verified'), $metrics['eligible_product_count']);
        self::assertSame(array('value' => 'IPX4', 'status' => 'verified'), $metrics['highest_matching_water_rating']);
        self::assertSame(array('value' => 2, 'status' => 'verified'), $metrics['search_refinement_count']);
        self::assertSame(array('value' => true, 'status' => 'verified'), $metrics['checkout_handoff']);
        self::assertSame(array('value' => null, 'status' => 'pending'), $metrics['checkout_conversion']);
        self::assertSame(array('value' => null, 'status' => 'pending'), $metrics['attributed_order_count']);
    }

    public function test_paid_metrics_preserve_currency_and_never_accept_agent_values(): void
    {
        $database = new ScriptedAnalyticsDatabase();
        $database->result_sets = array(
            array(),
            array(
                array('currency' => 'EUR', 'orders' => '1', 'gross' => '75.000000', 'net' => '70.000000'),
                array('currency' => 'USD', 'orders' => '2', 'gross' => '138.000000', 'net' => '69.000000'),
            ),
        );
        $database->var_results = array(1);
        $resolver = new FeedbackMetricResolver($database);

        $metrics = $resolver->resolve(
            self::WORKFLOW,
            array('checkout_conversion', 'attributed_order_count', 'paid_order_value', 'net_attributed_value')
        );

        self::assertSame(array('value' => true, 'status' => 'verified'), $metrics['checkout_conversion']);
        self::assertSame(array('value' => 3, 'status' => 'verified'), $metrics['attributed_order_count']);
        self::assertSame(
            array(
                'value'  => array(
                    array('currency' => 'EUR', 'value' => 75.0),
                    array('currency' => 'USD', 'value' => 138.0),
                ),
                'status' => 'verified',
            ),
            $metrics['paid_order_value']
        );
        self::assertSame(69.0, $metrics['net_attributed_value']['value'][1]['value']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not supported');
        $resolver->resolve(self::WORKFLOW, array('agent_supplied_revenue'));
    }
}
