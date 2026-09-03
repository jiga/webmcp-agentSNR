<?php

/**
 * Privacy-preserving capability-gap recording and aggregation.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use WPWebMCP\AgentSNR\Contract\EventName;
use WPWebMCP\AgentSNR\Privacy\Redactor;
use WPWebMCP\AgentSNR\Support\Clock;
use WPWebMCP\AgentSNR\Support\IdGenerator;
use WPWebMCP\AgentSNR\Support\Json;

final class CapabilityGapService
{
    private const CAPABILITIES = array(
        'back_in_stock_notification',
        'price_drop_alert',
        'product_reservation',
        'custom_product_configuration',
        'other',
    );

    private const CONTEXT_ALLOWLIST = array('color', 'capacity_liters', 'size', 'variant', 'category');

    private object $database;

    private EventSchema $schema;

    private Redactor $redactor;

    private EventRecorder $events;

    /** @var callable(): string */
    private $clock;

    /** @var callable(): string */
    private $ulid_generator;

    /**
     * @param object|null       $database       wpdb-compatible database object.
     * @param callable():string $clock          UTC DATETIME provider.
     * @param callable():string $ulid_generator ULID provider.
     */
    public function __construct(
        ?object $database = null,
        ?EventSchema $schema = null,
        ?Redactor $redactor = null,
        ?EventRecorder $events = null,
        ?callable $clock = null,
        ?callable $ulid_generator = null
    ) {
        if (null === $database) {
            global $wpdb;
            $database = $wpdb;
        }
        if (! is_object($database)) {
            throw new InvalidArgumentException('A wpdb-compatible database object is required.');
        }

        $this->database         = $database;
        $this->schema           = $schema ?? new EventSchema();
        $this->redactor         = $redactor ?? new Redactor();
        $this->clock            = $clock ?? static fn (): string => Clock::mysql();
        $this->ulid_generator   = $ulid_generator ?? static fn (): string => IdGenerator::ulid();
        $this->events           = $events ?? new EventRecorder($database, $this->schema, $this->clock);
    }

    /**
     * Record a narrowly structured unsupported request.
     *
     * Exact replays return the existing row, and neither the raw prompt nor
     * arbitrary input/output is persisted.
     *
     * @param array<string, mixed> $context Narrow product context.
     * @return array<string, mixed>
     */
    public function report(
        string $workflow_id,
        string $session_hash_hex,
        string $capability_slug,
        string $user_goal,
        ?int $related_product_id = null,
        array $context = array()
    ): array {
        $workflow_id      = $this->schema->workflow_id($workflow_id);
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $capability_slug  = strtolower(trim($capability_slug));
        if (! in_array($capability_slug, self::CAPABILITIES, true)) {
            throw new InvalidArgumentException('Requested capability is not in the public allowlist.');
        }
        if (null !== $related_product_id && $related_product_id < 1) {
            throw new InvalidArgumentException('Related product ID must be positive.');
        }

        $this->assert_workflow_scope($workflow_id, $session_hash_hex);
        $safe_context = $this->redactor->properties($context, self::CONTEXT_ALLOWLIST);
        foreach ($safe_context as $key => $value) {
            if (is_string($value)) {
                $safe_context[$key] = mb_substr($value, 0, 80);
            } elseif (! is_int($value) && ! is_float($value) && ! is_bool($value) && null !== $value) {
                unset($safe_context[$key]);
            }
        }
        // Public telemetry intentionally does not persist free-form user text.
        // The required input helps the agent explain the gap, while storage uses
        // only this fixed capability label and narrow structured context.
        unset($user_goal);
        $goal         = $this->canonical_goal($capability_slug);
        $context_json = array() === $safe_context ? null : Json::encode($safe_context);
        $existing     = $this->find_existing(
            $workflow_id,
            $session_hash_hex,
            $capability_slug,
            $goal,
            $related_product_id,
            $context_json
        );

        if (null !== $existing) {
            $this->ensure_event($existing);

            return $this->public_gap($existing, true);
        }

        $occurred_at = $this->now();
        $row = array(
            'id'                  => $this->schema->workflow_id(($this->ulid_generator)()),
            'workflow_id'         => $workflow_id,
            'demo_session_hash'   => $session_hash_hex,
            'capability_slug'     => $capability_slug,
            'user_goal_redacted'  => $goal,
            'related_product_id'  => $related_product_id,
            'context_json'        => $context_json,
            'signal_source'       => 'agent_reported',
            'signal_category'     => 'capability_gap',
            'signal_key'          => hash('sha256', 'capability_gap|' . $capability_slug),
            'evidence_status'     => 'unlinked',
            'status'              => 'open',
            'occurred_at'         => $occurred_at,
        );
        $result = $this->database->insert(
            $this->gaps_table(),
            $row,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        if (false === $result) {
            $existing = $this->find_existing(
                $workflow_id,
                $session_hash_hex,
                $capability_slug,
                $goal,
                $related_product_id,
                $context_json
            );
            if (null === $existing) {
                throw new RuntimeException('The capability gap could not be recorded.');
            }
            $this->ensure_event($existing);

            return $this->public_gap($existing, true);
        }

        $this->ensure_event($row);

        return $this->public_gap($row, false);
    }

    /**
     * Return compact, cursor-paginated grouped capability gaps.
     *
     * @param array<string, mixed> $filters Status/product/date/cursor/limit filters.
     * @return array<string, mixed>
     */
    public function grouped(string $session_hash_hex, array $filters = array()): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $filters          = $this->filters($filters);
        [$where, $args]   = $this->scope($session_hash_hex, $filters, 'g');
        $offset           = $this->decode_cursor($filters['cursor']);
        $fetch_limit      = $filters['limit'] + 1;
        $rows = $this->results(
            "SELECT g.capability_slug, g.status, COUNT(*) AS requests, COUNT(DISTINCT g.workflow_id) AS workflows,
                    MAX(g.occurred_at) AS latest_occurrence
             FROM {$this->gaps_table()} g
             WHERE {$where}
             GROUP BY g.capability_slug, g.status
             ORDER BY requests DESC, latest_occurrence DESC, g.capability_slug ASC, g.status ASC
             LIMIT {$fetch_limit} OFFSET {$offset}",
            $args
        );
        $has_more = count($rows) > $filters['limit'];
        $rows     = array_slice($rows, 0, $filters['limit']);
        $products = $this->related_products($session_hash_hex, $rows, $filters);
        $values   = $this->viewed_product_values($session_hash_hex, $rows, $filters);

        $items = array();
        foreach ($rows as $row) {
            $key = (string) $row['capability_slug'] . "\0" . (string) $row['status'];
            $items[] = array(
                'capability'                    => (string) $row['capability_slug'],
                'status'                        => (string) $row['status'],
                'requests'                      => (int) $row['requests'],
                'affected_workflows'            => (int) $row['workflows'],
                'related_product_ids'           => $products[$key] ?? array(),
                'viewed_product_value_context'  => $values[$key] ?? array(),
                'opportunity_context_label'     => 'Recorded public product-view value in affected workflows; this does not estimate foregone sales.',
                'latest_occurrence'             => (string) $row['latest_occurrence'],
            );
        }

        return array(
            'items'       => $items,
            'next_cursor' => $has_more ? $this->encode_cursor($offset + $filters['limit']) : null,
            'has_more'    => $has_more,
            'limit'       => $filters['limit'],
        );
    }

    private function assert_workflow_scope(string $workflow_id, string $session_hash_hex): void
    {
        $sql = $this->database->prepare(
            "SELECT w.id
             FROM {$this->workflows_table()} w
             WHERE w.demo_session_hash = %s AND w.surface = 'storefront' AND w.id = %s
             LIMIT 1",
            $session_hash_hex,
            $workflow_id
        );
        if (null === $this->database->get_var($sql)) {
            throw new InvalidArgumentException('Workflow is not available in this demo session.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_existing(
        string $workflow_id,
        string $session_hash_hex,
        string $capability_slug,
        string $goal,
        ?int $related_product_id,
        ?string $context_json
    ): ?array {
        $where_product = null === $related_product_id ? 'g.related_product_id IS NULL' : 'g.related_product_id = %d';
        $where_context = null === $context_json ? 'g.context_json IS NULL' : 'g.context_json = %s';
        $args = array($session_hash_hex, $workflow_id, $capability_slug, $goal);
        if (null !== $related_product_id) {
            $args[] = $related_product_id;
        }
        if (null !== $context_json) {
            $args[] = $context_json;
        }
        $sql = $this->database->prepare(
            "SELECT g.id, g.workflow_id, g.demo_session_hash, g.capability_slug, g.related_product_id, g.status, g.occurred_at
             FROM {$this->gaps_table()} g
             WHERE g.demo_session_hash = %s
               AND g.workflow_id = %s
               AND g.capability_slug = %s
               AND g.user_goal_redacted = %s
               AND {$where_product}
               AND {$where_context}
             ORDER BY g.occurred_at ASC, g.id ASC
             LIMIT 1",
            ...$args
        );
        $row = $this->database->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $gap Capability-gap row.
     */
    private function ensure_event(array $gap): void
    {
        $product_ids = null === $gap['related_product_id'] ? array() : array((int) $gap['related_product_id']);
        $this->events->record(
            (string) $gap['workflow_id'],
            EventName::CAPABILITY_GAP_REPORTED,
            array(
                'product_ids' => $product_ids,
                'properties'  => array(
                    'gap_id'               => (string) $gap['id'],
                    'requested_capability' => (string) $gap['capability_slug'],
                    'related_product_id'   => null === $gap['related_product_id'] ? null : (int) $gap['related_product_id'],
                    'status'               => (string) $gap['status'],
                ),
                'occurred_at' => (string) $gap['occurred_at'],
            ),
            'gap:' . (string) $gap['id']
        );
    }

    private function canonical_goal(string $capability_slug): string
    {
        return match ($capability_slug) {
            'back_in_stock_notification' => 'Shopper requested a back-in-stock notification.',
            'price_drop_alert' => 'Shopper requested a price-drop alert.',
            'product_reservation' => 'Shopper requested a product reservation.',
            'custom_product_configuration' => 'Shopper requested a custom product configuration.',
            default => 'Shopper requested an unsupported store capability.',
        };
    }

    /**
     * @param array<string, mixed> $row Capability-gap row.
     * @return array<string, mixed>
     */
    private function public_gap(array $row, bool $replayed): array
    {
        return array(
            'gap_id'             => (string) $row['id'],
            'workflow_id'        => (string) $row['workflow_id'],
            'capability'         => (string) $row['capability_slug'],
            'related_product_id' => null === $row['related_product_id'] ? null : (int) $row['related_product_id'],
            'status'             => (string) $row['status'],
            'occurred_at'        => (string) $row['occurred_at'],
            'replayed'           => $replayed,
            'fulfilled'          => false,
            'message'            => 'The unsupported request was recorded for the merchant; no notification, reservation, or order was created.',
        );
    }

    /**
     * @param list<array<string, mixed>> $groups Group rows.
     * @param array<string, mixed>       $filters Normalized filters.
     * @return array<string, list<int>>
     */
    private function related_products(string $session_hash_hex, array $groups, array $filters): array
    {
        if (array() === $groups) {
            return array();
        }
        [$where, $args] = $this->scope($session_hash_hex, $filters, 'g');
        [$pairs, $pair_args] = $this->group_pairs($groups, 'g');
        $args = array_merge($args, $pair_args);
        $rows = $this->results(
            "SELECT g.capability_slug, g.status, g.related_product_id, COUNT(*) AS requests
             FROM {$this->gaps_table()} g
             WHERE {$where} AND g.related_product_id IS NOT NULL AND ({$pairs})
             GROUP BY g.capability_slug, g.status, g.related_product_id
             ORDER BY requests DESC, g.related_product_id ASC",
            $args
        );
        $result = array();
        foreach ($rows as $row) {
            $key = (string) $row['capability_slug'] . "\0" . (string) $row['status'];
            if (count($result[$key] ?? array()) < 10) {
                $result[$key][] = (int) $row['related_product_id'];
            }
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $groups Group rows.
     * @param array<string, mixed>       $filters Normalized filters.
     * @return array<string, array<string, float>>
     */
    private function viewed_product_values(string $session_hash_hex, array $groups, array $filters): array
    {
        if (array() === $groups) {
            return array();
        }
        [$gap_where, $gap_args] = $this->scope($session_hash_hex, $filters, 'g');
        [$pairs, $pair_args]    = $this->group_pairs($groups, 'g');
        $args = array_merge($gap_args, array($session_hash_hex), $pair_args);
        $rows = $this->results(
            "SELECT viewed.capability_slug, viewed.status, viewed.currency, SUM(viewed.value) AS value
             FROM (
                 SELECT DISTINCT g.capability_slug, g.status, e.event_id, e.currency, e.value
                 FROM {$this->gaps_table()} g
                 INNER JOIN {$this->events_table()} e ON e.workflow_id = g.workflow_id
                 INNER JOIN {$this->workflows_table()} w ON w.id = e.workflow_id
                 WHERE {$gap_where}
                   AND w.demo_session_hash = %s
                   AND w.surface = 'storefront'
                   AND e.event_name = 'commerce.product.viewed'
                   AND e.value IS NOT NULL
                   AND e.currency IS NOT NULL
                   AND ({$pairs})
             ) viewed
             GROUP BY viewed.capability_slug, viewed.status, viewed.currency
             ORDER BY viewed.capability_slug ASC, viewed.status ASC, viewed.currency ASC",
            $args
        );
        $result = array();
        foreach ($rows as $row) {
            $key = (string) $row['capability_slug'] . "\0" . (string) $row['status'];
            $result[$key][(string) $row['currency']] = round((float) $row['value'], 6);
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $groups Group rows.
     * @return array{0:string,1:list<string>}
     */
    private function group_pairs(array $groups, string $alias): array
    {
        $parts = array();
        $args  = array();
        foreach ($groups as $group) {
            $parts[] = "({$alias}.capability_slug = %s AND {$alias}.status = %s)";
            $args[]  = (string) $group['capability_slug'];
            $args[]  = (string) $group['status'];
        }

        return array(implode(' OR ', $parts), $args);
    }

    /**
     * @param array<string, mixed> $filters Raw filters.
     * @return array<string, mixed>
     */
    private function filters(array $filters): array
    {
        foreach (array_keys($filters) as $key) {
            if (! is_string($key) || ! in_array($key, array('status', 'product_id', 'date_from', 'date_to', 'cursor', 'limit'), true)) {
                throw new InvalidArgumentException('Unknown capability-gap filter.');
            }
        }
        $result = array('status' => null, 'product_id' => null, 'date_from' => null, 'date_to' => null, 'cursor' => null, 'limit' => 20);
        if (isset($filters['status'])) {
            if (! is_string($filters['status']) || ! in_array($filters['status'], array('open', 'planned', 'resolved', 'dismissed'), true)) {
                throw new InvalidArgumentException('Capability-gap status is invalid.');
            }
            $result['status'] = $filters['status'];
        }
        if (isset($filters['product_id'])) {
            if (! is_int($filters['product_id']) || $filters['product_id'] < 1) {
                throw new InvalidArgumentException('Product filter must be a positive integer.');
            }
            $result['product_id'] = $filters['product_id'];
        }
        if (isset($filters['date_from'])) {
            $result['date_from'] = $this->date_boundary($filters['date_from'], false);
        }
        if (isset($filters['date_to'])) {
            $result['date_to'] = $this->date_boundary($filters['date_to'], true);
        }
        if (null !== $result['date_from'] && null !== $result['date_to'] && $result['date_from'] >= $result['date_to']) {
            throw new InvalidArgumentException('date_from must be before date_to.');
        }
        if (isset($filters['cursor'])) {
            if (! is_string($filters['cursor']) || strlen($filters['cursor']) > 100) {
                throw new InvalidArgumentException('Capability-gap cursor is invalid.');
            }
            $result['cursor'] = $filters['cursor'];
        }
        if (isset($filters['limit'])) {
            if (! is_int($filters['limit']) || $filters['limit'] < 1 || $filters['limit'] > 50) {
                throw new InvalidArgumentException('Capability-gap limit must be between 1 and 50.');
            }
            $result['limit'] = $filters['limit'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $filters Normalized filters.
     * @return array{0:string,1:list<mixed>}
     */
    private function scope(string $session_hash_hex, array $filters, string $alias): array
    {
        $where = "{$alias}.demo_session_hash = %s AND ({$alias}.signal_category = 'capability_gap' OR {$alias}.signal_category IS NULL)";
        $args  = array($session_hash_hex);
        if (null !== $filters['status']) {
            $where .= " AND {$alias}.status = %s";
            $args[] = $filters['status'];
        }
        if (null !== $filters['product_id']) {
            $where .= " AND {$alias}.related_product_id = %d";
            $args[] = $filters['product_id'];
        }
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

    /** @param mixed $value Date string. */
    private function date_boundary($value, bool $exclusive_end): string
    {
        if (! is_string($value) || 1 !== preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
            throw new InvalidArgumentException('Capability-gap dates must use YYYY-MM-DD.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Capability-gap date is invalid.');
        }
        if ($exclusive_end) {
            $date = $date->modify('+1 day');
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function encode_cursor(int $offset): string
    {
        return rtrim(strtr(base64_encode(Json::encode(array('offset' => $offset))), '+/', '-_'), '=');
    }

    private function decode_cursor(?string $cursor): int
    {
        if (null === $cursor) {
            return 0;
        }
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{1,100}\z/', $cursor)) {
            throw new InvalidArgumentException('Capability-gap cursor is invalid.');
        }
        $padding = strlen($cursor) % 4;
        if (0 !== $padding) {
            $cursor .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        if (false === $json) {
            throw new InvalidArgumentException('Capability-gap cursor is invalid.');
        }
        try {
            $payload = Json::decode_object($json);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Capability-gap cursor is invalid.');
        }
        if (! isset($payload['offset']) || ! is_int($payload['offset']) || $payload['offset'] < 0 || $payload['offset'] > 100000) {
            throw new InvalidArgumentException('Capability-gap cursor is invalid.');
        }

        return $payload['offset'];
    }

    private function now(): string
    {
        return $this->schema->mysql_timestamp(($this->clock)());
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

    private function gaps_table(): string
    {
        return $this->database->prefix . 'wmcp_capability_gaps';
    }
}
