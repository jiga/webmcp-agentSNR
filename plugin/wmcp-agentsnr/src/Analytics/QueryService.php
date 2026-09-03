<?php

/**
 * Session-scoped, redacted Agent SNR analytics queries.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use WPWebMCP\AgentSNR\Contract\EventName;
use WPWebMCP\AgentSNR\Support\Json;

final class QueryService
{
    private const MAX_TIMELINE_EVENTS = 20;
    private const TIMELINE_HEAD_EVENTS = 8;
    private const TIMELINE_TAIL_EVENTS = 12;
    private const MAX_REPLAY_ORDERS = 4;
    private const MAX_REPLAY_GAPS = 6;
    private const MAX_EVENT_PROPERTIES = 5;
    private const MAX_PROPERTY_STRING_LENGTH = 120;
    private const REPLAY_RESULT_BUDGET = 7000;
    private const MAX_HEALTH_EVENTS = 10000;

    private object $database;

    private EventSchema $schema;

    /** @var callable(string,string):?bool|null */
    private $tool_enabled_resolver;

    private ?SignalService $signals;

    /**
     * @param object|null                 $database              wpdb-compatible database object.
     * @param callable(string,string):?bool|null $tool_enabled_resolver Optional current policy lookup.
     */
    public function __construct(
        ?object $database = null,
        ?EventSchema $schema = null,
        ?callable $tool_enabled_resolver = null,
        ?SignalService $signals = null
    ) {
        if (null === $database) {
            global $wpdb;
            $database = $wpdb;
        }
        if (! is_object($database)) {
            throw new InvalidArgumentException('A wpdb-compatible database object is required.');
        }

        $this->database              = $database;
        $this->schema                = $schema ?? new EventSchema();
        $this->tool_enabled_resolver = $tool_enabled_resolver;
        $this->signals               = $signals;
    }

    /**
     * Return compact storefront workflow, invocation, commerce, and revenue totals.
     *
     * @param array<string, mixed> $filters Date and optional tool filters.
     * @return array<string, mixed>
     */
    public function overview(string $session_hash_hex, array $filters = array()): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $filters          = $this->filters($filters);
        [$scope, $args]   = $this->workflow_scope($session_hash_hex, $filters, 'w');
        if (null !== $filters['tool_name']) {
            $scope .= " AND EXISTS (
                SELECT 1 FROM {$this->events_table()} scoped_tool
                WHERE scoped_tool.workflow_id = w.id AND scoped_tool.tool_name = %s
            )";
            $args[] = $filters['tool_name'];
        }

        $workflow_row = $this->row(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN w.status = 'active' THEN 1 ELSE 0 END), 0) AS active,
                    COALESCE(SUM(CASE WHEN w.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
                    COALESCE(SUM(CASE WHEN w.status = 'abandoned' THEN 1 ELSE 0 END), 0) AS abandoned,
                    COALESCE(SUM(CASE WHEN w.status = 'expired' THEN 1 ELSE 0 END), 0) AS expired
             FROM {$this->workflows_table()} w
             WHERE {$scope}",
            $args
        );

        $event_where = $scope;
        $event_args  = $args;
        if (null !== $filters['tool_name']) {
            $event_where .= ' AND e.tool_name = %s';
            $event_args[] = $filters['tool_name'];
        }
        $invocation_row = $this->row(
            "SELECT COALESCE(SUM(CASE WHEN e.event_name = 'tool.call.started' THEN 1 ELSE 0 END), 0) AS calls,
                    COALESCE(SUM(CASE WHEN e.event_name = 'tool.call.succeeded' THEN 1 ELSE 0 END), 0) AS succeeded,
                    COALESCE(SUM(CASE WHEN e.event_name = 'tool.call.failed' THEN 1 ELSE 0 END), 0) AS failed,
                    COALESCE(SUM(CASE WHEN e.event_name = 'tool.call.cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
                    COALESCE(SUM(CASE WHEN e.event_name = 'tool.call.denied' THEN 1 ELSE 0 END), 0) AS denied,
                    COUNT(DISTINCT CASE WHEN e.event_name = 'tool.call.started' THEN e.workflow_id ELSE NULL END) AS workflows
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE {$event_where}",
            $event_args
        );

        $duration_sql = "SELECT e.duration_ms
                         FROM {$this->events_table()} e
                         INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
                         WHERE {$event_where}
                           AND e.event_name IN ('tool.call.succeeded','tool.call.failed','tool.call.cancelled','tool.call.denied')
                           AND e.duration_ms IS NOT NULL
                         ORDER BY e.duration_ms ASC
                         LIMIT " . (self::MAX_HEALTH_EVENTS + 1);
        $duration_rows = $this->results($duration_sql, $event_args);
        $durations     = array_map(static fn (array $row): int => (int) $row['duration_ms'], array_slice($duration_rows, 0, self::MAX_HEALTH_EVENTS));

        $commerce_row = $this->row(
            "SELECT COALESCE(SUM(CASE WHEN e.event_name = 'commerce.product.searched' THEN 1 ELSE 0 END), 0) AS product_searches,
                    COALESCE(SUM(CASE WHEN e.event_name = 'commerce.cart.changed' THEN 1 ELSE 0 END), 0) AS cart_mutations,
                    COALESCE(SUM(CASE WHEN e.event_name = 'commerce.checkout.handoff' THEN 1 ELSE 0 END), 0) AS checkout_handoffs,
                    COALESCE(SUM(CASE WHEN e.event_name = 'commerce.order.created' THEN 1 ELSE 0 END), 0) AS orders_created,
                    COALESCE(SUM(CASE WHEN e.event_name = 'commerce.order.paid' THEN 1 ELSE 0 END), 0) AS orders_paid,
                    COALESCE(SUM(CASE WHEN e.event_name = 'commerce.order.refunded' THEN 1 ELSE 0 END), 0) AS refunds
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE {$scope}",
            $args
        );

        $attribution_rows = $this->results(
            "SELECT ol.attribution_class, ol.currency,
                    COUNT(DISTINCT ol.order_id) AS orders,
                    COALESCE(SUM(ol.gross_value), 0) AS gross,
                    COALESCE(SUM(ol.refund_value), 0) AS refunds,
                    COALESCE(SUM(ol.net_value), 0) AS net
             FROM {$this->order_links_table()} ol
             INNER JOIN {$this->workflows_table()} w ON w.id = ol.workflow_id
             WHERE {$scope}
             GROUP BY ol.attribution_class, ol.currency
             ORDER BY ol.attribution_class ASC, ol.currency ASC",
            $args
        );
        $attribution = array();
        $revenue     = array('orders' => 0, 'by_currency' => array());
        foreach ($attribution_rows as $row) {
            $class    = (string) $row['attribution_class'];
            $currency = (string) $row['currency'];
            $item  = array(
                'orders'  => (int) $row['orders'],
                'gross'   => (float) $row['gross'],
                'refunds' => (float) $row['refunds'],
                'net'     => (float) $row['net'],
            );
            if (! isset($attribution[$class])) {
                $attribution[$class] = array('orders' => 0, 'by_currency' => array());
            }
            $attribution[$class]['orders'] += $item['orders'];
            $attribution[$class]['by_currency'][$currency] = $item;
            $revenue['orders'] += $item['orders'];
            if (! isset($revenue['by_currency'][$currency])) {
                $revenue['by_currency'][$currency] = array('orders' => 0, 'gross' => 0.0, 'refunds' => 0.0, 'net' => 0.0);
            }
            $revenue['by_currency'][$currency]['orders']  += $item['orders'];
            $revenue['by_currency'][$currency]['gross']   += $item['gross'];
            $revenue['by_currency'][$currency]['refunds'] += $item['refunds'];
            $revenue['by_currency'][$currency]['net']     += $item['net'];
        }
        foreach ($revenue['by_currency'] as &$currency_totals) {
            $currency_totals['gross']   = round($currency_totals['gross'], 6);
            $currency_totals['refunds'] = round($currency_totals['refunds'], 6);
            $currency_totals['net']     = round($currency_totals['net'], 6);
        }
        unset($currency_totals);

        [$gap_where, $gap_args] = $this->gap_scope($session_hash_hex, $filters, 'g');
        if (null !== $filters['tool_name']) {
            $gap_where .= " AND EXISTS (
                SELECT 1 FROM {$this->events_table()} gap_tool
                WHERE gap_tool.workflow_id = g.workflow_id AND gap_tool.tool_name = %s
            )";
            $gap_args[] = $filters['tool_name'];
        }
        $gap_row = $this->row(
            "SELECT COUNT(*) AS requests, COUNT(DISTINCT g.workflow_id) AS workflows, COUNT(DISTINCT g.capability_slug) AS capabilities
             FROM {$this->gaps_table()} g
             WHERE {$gap_where}",
            $gap_args
        );

        $policy_where = "w.demo_session_hash = %s AND e.event_name = 'policy.changed'";
        $policy_args  = array($session_hash_hex);
        if (null !== $filters['date_from']) {
            $policy_where .= ' AND e.occurred_at >= %s';
            $policy_args[] = $filters['date_from'];
        }
        if (null !== $filters['date_to']) {
            $policy_where .= ' AND e.occurred_at < %s';
            $policy_args[] = $filters['date_to'];
        }
        $policy_row = $this->row(
            "SELECT COUNT(*) AS changes
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE {$policy_where}",
            $policy_args
        );

        $calls = (int) ($invocation_row['calls'] ?? 0);
        $signal_summary = null === $this->signals
            ? array('available' => false, 'reason' => 'signal_service_unavailable', 'observed' => null, 'agent_reported' => null, 'workflows' => null, 'categories' => null)
            : $this->signals->summary($session_hash_hex, $filters);

        return array(
            'scope' => array(
                'surface'   => 'storefront',
                'date_from' => $filters['date_from'],
                'date_to'   => $filters['date_to'],
                'tool_name' => $filters['tool_name'],
            ),
            'workflows' => array(
                'total'     => (int) ($workflow_row['total'] ?? 0),
                'active'    => (int) ($workflow_row['active'] ?? 0),
                'completed' => (int) ($workflow_row['completed'] ?? 0),
                'abandoned' => (int) ($workflow_row['abandoned'] ?? 0),
                'expired'   => (int) ($workflow_row['expired'] ?? 0),
            ),
            'tool_calls' => array(
                'total'             => $calls,
                'workflows'         => (int) ($invocation_row['workflows'] ?? 0),
                'succeeded'         => (int) ($invocation_row['succeeded'] ?? 0),
                'failed'            => (int) ($invocation_row['failed'] ?? 0),
                'cancelled'         => (int) ($invocation_row['cancelled'] ?? 0),
                'denied'            => (int) ($invocation_row['denied'] ?? 0),
                'success_rate'      => $this->rate((int) ($invocation_row['succeeded'] ?? 0), $calls),
                'failure_rate'      => $this->rate((int) ($invocation_row['failed'] ?? 0), $calls),
                'cancellation_rate' => $this->rate((int) ($invocation_row['cancelled'] ?? 0), $calls),
                'denial_rate'       => $this->rate((int) ($invocation_row['denied'] ?? 0), $calls),
                'p50_duration_ms'   => $this->percentile($durations, 0.50),
                'p95_duration_ms'   => $this->percentile($durations, 0.95),
                'latency_truncated' => count($duration_rows) > self::MAX_HEALTH_EVENTS,
            ),
            'commerce' => array_map('intval', $commerce_row),
            'revenue' => array(
                'attribution' => $attribution,
                'orders'      => $revenue['orders'],
                'by_currency' => $revenue['by_currency'],
            ),
            'capability_gaps' => array(
                'requests'     => (int) ($gap_row['requests'] ?? 0),
                'workflows'    => (int) ($gap_row['workflows'] ?? 0),
                'capabilities' => (int) ($gap_row['capabilities'] ?? 0),
            ),
            'signals' => $signal_summary,
            'policy_changes' => (int) ($policy_row['changes'] ?? 0),
        );
    }

    /**
     * Return cursor-paginated, compact workflow summaries.
     *
     * @param array<string, mixed> $filters Status/tool/date/cursor/limit filters.
     * @return array<string, mixed>
     */
    public function workflows(string $session_hash_hex, array $filters = array()): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $filters          = $this->filters($filters, true);
        [$where, $args]   = $this->workflow_scope($session_hash_hex, $filters, 'w');

        if (null !== $filters['status']) {
            $where .= ' AND w.status = %s';
            $args[] = $filters['status'];
        }
        if (null !== $filters['tool_name']) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$this->events_table()} te
                WHERE te.workflow_id = w.id AND te.tool_name = %s
            )";
            $args[] = $filters['tool_name'];
        }
        if (null !== $filters['cursor']) {
            $cursor = $this->decode_cursor($filters['cursor']);
            $where .= ' AND (w.last_event_at < %s OR (w.last_event_at = %s AND w.id < %s))';
            array_push($args, $cursor['time'], $cursor['time'], $cursor['id']);
        }

        $fetch_limit = $filters['limit'] + 1;
        $rows = $this->results(
            "SELECT w.id, w.protocol, w.surface, w.status, w.started_at, w.ended_at, w.last_event_at, w.tool_count,
                    CASE WHEN w.wc_session_hash IS NULL THEN 0 ELSE 1 END AS commerce_session
             FROM {$this->workflows_table()} w
             WHERE {$where}
             ORDER BY w.last_event_at DESC, w.id DESC
             LIMIT {$fetch_limit}",
            $args
        );

        $has_more = count($rows) > $filters['limit'];
        $rows     = array_slice($rows, 0, $filters['limit']);
        $ids      = array_values(array_map(static fn (array $row): string => (string) $row['id'], $rows));
        $last_events = $this->last_events($session_hash_hex, $ids);
        $orders      = $this->workflow_orders($session_hash_hex, $ids);

        $items = array();
        foreach ($rows as $row) {
            $id   = (string) $row['id'];
            $last = $last_events[$id] ?? null;
            $order = $orders[$id] ?? array('orders' => 0, 'by_currency' => array());
            $items[] = array(
                'workflow_id'     => $id,
                'protocol'        => (string) $row['protocol'],
                'surface'         => (string) $row['surface'],
                'status'          => (string) $row['status'],
                'started_at'      => (string) $row['started_at'],
                'ended_at'        => null === $row['ended_at'] ? null : (string) $row['ended_at'],
                'last_event_at'   => (string) $row['last_event_at'],
                'tool_count'      => (int) $row['tool_count'],
                'commerce_session' => (bool) $row['commerce_session'],
                'last_event'      => $last,
                'commerce'        => $order,
            );
        }

        $next_cursor = null;
        if ($has_more && array() !== $rows) {
            $last_row    = $rows[count($rows) - 1];
            $next_cursor = $this->encode_cursor((string) $last_row['last_event_at'], (string) $last_row['id']);
        }

        return array(
            'items'       => $items,
            'next_cursor' => $next_cursor,
            'has_more'    => $has_more,
            'limit'       => $filters['limit'],
        );
    }

    /**
     * Deterministically explain one workflow without exposing raw input/output.
     *
     * @return array<string, mixed>|null
     */
    public function explain(string $session_hash_hex, string $workflow_id): ?array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $workflow_id      = $this->schema->workflow_id($workflow_id);
        $workflow = $this->row(
            "SELECT w.id, w.protocol, w.surface, w.status, w.started_at, w.ended_at, w.last_event_at, w.tool_count
             FROM {$this->workflows_table()} w
             WHERE w.demo_session_hash = %s AND w.surface = 'storefront' AND w.id = %s
             LIMIT 1",
            array($session_hash_hex, $workflow_id)
        );
        if (array() === $workflow) {
            return null;
        }

        $event_columns = 'e.id AS row_id, e.event_id, e.event_name, e.request_id, e.step_number, e.tool_name, e.tool_version, e.risk_class,
                e.outcome, e.duration_ms, e.error_code, e.http_status, e.product_ids_json, e.currency, e.value,
                e.properties_json, e.occurred_at';
        $event_scope = "FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE w.demo_session_hash = %s AND w.surface = 'storefront' AND w.id = %s";
        $scope_args = array($session_hash_hex, $workflow_id);
        $head_rows  = $this->results(
            "SELECT {$event_columns} {$event_scope}
             ORDER BY e.occurred_at ASC, e.id ASC
             LIMIT " . (self::TIMELINE_HEAD_EVENTS + 1),
            $scope_args
        );
        $tail_rows = $this->results(
            "SELECT {$event_columns} {$event_scope}
             ORDER BY e.occurred_at DESC, e.id DESC
             LIMIT " . (self::TIMELINE_TAIL_EVENTS + 1),
            $scope_args
        );

        $timeline_rows = array();
        foreach (array_merge($head_rows, $tail_rows) as $row) {
            $timeline_rows[(string) $row['event_id']] = $row;
        }
        $timeline_rows = array_values($timeline_rows);
        usort(
            $timeline_rows,
            static fn (array $left, array $right): int => array((string) $left['occurred_at'], (int) $left['row_id'])
                <=> array((string) $right['occurred_at'], (int) $right['row_id'])
        );
        $truncated = count($timeline_rows) > self::MAX_TIMELINE_EVENTS;
        if ($truncated) {
            $timeline_rows = array_merge(
                array_slice($timeline_rows, 0, self::TIMELINE_HEAD_EVENTS),
                array_slice($timeline_rows, -self::TIMELINE_TAIL_EVENTS)
            );
        }
        $timeline = array();
        foreach ($timeline_rows as $row) {
            $timeline[] = $this->public_replay_event($row, $truncated);
        }

        $first_problem_row = $this->row(
            "SELECT {$event_columns} {$event_scope}
               AND e.event_name IN ('tool.call.failed','tool.call.cancelled','tool.call.denied')
             ORDER BY e.occurred_at ASC, e.id ASC LIMIT 1",
            $scope_args
        );
        $first_problem = array() === $first_problem_row
            ? null
            : $this->public_replay_event($first_problem_row, $truncated);

        $recovery = null;
        if (array() !== $first_problem_row) {
            $recovery_row = $this->row(
                "SELECT {$event_columns} {$event_scope}
                   AND e.event_name = 'tool.call.succeeded'
                   AND (e.occurred_at > %s OR (e.occurred_at = %s AND e.id > %d))
                 ORDER BY e.occurred_at ASC, e.id ASC LIMIT 1",
                array_merge(
                    $scope_args,
                    array(
                        (string) $first_problem_row['occurred_at'],
                        (string) $first_problem_row['occurred_at'],
                        (int) $first_problem_row['row_id'],
                    )
                )
            );
            if (array() !== $recovery_row) {
                $recovery = $this->public_replay_event($recovery_row, $truncated);
            }
        }

        $last_commerce_row = $this->row(
            "SELECT {$event_columns} {$event_scope}
               AND e.event_name LIKE 'commerce.%'
             ORDER BY e.occurred_at DESC, e.id DESC LIMIT 1",
            $scope_args
        );
        $last_commerce = array() === $last_commerce_row
            ? null
            : $this->public_replay_event($last_commerce_row, $truncated);

        $order_rows = $this->results(
            "SELECT ol.order_id, ol.attribution_class, ol.confidence, ol.rule_version, ol.gross_value, ol.refund_value,
                    ol.net_value, ol.currency, ol.matched_products_json, ol.evidence_event_ids_json, ol.updated_at
             FROM {$this->order_links_table()} ol
             INNER JOIN {$this->workflows_table()} w ON w.id = ol.workflow_id
             WHERE w.demo_session_hash = %s AND w.surface = 'storefront' AND w.id = %s
             ORDER BY ol.updated_at DESC, ol.order_id DESC
             LIMIT " . (self::MAX_REPLAY_ORDERS + 1),
            array($session_hash_hex, $workflow_id)
        );
        if (count($order_rows) > self::MAX_REPLAY_ORDERS) {
            $truncated = true;
        }
        $order_rows = array_reverse(array_slice($order_rows, 0, self::MAX_REPLAY_ORDERS));
        $orders = array();
        foreach ($order_rows as $row) {
            $orders[] = $this->public_order($row, $truncated);
        }

        $gap_rows = $this->results(
            "SELECT g.id, g.capability_slug, g.related_product_id, g.status, g.occurred_at
             FROM {$this->gaps_table()} g
             WHERE g.demo_session_hash = %s AND g.workflow_id = %s
               AND (g.signal_category = 'capability_gap' OR g.signal_category IS NULL)
             ORDER BY g.occurred_at DESC, g.id DESC
             LIMIT " . (self::MAX_REPLAY_GAPS + 1),
            array($session_hash_hex, $workflow_id)
        );
        if (count($gap_rows) > self::MAX_REPLAY_GAPS) {
            $truncated = true;
        }
        $gap_rows = array_reverse(array_slice($gap_rows, 0, self::MAX_REPLAY_GAPS));
        $gaps = array_map(
            static fn (array $row): array => array(
                'gap_id'             => (string) $row['id'],
                'capability'         => (string) $row['capability_slug'],
                'related_product_id' => null === $row['related_product_id'] ? null : (int) $row['related_product_id'],
                'status'             => (string) $row['status'],
                'occurred_at'        => (string) $row['occurred_at'],
            ),
            $gap_rows
        );
        $signal_replay = null === $this->signals
            ? array('opportunity_signals' => array(), 'agent_feedback' => array())
            : $this->signals->for_workflow($session_hash_hex, $workflow_id);

        $result = array(
            'workflow' => array(
                'workflow_id'   => (string) $workflow['id'],
                'protocol'      => (string) $workflow['protocol'],
                'surface'       => (string) $workflow['surface'],
                'status'        => (string) $workflow['status'],
                'started_at'    => (string) $workflow['started_at'],
                'ended_at'      => null === $workflow['ended_at'] ? null : (string) $workflow['ended_at'],
                'last_event_at' => (string) $workflow['last_event_at'],
                'tool_count'    => (int) $workflow['tool_count'],
                'goal_source'   => 'unknown',
            ),
            'explanation' => $this->explanation_text(
                $workflow,
                $first_problem,
                $recovery,
                $orders,
                $gaps,
                $signal_replay['opportunity_signals'],
                $signal_replay['agent_feedback']
            ),
            'first_problem' => $first_problem,
            'recovery' => $recovery,
            'commerce_outcome' => array(
                'last_event' => $last_commerce,
                'orders'     => $orders,
            ),
            'capability_gaps' => $gaps,
            'opportunity_signals' => $signal_replay['opportunity_signals'],
            'agent_feedback'      => $signal_replay['agent_feedback'],
            'timeline'        => $timeline,
            'truncated'       => $truncated,
        );

        $result = $this->fit_replay_result($result);
        $result['explanation'] = $this->explanation_text(
            $workflow,
            $result['first_problem'],
            $result['recovery'],
            $result['commerce_outcome']['orders'],
            $result['capability_gaps'],
            $result['opportunity_signals'],
            $result['agent_feedback']
        );

        return $result;
    }

    /**
     * Return per-tool invocation health and commerce contribution.
     *
     * @param array<string, mixed> $filters Date/tool filters.
     * @return array<string, mixed>
     */
    public function tool_health(string $session_hash_hex, array $filters = array()): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $filters          = $this->filters($filters);
        [$scope, $args]   = $this->workflow_scope($session_hash_hex, $filters, 'w');
        if (null !== $filters['tool_name']) {
            $scope .= ' AND e.tool_name = %s';
            $args[] = $filters['tool_name'];
        }

        $rows = $this->results(
            "SELECT e.workflow_id, e.tool_name, e.tool_version, e.event_name, e.duration_ms, e.error_code
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE {$scope}
               AND e.event_name IN ('tool.call.succeeded','tool.call.failed','tool.call.denied')
             ORDER BY e.occurred_at ASC, e.id ASC
             LIMIT " . (self::MAX_HEALTH_EVENTS + 1),
            $args
        );
        $truncated = count($rows) > self::MAX_HEALTH_EVENTS;
        $rows      = array_slice($rows, 0, self::MAX_HEALTH_EVENTS);
        $groups    = array();
        foreach ($rows as $row) {
            $name    = (string) $row['tool_name'];
            $version = (string) $row['tool_version'];
            $key     = $name . "\0" . $version;
            if (! isset($groups[$key])) {
                $groups[$key] = array(
                    'name' => $name,
                    'version' => $version,
                    'calls' => 0,
                    'workflows' => array(),
                    'succeeded' => 0,
                    'failed' => 0,
                    'denied' => 0,
                    'durations' => array(),
                    'errors' => array(),
                );
            }
            ++$groups[$key]['calls'];
            $groups[$key]['workflows'][(string) $row['workflow_id']] = true;
            $bucket = match ((string) $row['event_name']) {
                EventName::TOOL_CALL_SUCCEEDED => 'succeeded',
                EventName::TOOL_CALL_FAILED => 'failed',
                EventName::TOOL_CALL_DENIED => 'denied',
            };
            ++$groups[$key][$bucket];
            if (null !== $row['duration_ms']) {
                $groups[$key]['durations'][] = (int) $row['duration_ms'];
            }
            if (null !== $row['error_code']) {
                $code = (string) $row['error_code'];
                $groups[$key]['errors'][$code] = ($groups[$key]['errors'][$code] ?? 0) + 1;
            }
        }

        $commerce = $this->tool_commerce($session_hash_hex, $filters);
        $revenue  = $this->tool_revenue($session_hash_hex, $filters);
        $items    = array();
        foreach ($groups as $group) {
            arsort($group['errors']);
            $top_errors = array();
            foreach (array_slice($group['errors'], 0, 3, true) as $code => $count) {
                $top_errors[] = array('code' => $code, 'count' => $count);
            }
            $calls = (int) $group['calls'];
            $items[] = array(
                'tool_name'          => $group['name'],
                'version'            => $group['version'],
                'calls'              => $calls,
                'workflows'          => count($group['workflows']),
                'succeeded'          => $group['succeeded'],
                'failed'             => $group['failed'],
                'denied'             => $group['denied'],
                'success_rate'       => $this->rate($group['succeeded'], $calls),
                'failure_rate'       => $this->rate($group['failed'], $calls),
                'denial_rate'        => $this->rate($group['denied'], $calls),
                'p50_duration_ms'    => $this->percentile($group['durations'], 0.50),
                'p95_duration_ms'    => $this->percentile($group['durations'], 0.95),
                'top_errors'         => $top_errors,
                'cart_mutations'     => $commerce[$group['name']]['cart_mutations'] ?? 0,
                'checkout_handoffs'  => $commerce[$group['name']]['checkout_handoffs'] ?? 0,
                'attributed_orders'  => $revenue[$group['name']]['orders'] ?? 0,
                'net_attributed_revenue' => $revenue[$group['name']]['net_by_currency'] ?? array(),
                'enabled'            => null === $this->tool_enabled_resolver
                    ? null
                    : ($this->tool_enabled_resolver)($group['name'], $session_hash_hex),
            );
        }
        usort(
            $items,
            static fn (array $left, array $right): int => $right['calls'] <=> $left['calls'] ?: strcmp($left['tool_name'], $right['tool_name'])
        );

        return array(
            'items'      => array_slice($items, 0, 50),
            'truncated'  => $truncated || count($items) > 50,
            'event_cap'  => self::MAX_HEALTH_EVENTS,
        );
    }

    /**
     * @param array<string, mixed> $filters Raw filters.
     * @return array<string, mixed>
     */
    private function filters(array $filters, bool $include_pagination = false): array
    {
        $allowed = array('date_from', 'date_to', 'tool_name');
        if ($include_pagination) {
            array_push($allowed, 'status', 'cursor', 'limit');
        }
        foreach (array_keys($filters) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unknown analytics query filter.');
            }
        }

        $normalized = array(
            'date_from' => null,
            'date_to'   => null,
            'tool_name' => null,
            'status'    => null,
            'cursor'    => null,
            'limit'     => 20,
        );
        if (isset($filters['date_from'])) {
            $normalized['date_from'] = $this->date_boundary($filters['date_from'], false);
        }
        if (isset($filters['date_to'])) {
            $normalized['date_to'] = $this->date_boundary($filters['date_to'], true);
        }
        if (null !== $normalized['date_from'] && null !== $normalized['date_to'] && $normalized['date_from'] >= $normalized['date_to']) {
            throw new InvalidArgumentException('date_from must be before date_to.');
        }
        if (isset($filters['tool_name'])) {
            if (! is_string($filters['tool_name']) || 1 !== preg_match('/\A[a-z][a-z0-9_]{0,127}\z/', $filters['tool_name'])) {
                throw new InvalidArgumentException('Tool-name filter is invalid.');
            }
            $normalized['tool_name'] = $filters['tool_name'];
        }
        if ($include_pagination && isset($filters['status'])) {
            if (! is_string($filters['status']) || ! in_array($filters['status'], array('active', 'completed', 'abandoned', 'expired'), true)) {
                throw new InvalidArgumentException('Workflow status filter is invalid.');
            }
            $normalized['status'] = $filters['status'];
        }
        if ($include_pagination && isset($filters['cursor'])) {
            if (! is_string($filters['cursor']) || strlen($filters['cursor']) > 100) {
                throw new InvalidArgumentException('Workflow cursor is invalid.');
            }
            $normalized['cursor'] = $filters['cursor'];
        }
        if ($include_pagination && isset($filters['limit'])) {
            if (! is_int($filters['limit']) || $filters['limit'] < 1 || $filters['limit'] > 50) {
                throw new InvalidArgumentException('Workflow limit must be between 1 and 50.');
            }
            $normalized['limit'] = $filters['limit'];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $filters Normalized filters.
     * @return array{0:string,1:list<mixed>}
     */
    private function workflow_scope(string $session_hash_hex, array $filters, string $alias): array
    {
        $where = "{$alias}.demo_session_hash = %s AND {$alias}.surface = 'storefront'";
        $args  = array($session_hash_hex);
        if (null !== $filters['date_from']) {
            $where .= " AND {$alias}.started_at >= %s";
            $args[] = $filters['date_from'];
        }
        if (null !== $filters['date_to']) {
            $where .= " AND {$alias}.started_at < %s";
            $args[] = $filters['date_to'];
        }

        return array($where, $args);
    }

    /**
     * @param array<string, mixed> $filters Normalized filters.
     * @return array{0:string,1:list<mixed>}
     */
    private function gap_scope(string $session_hash_hex, array $filters, string $alias): array
    {
        $where = "{$alias}.demo_session_hash = %s AND ({$alias}.signal_category = 'capability_gap' OR {$alias}.signal_category IS NULL)";
        $args  = array($session_hash_hex);
        if (null !== $filters['date_from']) {
            $where .= " AND {$alias}.occurred_at >= %s";
            $args[] = $filters['date_from'];
        }
        if (null !== $filters['date_to']) {
            $where .= " AND {$alias}.occurred_at < %s";
            $args[] = $filters['date_to'];
        }

        return array($where, $args);
    }

    /**
     * @param list<string> $workflow_ids Workflow IDs already scoped to this session.
     * @return array<string, array<string, mixed>>
     */
    private function last_events(string $session_hash_hex, array $workflow_ids): array
    {
        if (array() === $workflow_ids) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($workflow_ids), '%s'));
        $args = array_merge(array($session_hash_hex), $workflow_ids);
        $rows = $this->results(
            "SELECT e.workflow_id, e.event_name, e.tool_name, e.outcome, e.error_code, e.occurred_at
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE w.demo_session_hash = %s AND w.surface = 'storefront' AND w.id IN ({$placeholders})
             ORDER BY e.occurred_at DESC, e.id DESC",
            $args
        );
        $result = array();
        foreach ($rows as $row) {
            $id = (string) $row['workflow_id'];
            if (isset($result[$id])) {
                continue;
            }
            $result[$id] = array(
                'event_name'  => (string) $row['event_name'],
                'tool_name'   => null === $row['tool_name'] ? null : (string) $row['tool_name'],
                'outcome'     => null === $row['outcome'] ? null : (string) $row['outcome'],
                'error_code'  => null === $row['error_code'] ? null : (string) $row['error_code'],
                'occurred_at' => (string) $row['occurred_at'],
            );
        }

        return $result;
    }

    /**
     * @param list<string> $workflow_ids Workflow IDs already scoped to this session.
     * @return array<string, array<string, mixed>>
     */
    private function workflow_orders(string $session_hash_hex, array $workflow_ids): array
    {
        if (array() === $workflow_ids) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($workflow_ids), '%s'));
        $args = array_merge(array($session_hash_hex), $workflow_ids);
        $rows = $this->results(
            "SELECT ol.workflow_id, ol.currency, COUNT(DISTINCT ol.order_id) AS orders, COALESCE(SUM(ol.gross_value),0) AS gross,
                    COALESCE(SUM(ol.refund_value),0) AS refunds, COALESCE(SUM(ol.net_value),0) AS net
             FROM {$this->order_links_table()} ol
             INNER JOIN {$this->workflows_table()} w ON w.id = ol.workflow_id
             WHERE w.demo_session_hash = %s AND w.surface = 'storefront' AND w.id IN ({$placeholders})
             GROUP BY ol.workflow_id, ol.currency",
            $args
        );
        $result = array();
        foreach ($rows as $row) {
            $workflow_id = (string) $row['workflow_id'];
            $currency    = (string) $row['currency'];
            if (! isset($result[$workflow_id])) {
                $result[$workflow_id] = array('orders' => 0, 'by_currency' => array());
            }
            $result[$workflow_id]['orders'] += (int) $row['orders'];
            $result[$workflow_id]['by_currency'][$currency] = array(
                'orders'   => (int) $row['orders'],
                'gross'    => (float) $row['gross'],
                'refunds'  => (float) $row['refunds'],
                'net'      => (float) $row['net'],
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $filters Normalized filters.
     * @return array<string, array<string, int>>
     */
    private function tool_commerce(string $session_hash_hex, array $filters): array
    {
        [$scope, $args] = $this->workflow_scope($session_hash_hex, $filters, 'w');
        if (null !== $filters['tool_name']) {
            $scope .= ' AND e.tool_name = %s';
            $args[] = $filters['tool_name'];
        }
        $rows = $this->results(
            "SELECT e.tool_name,
                    COALESCE(SUM(CASE WHEN e.event_name = 'commerce.cart.changed' THEN 1 ELSE 0 END),0) AS cart_mutations,
                    COALESCE(SUM(CASE WHEN e.event_name = 'commerce.checkout.handoff' THEN 1 ELSE 0 END),0) AS checkout_handoffs
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE {$scope} AND e.tool_name IS NOT NULL
             GROUP BY e.tool_name",
            $args
        );
        $result = array();
        foreach ($rows as $row) {
            $result[(string) $row['tool_name']] = array(
                'cart_mutations'    => (int) $row['cart_mutations'],
                'checkout_handoffs' => (int) $row['checkout_handoffs'],
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $filters Normalized filters.
     * @return array<string, array<string, mixed>>
     */
    private function tool_revenue(string $session_hash_hex, array $filters): array
    {
        [$scope, $args] = $this->workflow_scope($session_hash_hex, $filters, 'w');
        $tool_condition = '';
        if (null !== $filters['tool_name']) {
            $tool_condition = ' AND e.tool_name = %s';
            $args[] = $filters['tool_name'];
        }
        $rows = $this->results(
            "SELECT tools.tool_name, ol.currency, COUNT(DISTINCT ol.order_id) AS orders, COALESCE(SUM(ol.net_value),0) AS net
             FROM (
                 SELECT DISTINCT e.workflow_id, e.tool_name
                 FROM {$this->events_table()} e
                 INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
                 WHERE {$scope} AND e.event_name = 'tool.call.succeeded' AND e.tool_name IS NOT NULL{$tool_condition}
             ) tools
             INNER JOIN {$this->order_links_table()} ol ON ol.workflow_id = tools.workflow_id
             GROUP BY tools.tool_name, ol.currency",
            $args
        );
        $result = array();
        foreach ($rows as $row) {
            $tool_name = (string) $row['tool_name'];
            $currency  = (string) $row['currency'];
            if (! isset($result[$tool_name])) {
                $result[$tool_name] = array('orders' => 0, 'net_by_currency' => array());
            }
            $result[$tool_name]['orders'] += (int) $row['orders'];
            $result[$tool_name]['net_by_currency'][$currency] = (float) $row['net'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row Event database row.
     * @return array<string, mixed>
     */
    private function public_replay_event(array $row, bool &$truncated): array
    {
        $event_name = (string) $row['event_name'];
        $event      = array(
            'event_id'    => (string) $row['event_id'],
            'event_name'  => $event_name,
            'step_number' => (int) $row['step_number'],
            'occurred_at' => (string) $row['occurred_at'],
        );

        if (null !== $row['request_id']) {
            $event['request_id'] = (string) $row['request_id'];
        }
        if (null !== $row['tool_name']) {
            $event['tool'] = array(
                'name'       => (string) $row['tool_name'],
                'version'    => (string) $row['tool_version'],
                'risk_class' => (string) $row['risk_class'],
            );
        }
        if (null !== $row['outcome']) {
            $event['outcome'] = (string) $row['outcome'];
        }
        if (null !== $row['duration_ms']) {
            $event['duration_ms'] = (int) $row['duration_ms'];
        }
        if (null !== $row['error_code']) {
            $event['error_code'] = (string) $row['error_code'];
        }
        if (null !== $row['http_status']) {
            $event['http_status'] = (int) $row['http_status'];
        }

        $product_ids = $this->safe_id_list($row['product_ids_json'], 8, $truncated);
        if (array() !== $product_ids) {
            $event['product_ids'] = $product_ids;
        }
        if (null !== $row['currency']) {
            $event['currency'] = (string) $row['currency'];
        }
        if (null !== $row['value']) {
            $event['value'] = (float) $row['value'];
        }

        $properties = $this->safe_properties($event_name, $row['properties_json'], $truncated);
        if (array() !== $properties) {
            $event['properties'] = $properties;
        }

        return $event;
    }

    /**
     * Keep a replay below the execution controller's 8 KiB output ceiling while
     * retaining its first/latest events and explicit diagnostic highlights.
     *
     * @param array<string, mixed> $result Replay result.
     * @return array<string, mixed>
     */
    private function fit_replay_result(array $result): array
    {
        while ($this->replay_size($result) > self::REPLAY_RESULT_BUDGET && $this->drop_interior_event_properties($result['timeline'])) {
            $result['truncated'] = true;
        }

        while ($this->replay_size($result) > self::REPLAY_RESULT_BUDGET && count($result['timeline']) > 2) {
            array_splice($result['timeline'], 1, 1);
            $result['truncated'] = true;
        }

        if ($this->replay_size($result) > self::REPLAY_RESULT_BUDGET) {
            $this->drop_all_event_properties($result);
            $result['truncated'] = true;
        }
        while ($this->replay_size($result) > self::REPLAY_RESULT_BUDGET && array() !== $result['commerce_outcome']['orders']) {
            array_shift($result['commerce_outcome']['orders']);
            $result['truncated'] = true;
        }
        while ($this->replay_size($result) > self::REPLAY_RESULT_BUDGET && array() !== $result['capability_gaps']) {
            array_shift($result['capability_gaps']);
            $result['truncated'] = true;
        }
        while ($this->replay_size($result) > self::REPLAY_RESULT_BUDGET && array() !== $result['opportunity_signals']) {
            array_shift($result['opportunity_signals']);
            $result['truncated'] = true;
        }
        while ($this->replay_size($result) > self::REPLAY_RESULT_BUDGET && array() !== $result['agent_feedback']) {
            array_shift($result['agent_feedback']);
            $result['truncated'] = true;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $timeline Replay timeline.
     */
    private function drop_interior_event_properties(array &$timeline): bool
    {
        $last = count($timeline) - 1;
        for ($index = 1; $index < $last; ++$index) {
            if (isset($timeline[$index]['properties'])) {
                unset($timeline[$index]['properties']);

                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $result Replay result. */
    private function drop_all_event_properties(array &$result): void
    {
        foreach ($result['timeline'] as &$event) {
            unset($event['properties']);
        }
        unset($event);

        foreach (array('first_problem', 'recovery') as $key) {
            if (is_array($result[$key])) {
                unset($result[$key]['properties']);
            }
        }
        if (is_array($result['commerce_outcome']['last_event'])) {
            unset($result['commerce_outcome']['last_event']['properties']);
        }
    }

    /** @param array<string, mixed> $result Replay result. */
    private function replay_size(array $result): int
    {
        return strlen(Json::encode($result));
    }

    /**
     * @param array<string, mixed> $row Order-link row.
     * @return array<string, mixed>
     */
    private function public_order(array $row, bool &$truncated): array
    {
        return array(
            'order_id'              => (int) $row['order_id'],
            'attribution_class'     => (string) $row['attribution_class'],
            'confidence'            => (string) $row['confidence'],
            'rule_version'          => (string) $row['rule_version'],
            'gross'                 => (float) $row['gross_value'],
            'refunds'               => (float) $row['refund_value'],
            'net'                   => (float) $row['net_value'],
            'currency'              => (string) $row['currency'],
            'matched_product_ids'   => $this->safe_id_list($row['matched_products_json'], 8, $truncated),
            'evidence_event_ids'    => $this->safe_event_id_list($row['evidence_event_ids_json'], 8, $truncated),
            'updated_at'            => (string) $row['updated_at'],
        );
    }

    /**
     * @param mixed $json Encoded property object.
     * @return array<string, mixed>
     */
    private function safe_properties(string $event_name, $json, bool &$truncated): array
    {
        if (! is_string($json) || '' === $json) {
            return array();
        }
        try {
            $properties = Json::decode_object($json);
        } catch (JsonException | InvalidArgumentException $exception) {
            $truncated = true;

            return array();
        }

        $properties = $this->schema->properties($event_name, $properties);
        if (count($properties) > self::MAX_EVENT_PROPERTIES) {
            $truncated = true;
        }

        $result = array();
        foreach (array_slice($properties, 0, self::MAX_EVENT_PROPERTIES, true) as $key => $value) {
            if (is_array($value)) {
                $truncated = true;
                continue;
            }
            if (is_string($value) && mb_strlen($value) > self::MAX_PROPERTY_STRING_LENGTH) {
                $value     = mb_substr($value, 0, self::MAX_PROPERTY_STRING_LENGTH);
                $truncated = true;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param mixed $json Encoded integer list.
     * @return list<int>
     */
    private function safe_id_list($json, int $limit, bool &$truncated): array
    {
        if (! is_string($json) || '' === $json) {
            return array();
        }
        try {
            $values = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $truncated = true;

            return array();
        }
        if (! is_array($values)) {
            $truncated = true;

            return array();
        }
        if (count($values) > $limit) {
            $truncated = true;
        }

        $result = array();
        foreach (array_slice($values, 0, $limit) as $value) {
            if (is_int($value) && $value > 0) {
                $result[] = $value;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param mixed $json Encoded event-ID list.
     * @return list<string>
     */
    private function safe_event_id_list($json, int $limit, bool &$truncated): array
    {
        if (! is_string($json) || '' === $json) {
            return array();
        }
        try {
            $values = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $truncated = true;

            return array();
        }
        if (! is_array($values)) {
            $truncated = true;

            return array();
        }
        if (count($values) > $limit) {
            $truncated = true;
        }

        $result = array();
        foreach (array_slice($values, 0, $limit) as $value) {
            if (is_string($value) && 1 === preg_match('/\Aevt_[0-9A-HJKMNP-TV-Z]{26}\z/', $value)) {
                $result[] = $value;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param array<string, mixed>      $workflow Workflow row.
     * @param array<string, mixed>|null $first_problem First problem event.
     * @param array<string, mixed>|null $recovery Recovery event.
     * @param list<array<string, mixed>> $orders Linked orders.
     * @param list<array<string, mixed>> $gaps Capability gaps.
     */
    private function explanation_text(
        array $workflow,
        ?array $first_problem,
        ?array $recovery,
        array $orders,
        array $gaps,
        array $opportunities = array(),
        array $feedback = array()
    ): string {
        $parts = array(
            sprintf('Storefront workflow %s is %s after %d recorded tool calls.', $workflow['id'], $workflow['status'], (int) $workflow['tool_count']),
        );
        if (null !== $first_problem) {
            $error_code = $first_problem['error_code'] ?? null;
            $parts[] = sprintf(
                'The first recorded problem was %s%s.',
                $first_problem['event_name'],
                null === $error_code ? '' : ' (' . $error_code . ')'
            );
            $parts[] = null === $recovery
                ? 'No later successful tool call was recorded.'
                : sprintf('A later successful %s call was recorded.', $recovery['tool']['name'] ?? 'tool');
        } else {
            $parts[] = 'No failed, cancelled, or denied tool call was recorded.';
        }
        $parts[] = array() === $orders
            ? 'No attributed WooCommerce order is linked to this workflow.'
            : sprintf('%d attributed WooCommerce order link(s) are shown.', count($orders));
        if (array() !== $gaps) {
            $parts[] = sprintf('%d unsupported capability request(s) are shown.', count($gaps));
        }
        if (array() !== $opportunities) {
            $parts[] = sprintf('%d site-observed opportunity signal(s) are shown.', count($opportunities));
        }
        if (array() !== $feedback) {
            $parts[] = sprintf('%d evidence-linked agent feedback report(s) are shown.', count($feedback));
        }

        return implode(' ', $parts);
    }

    /**
     * @param mixed $value Date string.
     */
    private function date_boundary($value, bool $exclusive_end): string
    {
        if (! is_string($value) || 1 !== preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
            throw new InvalidArgumentException('Analytics dates must use YYYY-MM-DD.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Analytics date is invalid.');
        }
        if ($exclusive_end) {
            $date = $date->modify('+1 day');
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function encode_cursor(string $time, string $workflow_id): string
    {
        $payload = json_encode(array('t' => $this->schema->mysql_timestamp($time), 'i' => $this->schema->workflow_id($workflow_id)), JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * @return array{time:string,id:string}
     */
    private function decode_cursor(string $cursor): array
    {
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{1,100}\z/', $cursor)) {
            throw new InvalidArgumentException('Workflow cursor is invalid.');
        }
        $padding = strlen($cursor) % 4;
        if (0 !== $padding) {
            $cursor .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        if (false === $json) {
            throw new InvalidArgumentException('Workflow cursor is invalid.');
        }
        try {
            $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Workflow cursor is invalid.');
        }
        if (! is_array($payload) || ! isset($payload['t'], $payload['i']) || ! is_string($payload['t']) || ! is_string($payload['i'])) {
            throw new InvalidArgumentException('Workflow cursor is invalid.');
        }

        return array('time' => $this->schema->mysql_timestamp($payload['t']), 'id' => $this->schema->workflow_id($payload['i']));
    }

    /**
     * @param list<int> $values Values, sorted or unsorted.
     */
    private function percentile(array $values, float $percentile): ?int
    {
        if (array() === $values) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $index = max(0, (int) ceil($percentile * count($values)) - 1);

        return $values[$index];
    }

    private function rate(int $part, int $total): float
    {
        return 0 === $total ? 0.0 : round($part / $total, 4);
    }

    /**
     * @param list<mixed> $args Prepare arguments.
     * @return array<string, mixed>
     */
    private function row(string $sql, array $args): array
    {
        $row = $this->database->get_row($this->prepare($sql, $args), ARRAY_A);

        return is_array($row) ? $row : array();
    }

    /**
     * @param list<mixed> $args Prepare arguments.
     * @return list<array<string, mixed>>
     */
    private function results(string $sql, array $args): array
    {
        $rows = $this->database->get_results($this->prepare($sql, $args), ARRAY_A);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : array();
    }

    /**
     * @param list<mixed> $args Prepare arguments.
     */
    private function prepare(string $sql, array $args): string
    {
        return array() === $args ? $sql : $this->database->prepare($sql, ...$args);
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

    private function gaps_table(): string
    {
        return $this->database->prefix . 'wmcp_capability_gaps';
    }
}
