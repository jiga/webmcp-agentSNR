<?php

/**
 * Resolve a fixed metric vocabulary from first-party workflow evidence.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Analytics;

use InvalidArgumentException;
use JsonException;
use WPWebMCP\AgentSNR\Contract\EventName;
use WPWebMCP\AgentSNR\Support\Json;

final class FeedbackMetricResolver
{
    public const METRICS = array(
        'eligible_product_count',
        'highest_matching_water_rating',
        'search_refinement_count',
        'checkout_handoff',
        'checkout_conversion',
        'attributed_order_count',
        'paid_order_value',
        'net_attributed_value',
    );

    public function __construct(
        private readonly object $database,
        private readonly EventSchema $schema = new EventSchema()
    ) {
    }

    /**
     * Validate that all evidence belongs to the current storefront workflow.
     *
     * @param list<string> $event_ids Event IDs supplied by the agent.
     * @return list<array<string, mixed>>
     */
    public function evidence(string $session_hash_hex, string $workflow_id, array $event_ids): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $workflow_id      = $this->schema->workflow_id($workflow_id);
        if (array() === $event_ids || 6 < count($event_ids)) {
            throw new InvalidArgumentException('Feedback requires between one and six evidence event IDs.');
        }
        $normalized = array();
        foreach ($event_ids as $event_id) {
            if (! is_string($event_id)) {
                throw new InvalidArgumentException('Feedback evidence IDs must be strings.');
            }
            $normalized[] = $this->schema->event_id($event_id);
        }
        $normalized = array_values(array_unique($normalized));
        if (count($normalized) !== count($event_ids)) {
            throw new InvalidArgumentException('Feedback evidence IDs must be unique.');
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '%s'));
        $sql = $this->database->prepare(
            "SELECT e.event_id, e.request_id, e.event_name, e.tool_name, e.product_ids_json, e.properties_json, e.occurred_at
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE w.demo_session_hash = %s
               AND w.surface = 'storefront'
               AND w.id = %s
               AND e.event_id IN ({$placeholders})
             ORDER BY e.occurred_at ASC, e.id ASC",
            $session_hash_hex,
            $workflow_id,
            ...$normalized
        );
        $rows = $this->database->get_results($sql, ARRAY_A);
        $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : array();
        if (count($rows) !== count($normalized)) {
            throw new InvalidArgumentException('Feedback evidence is not valid for this workflow.');
        }
        foreach ($rows as $row) {
            if (
                EventName::AGENT_FEEDBACK_REPORTED === ($row['event_name'] ?? null)
                || 'report_agent_feedback' === ($row['tool_name'] ?? null)
            ) {
                throw new InvalidArgumentException('Feedback evidence is not valid for this workflow.');
            }
        }

        $request_ids = array_values(
            array_unique(
                array_filter(
                    array_map(static fn (array $row): ?string => is_string($row['request_id'] ?? null) ? $row['request_id'] : null, $rows)
                )
            )
        );
        $semantic_rows = array();
        if (array() !== $request_ids) {
            $request_placeholders = implode(',', array_fill(0, count($request_ids), '%s'));
            $semantic_sql = $this->database->prepare(
                "SELECT e.event_id, e.request_id, e.event_name, e.tool_name, e.product_ids_json, e.properties_json, e.occurred_at
                 FROM {$this->events_table()} e
                 INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
                 WHERE w.demo_session_hash = %s AND w.surface = 'storefront' AND w.id = %s
                   AND e.request_id IN ({$request_placeholders})
                   AND e.event_name IN (%s,%s,%s,%s)
                 ORDER BY e.occurred_at ASC, e.id ASC",
                $session_hash_hex,
                $workflow_id,
                ...array_merge(
                    $request_ids,
                    array(EventName::PRODUCT_SEARCHED, EventName::PRODUCTS_COMPARED, EventName::CHECKOUT_HANDOFF, EventName::CAPABILITY_GAP_REPORTED)
                )
            );
            $resolved = $this->database->get_results($semantic_sql, ARRAY_A);
            $semantic_rows = is_array($resolved) ? array_values(array_filter($resolved, 'is_array')) : array();
        }

        $meaningful = false;
        foreach (array_merge($rows, $semantic_rows) as $row) {
            $event_name = (string) ($row['event_name'] ?? '');
            if (
                in_array(
                    $event_name,
                    array(
                        EventName::TOOL_CALL_FAILED,
                        EventName::TOOL_CALL_CANCELLED,
                        EventName::TOOL_CALL_DENIED,
                        EventName::CAPABILITY_GAP_REPORTED,
                        EventName::CHECKOUT_HANDOFF,
                    ),
                    true
                )
            ) {
                $meaningful = true;
                break;
            }
            if (
                EventName::TOOL_CALL_SUCCEEDED === $event_name
                && 'report_capability_gap' === ($row['tool_name'] ?? null)
            ) {
                $meaningful = true;
                break;
            }
            $properties = $this->properties($row['properties_json'] ?? null);
            if (EventName::PRODUCT_SEARCHED === $event_name && isset($properties['result_count']) && 2 >= (int) $properties['result_count']) {
                $meaningful = true;
                break;
            }
            if (EventName::PRODUCTS_COMPARED === $event_name && 0 < (int) ($properties['missing_fact_count'] ?? 0)) {
                $meaningful = true;
                break;
            }
        }
        if (! $meaningful) {
            throw new InvalidArgumentException('Feedback evidence is not valid for this workflow.');
        }

        return array_merge($rows, $semantic_rows);
    }

    /**
     * @param list<string> $requested Fixed metric names.
     * @return array<string, array{value:mixed,status:string}>
     */
    public function resolve(string $workflow_id, array $requested): array
    {
        $workflow_id = $this->schema->workflow_id($workflow_id);
        $requested   = array_values(array_unique($requested));
        foreach ($requested as $metric) {
            if (! is_string($metric) || ! in_array($metric, self::METRICS, true)) {
                throw new InvalidArgumentException('Requested feedback metric is not supported.');
            }
        }

        $search_rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT properties_json
                 FROM {$this->events_table()}
                 WHERE workflow_id = %s AND event_name = %s
                 ORDER BY occurred_at ASC, id ASC
                 LIMIT 100",
                $workflow_id,
                EventName::PRODUCT_SEARCHED
            ),
            ARRAY_A
        );
        $search_rows = is_array($search_rows) ? array_values(array_filter($search_rows, 'is_array')) : array();
        $latest_search = array();
        foreach ($search_rows as $row) {
            $properties = $this->properties($row['properties_json'] ?? null);
            if (array() !== $properties) {
                $latest_search = $properties;
            }
        }

        $handoff_count = (int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COUNT(*) FROM {$this->events_table()} WHERE workflow_id = %s AND event_name = %s",
                $workflow_id,
                EventName::CHECKOUT_HANDOFF
            )
        );
        $commerce_rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT currency, COUNT(DISTINCT order_id) AS orders,
                        COALESCE(SUM(gross_value),0) AS gross, COALESCE(SUM(net_value),0) AS net
                 FROM {$this->order_links_table()}
                 WHERE workflow_id = %s
                 GROUP BY currency
                 ORDER BY currency ASC",
                $workflow_id
            ),
            ARRAY_A
        );
        $commerce_rows = is_array($commerce_rows) ? array_values(array_filter($commerce_rows, 'is_array')) : array();
        $order_count   = array_sum(array_map(static fn (array $row): int => (int) ($row['orders'] ?? 0), $commerce_rows));

        $result = array();
        foreach ($requested as $metric) {
            if ('eligible_product_count' === $metric) {
                $result[$metric] = array_key_exists('result_count', $latest_search)
                    ? $this->metric((int) $latest_search['result_count'], 'verified')
                    : $this->metric(null, 'unavailable');
            } elseif ('highest_matching_water_rating' === $metric) {
                $result[$metric] = isset($latest_search['highest_water_rating']) && is_string($latest_search['highest_water_rating'])
                    ? $this->metric($latest_search['highest_water_rating'], 'verified')
                    : $this->metric(null, 'unavailable');
            } elseif ('search_refinement_count' === $metric) {
                $result[$metric] = $this->metric(count($search_rows), 'verified');
            } elseif ('checkout_handoff' === $metric) {
                $result[$metric] = $this->metric(0 < $handoff_count, 'verified');
            } elseif (0 === $order_count) {
                $result[$metric] = $this->metric(null, 0 < $handoff_count ? 'pending' : 'unavailable');
            } elseif ('checkout_conversion' === $metric) {
                $result[$metric] = $this->metric(true, 'verified');
            } elseif ('attributed_order_count' === $metric) {
                $result[$metric] = $this->metric($order_count, 'verified');
            } elseif ('paid_order_value' === $metric) {
                $result[$metric] = $this->metric($this->currency_values($commerce_rows, 'gross'), 'verified');
            } else {
                $result[$metric] = $this->metric($this->currency_values($commerce_rows, 'net'), 'verified');
            }
        }

        return $result;
    }

    /** @param mixed $json Encoded properties. */
    private function properties($json): array
    {
        if (! is_string($json) || '' === $json) {
            return array();
        }
        try {
            return Json::decode_object($json);
        } catch (JsonException | InvalidArgumentException) {
            return array();
        }
    }

    /** @return array{value:mixed,status:string} */
    private function metric(mixed $value, string $status): array
    {
        return array('value' => $value, 'status' => $status);
    }

    /**
     * @param list<array<string, mixed>> $rows Currency rows.
     * @return list<array{currency:string,value:float}>
     */
    private function currency_values(array $rows, string $column): array
    {
        return array_map(
            static fn (array $row): array => array(
                'currency' => (string) ($row['currency'] ?? ''),
                'value'    => round((float) ($row[$column] ?? 0), 6),
            ),
            $rows
        );
    }

    private function workflows_table(): string
    {
        return $this->database->prefix . 'wmcp_workflows';
    }

    private function events_table(): string
    {
        return $this->database->prefix . 'wmcp_events';
    }

    private function order_links_table(): string
    {
        return $this->database->prefix . 'wmcp_order_links';
    }
}
