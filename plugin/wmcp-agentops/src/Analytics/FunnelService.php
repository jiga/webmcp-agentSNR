<?php

/**
 * Deterministic storefront-to-commerce funnel aggregation.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use WPWebMCP\AgentOps\Contract\EventName;

final class FunnelService
{
    private const MAX_EVENTS = 20000;

    private const STAGES = array(
        'workflow_started'       => array(EventName::WORKFLOW_STARTED),
        'product_search'         => array(EventName::PRODUCT_SEARCHED),
        // A comparison necessarily retrieves both product records; count that
        // recorded fact without inventing a separate product-view event.
        'product_viewed'         => array(EventName::PRODUCT_VIEWED, EventName::PRODUCTS_COMPARED),
        'comparison'             => array(EventName::PRODUCTS_COMPARED),
        'cart_changed'           => array(EventName::CART_CHANGED),
        'checkout_handoff'       => array(EventName::CHECKOUT_HANDOFF),
        'order_created'          => array(EventName::ORDER_CREATED),
        'order_paid'             => array(EventName::ORDER_PAID),
        'retained_after_refunds' => array(),
    );

    private object $database;

    private EventSchema $schema;

    public function __construct(?object $database = null, ?EventSchema $schema = null)
    {
        if (null === $database) {
            global $wpdb;
            $database = $wpdb;
        }
        if (! is_object($database)) {
            throw new InvalidArgumentException('A wpdb-compatible database object is required.');
        }

        $this->database = $database;
        $this->schema   = $schema ?? new EventSchema();
    }

    /**
     * @param array<string, mixed> $filters Optional date, workflow, and tool filters.
     * @return array<string, mixed>
     */
    public function get(string $session_hash_hex, array $filters = array()): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $filters          = $this->filters($filters);
        [$scope, $args]   = $this->scope($session_hash_hex, $filters, 'w');

        $workflow_rows = $this->results(
            "SELECT w.id, w.status, w.started_at, w.ended_at
             FROM {$this->workflows_table()} w
             WHERE {$scope}
             ORDER BY w.started_at ASC, w.id ASC
             LIMIT 5000",
            $args
        );
        $workflows = array();
        foreach ($workflow_rows as $row) {
            $id = (string) $row['id'];
            $workflows[$id] = array(
                'status'     => (string) $row['status'],
                'started_at' => (string) $row['started_at'],
                'ended_at'   => null === $row['ended_at'] ? null : (string) $row['ended_at'],
                'stages'     => array('workflow_started' => (string) $row['started_at']),
                'problems'   => array(),
            );
        }

        $event_names = array();
        foreach (self::STAGES as $names) {
            $event_names = array_merge($event_names, $names);
        }
        $event_names = array_values(array_unique(array_merge($event_names, EventName::tool_terminal())));
        $event_placeholders = implode(',', array_fill(0, count($event_names), '%s'));
        $event_args         = array_merge($args, $event_names);
        $event_rows = $this->results(
            "SELECT e.workflow_id, e.event_name, e.error_code, e.occurred_at
             FROM {$this->events_table()} e
             INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
             WHERE {$scope} AND e.event_name IN ({$event_placeholders})
             ORDER BY e.occurred_at ASC, e.id ASC
             LIMIT " . (self::MAX_EVENTS + 1),
            $event_args
        );
        $truncated = count($event_rows) > self::MAX_EVENTS || count($workflow_rows) >= 5000;
        foreach (array_slice($event_rows, 0, self::MAX_EVENTS) as $row) {
            $workflow_id = (string) $row['workflow_id'];
            if (! isset($workflows[$workflow_id])) {
                continue;
            }
            $event_name = (string) $row['event_name'];
            foreach (self::STAGES as $stage => $names) {
                if (isset($workflows[$workflow_id]['stages'][$stage]) || ! in_array($event_name, $names, true)) {
                    continue;
                }
                $workflows[$workflow_id]['stages'][$stage] = (string) $row['occurred_at'];
            }
            if (in_array($event_name, array(EventName::TOOL_CALL_FAILED, EventName::TOOL_CALL_CANCELLED, EventName::TOOL_CALL_DENIED), true)) {
                $workflows[$workflow_id]['problems'][] = array(
                    'event_name'  => $event_name,
                    'error_code'  => null === $row['error_code'] ? null : (string) $row['error_code'],
                    'occurred_at' => (string) $row['occurred_at'],
                );
            }
        }

        $retained_rows = $this->results(
            "SELECT ol.workflow_id, MIN(ol.updated_at) AS retained_at
             FROM {$this->order_links_table()} ol
             INNER JOIN {$this->workflows_table()} w ON w.id = ol.workflow_id
             WHERE {$scope} AND ol.net_value > 0
             GROUP BY ol.workflow_id",
            $args
        );
        foreach ($retained_rows as $row) {
            $workflow_id = (string) $row['workflow_id'];
            if (isset($workflows[$workflow_id])) {
                $workflows[$workflow_id]['stages']['retained_after_refunds'] = (string) $row['retained_at'];
            }
        }

        $stage_names = array_keys(self::STAGES);
        $start_count = count($workflows);
        $stages      = array();
        foreach ($stage_names as $index => $stage_name) {
            $reached = array_filter(
                $workflows,
                static fn (array $workflow): bool => isset($workflow['stages'][$stage_name])
            );
            $count          = count($reached);
            $previous_count = 0 === $index ? $start_count : (int) $stages[$index - 1]['workflow_count'];
            $next_stage     = $stage_names[$index + 1] ?? null;
            $durations      = array();
            $exit_reasons   = array();
            if (null !== $next_stage) {
                foreach ($reached as $workflow) {
                    if (isset($workflow['stages'][$next_stage])) {
                        $from = strtotime($workflow['stages'][$stage_name] . ' UTC');
                        $to   = strtotime($workflow['stages'][$next_stage] . ' UTC');
                        if (false !== $from && false !== $to && $to >= $from) {
                            $durations[] = ($to - $from) * 1000;
                        }
                        continue;
                    }
                    $reason = $this->exit_reason($workflow, $workflow['stages'][$stage_name]);
                    $exit_reasons[$reason] = ($exit_reasons[$reason] ?? 0) + 1;
                }
            }
            arsort($exit_reasons);
            $top_exit_reason = array_key_first($exit_reasons);

            $stages[] = array(
                'stage'                     => $stage_name,
                'workflow_count'            => $count,
                'conversion_from_previous'  => $this->rate($count, $previous_count),
                'conversion_from_start'     => $this->rate($count, $start_count),
                'median_time_to_next_ms'    => $this->median($durations),
                'top_exit_reason'           => $top_exit_reason,
                'top_exit_count'            => null === $top_exit_reason ? 0 : $exit_reasons[$top_exit_reason],
            );
        }

        return array(
            'scope' => array(
                'surface'    => 'storefront',
                'workflow_id' => $filters['workflow_id'],
                'tool_name'  => $filters['tool_name'],
                'date_from'  => $filters['date_from'],
                'date_to'    => $filters['date_to'],
            ),
            'stages'            => $stages,
            'workflow_count'    => $start_count,
            'truncated'         => $truncated,
            'value_definition'  => 'Retained means an attributed paid order has net value greater than zero after recorded refunds.',
        );
    }

    /**
     * @param array<string, mixed> $workflow Aggregated workflow.
     */
    private function exit_reason(array $workflow, string $stage_time): string
    {
        foreach ($workflow['problems'] as $problem) {
            if ($problem['occurred_at'] < $stage_time) {
                continue;
            }
            return null === $problem['error_code'] ? $problem['event_name'] : $problem['error_code'];
        }

        return match ($workflow['status']) {
            'active' => 'in_progress',
            'abandoned' => 'workflow_abandoned',
            'expired' => 'workflow_expired',
            'completed' => 'completed_without_next_stage',
            default => 'no_next_stage',
        };
    }

    /**
     * @param array<string, mixed> $filters Raw filters.
     * @return array<string, mixed>
     */
    private function filters(array $filters): array
    {
        foreach (array_keys($filters) as $key) {
            if (! is_string($key) || ! in_array($key, array('date_from', 'date_to', 'workflow_id', 'tool_name'), true)) {
                throw new InvalidArgumentException('Unknown funnel filter.');
            }
        }
        $result = array('date_from' => null, 'date_to' => null, 'workflow_id' => null, 'tool_name' => null);
        if (isset($filters['date_from'])) {
            $result['date_from'] = $this->date_boundary($filters['date_from'], false);
        }
        if (isset($filters['date_to'])) {
            $result['date_to'] = $this->date_boundary($filters['date_to'], true);
        }
        if (null !== $result['date_from'] && null !== $result['date_to'] && $result['date_from'] >= $result['date_to']) {
            throw new InvalidArgumentException('date_from must be before date_to.');
        }
        if (isset($filters['workflow_id'])) {
            if (! is_string($filters['workflow_id'])) {
                throw new InvalidArgumentException('Workflow filter must be a ULID string.');
            }
            $result['workflow_id'] = $this->schema->workflow_id($filters['workflow_id']);
        }
        if (isset($filters['tool_name'])) {
            if (! is_string($filters['tool_name']) || 1 !== preg_match('/\A[a-z][a-z0-9_]{0,127}\z/', $filters['tool_name'])) {
                throw new InvalidArgumentException('Tool-name filter is invalid.');
            }
            $result['tool_name'] = $filters['tool_name'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $filters Normalized filters.
     * @return array{0:string,1:list<mixed>}
     */
    private function scope(string $session_hash_hex, array $filters, string $alias): array
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
        if (null !== $filters['workflow_id']) {
            $where .= " AND {$alias}.id = %s";
            $args[] = $filters['workflow_id'];
        }
        if (null !== $filters['tool_name']) {
            $where .= " AND EXISTS (SELECT 1 FROM {$this->events_table()} ft WHERE ft.workflow_id = {$alias}.id AND ft.tool_name = %s)";
            $args[] = $filters['tool_name'];
        }

        return array($where, $args);
    }

    /**
     * @param mixed $value Date string.
     */
    private function date_boundary($value, bool $exclusive_end): string
    {
        if (! is_string($value) || 1 !== preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
            throw new InvalidArgumentException('Funnel dates must use YYYY-MM-DD.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Funnel date is invalid.');
        }
        if ($exclusive_end) {
            $date = $date->modify('+1 day');
        }

        return $date->format('Y-m-d H:i:s');
    }

    /** @param list<int> $values */
    private function median(array $values): ?int
    {
        if (array() === $values) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        if (1 === $count % 2) {
            return $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function rate(int $part, int $whole): float
    {
        return 0 === $whole ? 0.0 : round($part / $whole, 4);
    }

    /**
     * @param list<mixed> $args Prepare arguments.
     * @return list<array<string, mixed>>
     */
    private function results(string $sql, array $args): array
    {
        $prepared = array() === $args ? $sql : $this->database->prepare($sql, ...$args);
        $rows = $this->database->get_results($prepared, ARRAY_A);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : array();
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
