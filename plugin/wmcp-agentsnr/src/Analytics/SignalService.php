<?php

/**
 * Unified, privacy-preserving opportunity and agent-feedback signals.
 *
 * The physical table retains its original capability-gap name for upgrade
 * compatibility; this service is the canonical interface for all new rows.
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
use WPWebMCP\AgentSNR\Support\Clock;
use WPWebMCP\AgentSNR\Support\IdGenerator;
use WPWebMCP\AgentSNR\Support\Json;

final class SignalService
{
    private const RESULT_BUDGET = 6500;
    private const FEEDBACK_TYPES = array(
        'worked_well',
        'missing_product',
        'missing_capability',
        'policy_unclear',
        'insufficient_product_data',
        'confusing_result',
        'constraint_encountered',
        'too_many_steps',
        'slow_response',
        'handoff_issue',
    );

    private const OUTCOMES = array('success', 'partial', 'blocked', 'abandoned');
    private const STEPS = array('discovery', 'comparison', 'policy', 'cart', 'checkout_handoff', 'journey_summary');
    private const REASONS = array(
        'useful_evidence',
        'smooth_handoff',
        'zero_results',
        'low_coverage',
        'out_of_stock',
        'budget_tradeoff',
        'missing_fact',
        'unsupported_capability',
        'unclear_policy',
        'slow_tool',
        'difficult_handoff',
    );
    private const ACTIONS = array(
        'clarify_policy',
        'improve_product_data',
        'add_capability',
        'fix_tool_error',
        'reduce_steps',
        'improve_product_coverage',
        'review_inventory',
        'review_handoff',
        'no_action',
    );
    private const RATING_VALUES = array(
        'evidence_quality' => array('sufficient', 'insufficient', 'unknown'),
        'policy_clarity'   => array('clear', 'unclear', 'not_applicable'),
        'handoff_quality'  => array('smooth', 'difficult', 'blocked', 'not_applicable'),
        'effort'           => array('low', 'medium', 'high'),
    );

    private object $database;
    private EventSchema $schema;
    private EventRecorder $events;
    private FeedbackMetricResolver $metrics;

    /** @var callable(): string */
    private $clock;

    /** @var callable(): string */
    private $ulid_generator;

    /**
     * @param callable():string|null $clock UTC DATETIME provider.
     * @param callable():string|null $ulid_generator ULID provider.
     */
    public function __construct(
        ?object $database = null,
        ?EventSchema $schema = null,
        ?EventRecorder $events = null,
        ?FeedbackMetricResolver $metrics = null,
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
        $this->database       = $database;
        $this->schema         = $schema ?? new EventSchema();
        $this->events         = $events ?? new EventRecorder($database, $this->schema, $clock);
        $this->metrics        = $metrics ?? new FeedbackMetricResolver($database, $this->schema);
        $this->clock          = $clock ?? static fn (): string => Clock::mysql();
        $this->ulid_generator = $ulid_generator ?? static fn (): string => IdGenerator::ulid();
    }

    /**
     * Persist deterministic opportunities derived from a product search.
     *
     * @param array<string, mixed> $semantic_event Commerce semantic event.
     * @param array<string, mixed> $analysis Detector output.
     * @param list<int>            $product_ids Matched public product IDs.
     * @return list<array<string, mixed>>
     */
    public function observe_search(array $semantic_event, array $analysis, array $product_ids = array()): array
    {
        $workflow_id = $this->schema->workflow_id((string) ($semantic_event['workflow_id'] ?? ''));
        $event_id    = $this->schema->event_id((string) ($semantic_event['event_id'] ?? ''));
        $workflow    = $this->storefront_workflow($workflow_id);
        $demand      = isset($analysis['demand']) && is_array($analysis['demand']) ? $analysis['demand'] : array();
        $metrics     = isset($analysis['metrics']) && is_array($analysis['metrics']) ? $analysis['metrics'] : array();
        $signals     = isset($analysis['signals']) && is_array($analysis['signals']) ? $analysis['signals'] : array();
        $key         = isset($demand['key']) && is_string($demand['key']) && 1 === preg_match('/\A[a-f0-9]{64}\z/', $demand['key'])
            ? $demand['key']
            : hash('sha256', $workflow_id . '|' . $event_id);
        $title       = isset($demand['title']) && is_string($demand['title']) ? mb_substr($demand['title'], 0, 300) : 'Product search';
        $context     = isset($demand['context']) && is_array($demand['context']) ? $demand['context'] : array();
        $records     = array();

        foreach ($signals as $signal) {
            if (! is_array($signal)) {
                continue;
            }
            $category = $this->enum($signal['category'] ?? null, array('demand_gap', 'inventory_gap', 'experience_friction'), 'signal category');
            $code     = $this->stable_code($signal['code'] ?? null, 'signal code');
            $action   = $this->enum($signal['action'] ?? 'no_action', self::ACTIONS, 'suggested action');
            $records[] = $this->persist(
                array(
                    'workflow_id'       => $workflow_id,
                    'demo_session_hash' => (string) $workflow['demo_session_hash'],
                    'signal_source'     => 'site_observed',
                    'signal_category'   => $category,
                    'signal_code'       => $code,
                    'signal_key'        => $key,
                    'title'             => $title,
                    'context'           => $context,
                    'evidence_status'   => 'verified',
                    'evidence_event_ids' => array($event_id),
                    'measured_context'  => $this->verified_metrics($metrics),
                    'suggested_action'  => $action,
                    'related_product_id' => $product_ids[0] ?? null,
                    'dedupe_key'        => hash('sha256', "observed\0{$workflow_id}\0{$code}\0{$key}\0{$event_id}"),
                )
            );
        }

        return $records;
    }

    /**
     * Persist comparison opportunities such as missing merchant facts.
     *
     * @param array<string, mixed>       $semantic_event Commerce semantic event.
     * @param list<array<string, mixed>> $signals Detector signals.
     * @param list<int>                  $product_ids Compared products.
     * @return list<array<string, mixed>>
     */
    public function observe_comparison(array $semantic_event, array $signals, array $product_ids): array
    {
        $workflow_id = $this->schema->workflow_id((string) ($semantic_event['workflow_id'] ?? ''));
        $event_id    = $this->schema->event_id((string) ($semantic_event['event_id'] ?? ''));
        $workflow    = $this->storefront_workflow($workflow_id);
        $key         = hash('sha256', 'comparison|' . implode(',', array_values(array_unique(array_map('intval', $product_ids)))));
        $records     = array();
        foreach ($signals as $signal) {
            if (! is_array($signal)) {
                continue;
            }
            $category = $this->enum($signal['category'] ?? null, array('experience_friction'), 'signal category');
            $code     = $this->stable_code($signal['code'] ?? null, 'signal code');
            $metrics  = isset($signal['metrics']) && is_array($signal['metrics']) ? $signal['metrics'] : array();
            $records[] = $this->persist(
                array(
                    'workflow_id'        => $workflow_id,
                    'demo_session_hash'  => (string) $workflow['demo_session_hash'],
                    'signal_source'      => 'site_observed',
                    'signal_category'    => $category,
                    'signal_code'        => $code,
                    'signal_key'         => $key,
                    'title'              => mb_substr((string) ($signal['title'] ?? 'Product comparison has missing facts'), 0, 300),
                    'context'            => array(),
                    'evidence_status'    => 'verified',
                    'evidence_event_ids' => array($event_id),
                    'measured_context'   => $this->verified_metrics($metrics),
                    'suggested_action'   => $this->enum($signal['action'] ?? 'improve_product_data', self::ACTIONS, 'suggested action'),
                    'related_product_id' => $product_ids[0] ?? null,
                    'dedupe_key'         => hash('sha256', "observed\0{$workflow_id}\0{$code}\0{$key}\0{$event_id}"),
                )
            );
        }

        return $records;
    }

    /**
     * Record bounded structured feedback linked to verified workflow evidence.
     *
     * @param array<string, mixed> $input Validated public tool input.
     * @return array<string, mixed>
     */
    public function report_feedback(
        string $workflow_id,
        string $session_hash_hex,
        string $request_id,
        array $input
    ): array {
        $workflow_id      = $this->schema->workflow_id($workflow_id);
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $request_id       = $this->schema->request_id($request_id);
        $this->assert_workflow_scope($workflow_id, $session_hash_hex);

        $feedback_type = $this->enum($input['feedback_type'] ?? null, self::FEEDBACK_TYPES, 'feedback type');
        $outcome       = $this->enum($input['outcome'] ?? null, self::OUTCOMES, 'feedback outcome');
        $step          = $this->enum($input['step'] ?? null, self::STEPS, 'feedback step');
        $reason        = $this->enum($input['reason_code'] ?? null, self::REASONS, 'feedback reason');
        $action        = $this->enum($input['suggested_owner_action'] ?? $this->default_action($feedback_type), self::ACTIONS, 'suggested action');
        $ratings       = $this->ratings($input['ratings'] ?? array());
        $requested     = $this->requested_metrics($input['requested_metrics'] ?? array());
        $event_ids     = isset($input['evidence_event_ids']) && is_array($input['evidence_event_ids'])
            ? array_values($input['evidence_event_ids'])
            : array();
        $evidence      = $this->metrics->evidence($session_hash_hex, $workflow_id, $event_ids);
        $measured      = $this->metrics->resolve($workflow_id, $requested);

        sort($event_ids, SORT_STRING);
        $fingerprint = hash(
            'sha256',
            Json::encode(
                array(
                    'feedback_type' => $feedback_type,
                    'outcome'       => $outcome,
                    'step'          => $step,
                    'reason'        => $reason,
                    'ratings'       => $ratings,
                    'metrics'       => $requested,
                    'action'        => $action,
                    'evidence'      => $event_ids,
                )
            )
        );
        $dedupe_key = hash('sha256', "feedback\0{$workflow_id}\0{$fingerprint}");
        $existing   = $this->find_by_dedupe($dedupe_key);
        if (null !== $existing) {
            $this->ensure_feedback_event($existing);

            return $this->public_feedback($existing, true);
        }

        $feedback_count = (int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COUNT(*) FROM {$this->signals_table()}
                 WHERE workflow_id = %s AND signal_source = 'agent_reported'
                   AND evidence_status = 'linked' AND reason_code IS NOT NULL",
                $workflow_id
            )
        );
        if (2 <= $feedback_count) {
            throw new InvalidArgumentException('This workflow has reached its structured-feedback limit.');
        }

        $subject  = $this->feedback_subject($workflow_id, $feedback_type, $step, $evidence);
        $products = $this->evidence_product_ids($evidence);
        $feedback_data = array(
                'workflow_id'         => $workflow_id,
                'demo_session_hash'   => $session_hash_hex,
                'signal_source'       => 'agent_reported',
                'signal_category'     => $subject['category'] ?? $this->feedback_category($feedback_type),
                'signal_code'         => $feedback_type,
                'signal_key'          => $subject['key'],
                'title'               => $subject['title'],
                'context'             => array(),
                'evidence_status'     => 'linked',
                'outcome'             => $outcome,
                'step_id'             => $step,
                'reason_code'         => $reason,
                'evidence_event_ids'  => $event_ids,
                'ratings'             => $ratings,
                'requested_metrics'   => $requested,
                'measured_context'    => $measured,
                'suggested_action'    => $action,
                'related_product_id'  => $products[0] ?? null,
                'dedupe_key'          => $dedupe_key,
                'request_id'          => $request_id,
        );
        $record = null;
        for ($slot = 1; $slot <= 2; ++$slot) {
            if ($this->feedback_slot_taken($workflow_id, $slot)) {
                continue;
            }
            try {
                $record = $this->persist($feedback_data + array('feedback_slot' => $slot));
                break;
            } catch (RuntimeException $exception) {
                if (! $this->feedback_slot_taken($workflow_id, $slot)) {
                    throw $exception;
                }
            }
        }
        if (null === $record) {
            throw new InvalidArgumentException('This workflow has reached its structured-feedback limit.');
        }
        $this->ensure_feedback_event($record);

        return $this->public_feedback($record, false);
    }

    /**
     * Group observed and reported signals for the current demo session.
     *
     * @param array<string, mixed> $filters Signal filters.
     * @return array<string, mixed>
     */
    public function grouped(string $session_hash_hex, array $filters = array()): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $filters          = $this->filters($filters);
        $where            = 'g.demo_session_hash = %s';
        $args             = array($session_hash_hex);
        foreach (array('signal_category' => 'category', 'signal_source' => 'source', 'status' => 'status') as $column => $key) {
            if (null !== $filters[$key]) {
                $where .= " AND g.{$column} = %s";
                $args[] = $filters[$key];
            }
        }
        if (null !== $filters['date_from']) {
            $where .= ' AND g.occurred_at >= %s';
            $args[] = $filters['date_from'];
        }
        if (null !== $filters['date_to']) {
            $where .= ' AND g.occurred_at < %s';
            $args[] = $filters['date_to'];
        }
        $offset      = $this->decode_cursor($filters['cursor']);
        $fetch_limit = $filters['limit'] + 1;
        $rows = $this->results(
            "SELECT g.signal_key, g.signal_category,
                    COALESCE(MAX(CASE WHEN g.signal_source = 'site_observed' THEN g.capability_slug ELSE NULL END), MAX(g.capability_slug)) AS signal_code,
                    MAX(g.user_goal_redacted) AS signal_title, g.status,
                    SUM(CASE WHEN g.signal_source = 'site_observed' THEN 1 ELSE 0 END) AS observed_count,
                    SUM(CASE WHEN g.signal_source = 'agent_reported' THEN 1 ELSE 0 END) AS feedback_count,
                    COUNT(DISTINCT g.workflow_id) AS affected_workflows,
                    COUNT(DISTINCT g.demo_session_hash) AS distinct_sessions,
                    MAX(g.related_product_id) AS related_product_id,
                    COALESCE(
                        MAX(CASE WHEN g.signal_source = 'site_observed' THEN g.suggested_action ELSE NULL END),
                        MAX(CASE WHEN g.signal_source = 'agent_reported' THEN g.suggested_action ELSE NULL END),
                        'no_action'
                    ) AS suggested_action,
                    MAX(g.occurred_at) AS latest_occurrence,
                    MAX(CASE WHEN g.signal_source = 'site_observed' THEN 1 ELSE 0 END) AS has_verified,
                    MAX(CASE WHEN g.evidence_status = 'linked' THEN 1 ELSE 0 END) AS has_linked
             FROM {$this->signals_table()} g
             WHERE {$where}
             GROUP BY g.signal_key, g.signal_category, g.status,
                      CASE WHEN g.signal_key IS NULL OR g.signal_key = '' THEN g.capability_slug ELSE '' END
             ORDER BY affected_workflows DESC, latest_occurrence DESC, signal_code ASC
             LIMIT {$fetch_limit} OFFSET {$offset}",
            $args
        );
        $has_more = count($rows) > $filters['limit'];
        $rows     = array_slice($rows, 0, $filters['limit']);
        $items    = array();
        foreach ($rows as $row) {
            $key      = isset($row['signal_key']) && is_string($row['signal_key']) && '' !== $row['signal_key']
                ? $row['signal_key']
                : hash('sha256', (string) $row['signal_category'] . '|' . (string) $row['signal_code'] . '|' . (string) $row['signal_title']);
            $observed = (int) ($row['observed_count'] ?? 0);
            $feedback = (int) ($row['feedback_count'] ?? 0);
            $measurement = $this->group_measurement($session_hash_hex, $row, $filters);
            $items[] = array(
                'signal_key'             => $key,
                'category'               => (string) $row['signal_category'],
                'signal_code'            => (string) $row['signal_code'],
                'title'                  => (string) $row['signal_title'],
                'status'                 => (string) $row['status'],
                'sources'                => array('site_observed' => 0 < $observed, 'agent_reported' => 0 < $feedback),
                'observed_count'         => $observed,
                'feedback_count'         => $feedback,
                'affected_workflows'     => (int) ($row['affected_workflows'] ?? 0),
                'distinct_sessions'      => (int) ($row['distinct_sessions'] ?? 0),
                'related_product_ids'    => null === ($row['related_product_id'] ?? null) ? array() : array((int) $row['related_product_id']),
                'evidence_status'        => 0 < (int) ($row['has_verified'] ?? 0) ? 'verified' : (0 < (int) ($row['has_linked'] ?? 0) ? 'linked' : 'unlinked'),
                'measured_context'       => $measurement['context'],
                'measurement_scope'      => null === $measurement['row']
                    ? null
                    : array(
                        'kind'        => 1 === (int) ($row['affected_workflows'] ?? 0)
                            ? 'single_workflow'
                            : 'latest_workflow_sample',
                        'workflow_id' => (string) $measurement['row']['workflow_id'],
                        'source'      => (string) $measurement['row']['signal_source'],
                        'occurred_at' => (string) $measurement['row']['occurred_at'],
                    ),
                'suggested_owner_action' => null === ($row['suggested_action'] ?? null) ? 'no_action' : (string) $row['suggested_action'],
                'latest_occurrence'      => (string) $row['latest_occurrence'],
            );
        }

        $trimmed = false;
        while (1 < count($items) && $this->grouped_size($items, $has_more, $filters['limit']) > self::RESULT_BUDGET) {
            array_pop($items);
            $has_more = true;
            $trimmed  = true;
        }
        if (array() !== $items && $this->grouped_size($items, $has_more, $filters['limit']) > self::RESULT_BUDGET) {
            $items[0]['measured_context']    = array();
            $items[0]['measurement_truncated'] = true;
            $trimmed = true;
        }
        $returned = count($items);

        return array(
            'scope'       => array('visibility' => 'current_demo_session'),
            'items'       => $items,
            'next_cursor' => $has_more && 0 < $returned ? $this->encode_cursor($offset + $returned) : null,
            'has_more'    => $has_more,
            'limit'       => $filters['limit'],
            'truncated'   => $trimmed,
        );
    }

    /**
     * @param array<string, mixed> $filters Normalized overview filters.
     * @return array<string, mixed>
     */
    public function summary(string $session_hash_hex, array $filters = array()): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        if (null !== ($filters['tool_name'] ?? null)) {
            return array(
                'available'       => false,
                'reason'          => 'not_available_for_tool_filter',
                'observed'        => null,
                'agent_reported'  => null,
                'workflows'       => null,
                'categories'      => null,
                'date_filter_applied' => null !== ($filters['date_from'] ?? null) || null !== ($filters['date_to'] ?? null),
                'tool_filter_applied' => false,
            );
        }
        $where = 'demo_session_hash = %s';
        $args  = array($session_hash_hex);
        if (isset($filters['date_from']) && is_string($filters['date_from'])) {
            $where .= ' AND occurred_at >= %s';
            $args[] = $this->schema->mysql_timestamp($filters['date_from']);
        }
        if (isset($filters['date_to']) && is_string($filters['date_to'])) {
            $where .= ' AND occurred_at < %s';
            $args[] = $this->schema->mysql_timestamp($filters['date_to']);
        }
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT COALESCE(SUM(CASE WHEN signal_source = 'site_observed' THEN 1 ELSE 0 END),0) AS observed,
                        COALESCE(SUM(CASE WHEN signal_source = 'agent_reported' AND evidence_status = 'linked' THEN 1 ELSE 0 END),0) AS agent_reported,
                        COUNT(DISTINCT workflow_id) AS workflows, COUNT(DISTINCT signal_category) AS categories
                 FROM {$this->signals_table()} WHERE {$where}",
                ...$args
            ),
            ARRAY_A
        );
        $row = is_array($row) ? $row : array();

        return array(
            'available'      => true,
            'observed'       => (int) ($row['observed'] ?? 0),
            'agent_reported' => (int) ($row['agent_reported'] ?? 0),
            'workflows'      => (int) ($row['workflows'] ?? 0),
            'categories'     => (int) ($row['categories'] ?? 0),
            'date_filter_applied' => null !== ($filters['date_from'] ?? null) || null !== ($filters['date_to'] ?? null),
            'tool_filter_applied' => false,
        );
    }

    /**
     * Return bounded replay collections for one scoped storefront workflow.
     *
     * @return array{opportunity_signals:list<array<string,mixed>>,agent_feedback:list<array<string,mixed>>}
     */
    public function for_workflow(string $session_hash_hex, string $workflow_id): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $workflow_id      = $this->schema->workflow_id($workflow_id);
        $this->assert_workflow_scope($workflow_id, $session_hash_hex);
        $rows = $this->results(
            "SELECT id, signal_source, signal_category, capability_slug, user_goal_redacted, status, outcome, step_id,
                    reason_code, evidence_status, evidence_event_ids_json, ratings_json, requested_metrics_json,
                    measured_context_json, suggested_action, occurred_at
             FROM {$this->signals_table()}
             WHERE demo_session_hash = %s AND workflow_id = %s
             ORDER BY occurred_at DESC, id DESC LIMIT 12",
            array($session_hash_hex, $workflow_id)
        );
        $opportunities = array();
        $feedback      = array();
        foreach (array_reverse($rows) as $row) {
            $common = array(
                'status'                 => (string) $row['status'],
                'evidence_status'        => (string) $row['evidence_status'],
                'evidence_event_ids'     => $this->decode_string_list($row['evidence_event_ids_json'] ?? null, 6),
                'measured_context'       => $this->decode_object($row['measured_context_json'] ?? null),
                'suggested_owner_action' => null === $row['suggested_action'] ? 'no_action' : (string) $row['suggested_action'],
                'occurred_at'            => (string) $row['occurred_at'],
            );
            if ('site_observed' === $row['signal_source'] && 4 > count($opportunities)) {
                $opportunities[] = array_merge(
                    $common,
                    array(
                        'signal_id'   => (string) $row['id'],
                        'source'      => 'site_observed',
                        'category'    => (string) $row['signal_category'],
                        'signal_code' => (string) $row['capability_slug'],
                        'title'       => (string) $row['user_goal_redacted'],
                    )
                );
            } elseif ('agent_reported' === $row['signal_source'] && 'linked' === $row['evidence_status'] && 4 > count($feedback)) {
                $requested = $this->decode_string_list($row['requested_metrics_json'] ?? null, 5);
                $measured  = array() === $requested
                    ? $this->decode_object($row['measured_context_json'] ?? null)
                    : $this->metrics->resolve($workflow_id, $requested);
                $common['measured_context'] = $measured;
                $feedback[] = array_merge(
                    $common,
                    array(
                        'feedback_id'      => (string) $row['id'],
                        'trust'            => 'agent_reported',
                        'feedback_type'    => (string) $row['capability_slug'],
                        'outcome'          => null === $row['outcome'] ? null : (string) $row['outcome'],
                        'step'             => null === $row['step_id'] ? null : (string) $row['step_id'],
                        'reason_code'      => null === $row['reason_code'] ? null : (string) $row['reason_code'],
                        'ratings'          => $this->decode_object($row['ratings_json'] ?? null),
                        'requested_metrics' => $requested,
                    )
                );
            }
        }

        return array('opportunity_signals' => $opportunities, 'agent_feedback' => $feedback);
    }

    /**
     * @param array<string, mixed> $data Canonical signal data.
     * @return array<string, mixed>
     */
    private function persist(array $data): array
    {
        $dedupe_key = (string) $data['dedupe_key'];
        $existing   = $this->find_by_dedupe($dedupe_key);
        if (null !== $existing) {
            $existing['replayed'] = true;
            if ('site_observed' === ($existing['signal_source'] ?? null)) {
                $this->ensure_opportunity_event($existing);
            }

            return $existing;
        }
        $occurred_at = $this->now();
        $row = array(
            'id'                       => $this->schema->workflow_id(($this->ulid_generator)()),
            'workflow_id'              => (string) $data['workflow_id'],
            'demo_session_hash'        => (string) $data['demo_session_hash'],
            'capability_slug'          => (string) $data['signal_code'],
            'user_goal_redacted'       => (string) $data['title'],
            'related_product_id'       => isset($data['related_product_id']) ? (int) $data['related_product_id'] : null,
            'context_json'             => empty($data['context']) ? null : Json::encode((array) $data['context']),
            'signal_source'            => (string) $data['signal_source'],
            'signal_category'          => (string) $data['signal_category'],
            'signal_key'               => (string) $data['signal_key'],
            'evidence_status'          => (string) $data['evidence_status'],
            'outcome'                  => $data['outcome'] ?? null,
            'step_id'                  => $data['step_id'] ?? null,
            'reason_code'              => $data['reason_code'] ?? null,
            'evidence_event_ids_json'  => Json::encode(array_values((array) $data['evidence_event_ids'])),
            'ratings_json'             => empty($data['ratings']) ? null : Json::encode((array) $data['ratings']),
            'requested_metrics_json'   => empty($data['requested_metrics']) ? null : Json::encode(array_values((array) $data['requested_metrics'])),
            'measured_context_json'    => empty($data['measured_context']) ? null : Json::encode((array) $data['measured_context']),
            'suggested_action'         => (string) $data['suggested_action'],
            'dedupe_key'               => $dedupe_key,
            'feedback_slot'            => isset($data['feedback_slot']) ? (int) $data['feedback_slot'] : null,
            'status'                   => 'open',
            'occurred_at'              => $occurred_at,
        );
        $inserted = $this->database->insert(
            $this->signals_table(),
            $row,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        if (false === $inserted) {
            $existing = $this->find_by_dedupe($dedupe_key);
            if (null === $existing) {
                throw new RuntimeException('The agent signal could not be recorded.');
            }
            $existing['replayed'] = true;

            return $existing;
        }
        $row['replayed'] = false;

        if ('site_observed' === $row['signal_source']) {
            $this->ensure_opportunity_event($row);
        }

        return $row;
    }

    /** @param array<string, mixed> $row Site-observed signal row. */
    private function ensure_opportunity_event(array $row): void
    {
        $this->events->record(
            (string) $row['workflow_id'],
            EventName::OPPORTUNITY_DETECTED,
            array(
                'product_ids' => null === $row['related_product_id'] ? array() : array((int) $row['related_product_id']),
                'properties'  => array(
                    'signal_id'        => (string) $row['id'],
                    'signal_source'    => 'site_observed',
                    'signal_category'  => (string) $row['signal_category'],
                    'signal_code'      => (string) $row['capability_slug'],
                    'signal_key'       => (string) $row['signal_key'],
                    'evidence_status'  => 'verified',
                    'evidence_count'   => count($this->decode_string_list($row['evidence_event_ids_json'] ?? null, 6)),
                    'suggested_action' => (string) $row['suggested_action'],
                ),
                'occurred_at' => (string) $row['occurred_at'],
            ),
            'signal:' . (string) $row['id']
        );
    }

    /** @param array<string, mixed> $row Signal row. */
    private function ensure_feedback_event(array $row): void
    {
        $this->events->record(
            (string) $row['workflow_id'],
            EventName::AGENT_FEEDBACK_REPORTED,
            array(
                'product_ids' => null === $row['related_product_id'] ? array() : array((int) $row['related_product_id']),
                'properties' => array(
                    'feedback_id'      => (string) $row['id'],
                    'feedback_type'    => (string) $row['capability_slug'],
                    'feedback_outcome' => null === $row['outcome'] ? null : (string) $row['outcome'],
                    'step_id'          => null === $row['step_id'] ? null : (string) $row['step_id'],
                    'evidence_status'  => (string) $row['evidence_status'],
                    'evidence_count'   => count($this->decode_string_list($row['evidence_event_ids_json'] ?? null, 6)),
                    'metric_count'     => count($this->decode_string_list($row['requested_metrics_json'] ?? null, 5)),
                    'suggested_action' => (string) $row['suggested_action'],
                ),
                'occurred_at' => (string) $row['occurred_at'],
            ),
            'feedback:' . (string) $row['id']
        );
    }

    /** @param array<string, mixed> $row Feedback row. */
    private function public_feedback(array $row, bool $replayed): array
    {
        $requested = $this->decode_string_list($row['requested_metrics_json'] ?? null, 5);
        $measured  = array() === $requested
            ? $this->decode_object($row['measured_context_json'] ?? null)
            : $this->metrics->resolve((string) $row['workflow_id'], $requested);

        return array(
            'feedback_id'             => (string) $row['id'],
            'recorded'                => true,
            'replayed'                => $replayed,
            'trust'                   => 'agent_reported',
            'evidence_status'         => (string) $row['evidence_status'],
            'evidence_event_ids'      => $this->decode_string_list($row['evidence_event_ids_json'] ?? null, 6),
            'measured_context'        => $measured,
            'suggested_owner_action'  => (string) $row['suggested_action'],
            'message'                 => 'Structured agent feedback was recorded as untrusted testimony linked to site evidence. Metric values were computed by the site.',
        );
    }

    /** @return array<string, mixed>|null */
    private function find_by_dedupe(string $dedupe_key): ?array
    {
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT * FROM {$this->signals_table()} WHERE dedupe_key = %s LIMIT 1",
                $dedupe_key
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    private function storefront_workflow(string $workflow_id): array
    {
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT id, demo_session_hash, surface FROM {$this->workflows_table()} WHERE id = %s AND surface = 'storefront' LIMIT 1",
                $workflow_id
            ),
            ARRAY_A
        );
        if (! is_array($row) || ! isset($row['demo_session_hash']) || ! is_string($row['demo_session_hash'])) {
            throw new InvalidArgumentException('Signal workflow is not a storefront workflow.');
        }
        $this->schema->hash_hex($row['demo_session_hash']);

        return $row;
    }

    private function assert_workflow_scope(string $workflow_id, string $session_hash_hex): void
    {
        $workflow = $this->storefront_workflow($workflow_id);
        if (! hash_equals($session_hash_hex, (string) $workflow['demo_session_hash'])) {
            throw new InvalidArgumentException('Workflow is not available in this demo session.');
        }
    }

    private function feedback_slot_taken(string $workflow_id, int $slot): bool
    {
        return null !== $this->database->get_var(
            $this->database->prepare(
                "SELECT id FROM {$this->signals_table()} WHERE workflow_id = %s AND feedback_slot = %d LIMIT 1",
                $workflow_id,
                $slot
            )
        );
    }

    /**
     * @param list<array<string, mixed>> $evidence Validated evidence plus semantic siblings.
     * @return array{key:string,title:string,category?:string}
     */
    private function feedback_subject(string $workflow_id, string $feedback_type, string $step, array $evidence): array
    {
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT signal_key, signal_category, user_goal_redacted, evidence_event_ids_json
                 FROM {$this->signals_table()}
                 WHERE workflow_id = %s AND signal_source = 'site_observed'
                 ORDER BY occurred_at DESC, id DESC LIMIT 20",
                $workflow_id
            ),
            ARRAY_A
        );
        $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : array();
        $evidence_ids = array_values(
            array_unique(
                array_filter(
                    array_map(static fn (array $row): ?string => is_string($row['event_id'] ?? null) ? $row['event_id'] : null, $evidence)
                )
            )
        );
        foreach ($rows as $row) {
            $signal_evidence = $this->decode_string_list($row['evidence_event_ids_json'] ?? null, 6);
            if (
                array() !== array_intersect($evidence_ids, $signal_evidence)
                && isset($row['signal_key'], $row['user_goal_redacted'])
                && is_string($row['signal_key'])
                && '' !== $row['signal_key']
            ) {
                return array(
                    'key'      => $row['signal_key'],
                    'title'    => (string) $row['user_goal_redacted'],
                    'category' => (string) $row['signal_category'],
                );
            }
        }

        $titles = array(
            'worked_well'               => 'Agent-reported journey strength',
            'missing_product'           => 'Requested product coverage is missing',
            'missing_capability'        => 'Requested site capability is missing',
            'policy_unclear'            => 'Published policy was unclear',
            'insufficient_product_data' => 'Product evidence was insufficient',
            'confusing_result'          => 'Tool result was confusing',
            'constraint_encountered'    => 'The journey required a constraint trade-off',
            'too_many_steps'            => 'The journey required too many steps',
            'slow_response'             => 'The journey felt slow',
            'handoff_issue'             => 'The human handoff needs review',
        );

        return array(
            'key'   => hash('sha256', $feedback_type . '|' . $step),
            'title' => $titles[$feedback_type],
        );
    }

    private function feedback_category(string $feedback_type): string
    {
        return match ($feedback_type) {
            'worked_well' => 'worked_well',
            'missing_product' => 'demand_gap',
            'missing_capability' => 'capability_gap',
            default => 'experience_friction',
        };
    }

    /**
     * Select one deterministic measurement row for a grouped signal. A linked
     * agent report wins because its metric request is explicit; otherwise the
     * latest site-observed row is the sample. Values are never blended across
     * workflows or presented as a group aggregate.
     *
     * @param array<string, mixed> $group Grouped database row.
     * @param array<string, mixed> $filters Normalized collection filters.
     * @return array{row:array<string,mixed>|null,context:array<string,mixed>}
     */
    private function group_measurement(string $session_hash_hex, array $group, array $filters): array
    {
        [$identity, $identity_args] = $this->group_identity($group);
        $date_scope = '';
        $date_args  = array();
        if (null !== $filters['date_from']) {
            $date_scope .= ' AND occurred_at >= %s';
            $date_args[] = $filters['date_from'];
        }
        if (null !== $filters['date_to']) {
            $date_scope .= ' AND occurred_at < %s';
            $date_args[] = $filters['date_to'];
        }
        $base_args = array_merge(
            array($session_hash_hex, (string) $group['signal_category'], (string) $group['status']),
            $identity_args,
            $date_args
        );
        $columns = 'id, workflow_id, signal_source, requested_metrics_json, measured_context_json, occurred_at';
        $row = null;
        if (null === $filters['source'] || 'agent_reported' === $filters['source']) {
            $row = $this->database->get_row(
                $this->database->prepare(
                    "SELECT {$columns} FROM {$this->signals_table()}
                     WHERE demo_session_hash = %s AND signal_category = %s AND status = %s AND {$identity}
                       AND signal_source = 'agent_reported' AND evidence_status = 'linked'{$date_scope}
                     ORDER BY occurred_at DESC, id DESC LIMIT 1",
                    ...$base_args
                ),
                ARRAY_A
            );
        }
        if (! is_array($row) && (null === $filters['source'] || 'site_observed' === $filters['source'])) {
            $row = $this->database->get_row(
                $this->database->prepare(
                    "SELECT {$columns} FROM {$this->signals_table()}
                     WHERE demo_session_hash = %s AND signal_category = %s AND status = %s AND {$identity}
                       AND signal_source = 'site_observed'{$date_scope}
                     ORDER BY occurred_at DESC, id DESC LIMIT 1",
                    ...$base_args
                ),
                ARRAY_A
            );
        }
        if (! is_array($row) || ! isset($row['workflow_id'], $row['signal_source'], $row['occurred_at'])) {
            return array('row' => null, 'context' => array());
        }

        $requested = $this->decode_string_list($row['requested_metrics_json'] ?? null, 5);
        $context   = array() === $requested
            ? $this->decode_object($row['measured_context_json'] ?? null)
            : $this->metrics->resolve((string) $row['workflow_id'], $requested);

        return array('row' => $row, 'context' => $context);
    }

    /**
     * @param array<string, mixed> $group Grouped database row.
     * @return array{0:string,1:list<string>}
     */
    private function group_identity(array $group): array
    {
        if (isset($group['signal_key']) && is_string($group['signal_key']) && '' !== $group['signal_key']) {
            return array('signal_key = %s', array($group['signal_key']));
        }

        return array(
            "(signal_key IS NULL OR signal_key = '') AND capability_slug = %s",
            array((string) $group['signal_code']),
        );
    }

    private function default_action(string $feedback_type): string
    {
        return match ($feedback_type) {
            'worked_well' => 'no_action',
            'missing_product', 'constraint_encountered' => 'improve_product_coverage',
            'missing_capability' => 'add_capability',
            'policy_unclear' => 'clarify_policy',
            'insufficient_product_data', 'confusing_result' => 'improve_product_data',
            'too_many_steps' => 'reduce_steps',
            'slow_response' => 'fix_tool_error',
            'handoff_issue' => 'review_handoff',
        };
    }

    /** @param mixed $value Enum input. */
    private function enum($value, array $allowed, string $label): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            unset($label);
            throw new InvalidArgumentException('Value is not in its allowed set.');
        }

        return $value;
    }

    /** @param mixed $value Code input. */
    private function stable_code($value, string $label): string
    {
        if (! is_string($value) || 1 !== preg_match('/\A[a-z][a-z0-9_.-]{0,63}\z/', $value)) {
            unset($label);
            throw new InvalidArgumentException('Stable code is invalid.');
        }

        return $value;
    }

    /** @param mixed $value Ratings object. */
    private function ratings($value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Feedback ratings must be an object.');
        }
        $result = array();
        foreach ($value as $name => $rating) {
            if (! is_string($name) || ! isset(self::RATING_VALUES[$name]) || ! is_string($rating) || ! in_array($rating, self::RATING_VALUES[$name], true)) {
                throw new InvalidArgumentException('Feedback rating is invalid.');
            }
            $result[$name] = $rating;
        }
        ksort($result);

        return $result;
    }

    /** @param mixed $value Requested metrics. @return list<string> */
    private function requested_metrics($value): array
    {
        if (! is_array($value) || 5 < count($value)) {
            throw new InvalidArgumentException('Feedback may request at most five metrics.');
        }
        $metrics = array();
        foreach ($value as $metric) {
            if (! is_string($metric) || ! in_array($metric, FeedbackMetricResolver::METRICS, true)) {
                throw new InvalidArgumentException('Requested feedback metric is invalid.');
            }
            $metrics[] = $metric;
        }
        if (count($metrics) !== count(array_unique($metrics))) {
            throw new InvalidArgumentException('Requested feedback metrics must be unique.');
        }

        return $metrics;
    }

    /** @param array<string, mixed> $metrics Raw detector metrics. @return array<string, array{value:mixed,status:string}> */
    private function verified_metrics(array $metrics): array
    {
        $result = array();
        foreach ($metrics as $key => $value) {
            if (is_string($key) && (is_scalar($value) || null === $value)) {
                $result[$key] = array('value' => $value, 'status' => null === $value ? 'unavailable' : 'verified');
            }
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $evidence Evidence rows. @return list<int> */
    private function evidence_product_ids(array $evidence): array
    {
        $ids = array();
        foreach ($evidence as $row) {
            foreach ($this->decode_integer_list($row['product_ids_json'] ?? null, 20) as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string, mixed> $filters Raw filters. */
    private function filters(array $filters): array
    {
        $allowed = array('category', 'source', 'status', 'date_from', 'date_to', 'cursor', 'limit');
        foreach (array_keys($filters) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unknown opportunity-signal filter.');
            }
        }
        $result = array('category' => null, 'source' => null, 'status' => null, 'date_from' => null, 'date_to' => null, 'cursor' => null, 'limit' => 8);
        if (isset($filters['category'])) {
            $result['category'] = $this->enum($filters['category'], array('demand_gap', 'inventory_gap', 'capability_gap', 'experience_friction', 'worked_well'), 'signal category');
        }
        if (isset($filters['source'])) {
            $result['source'] = $this->enum($filters['source'], array('site_observed', 'agent_reported'), 'signal source');
        }
        if (isset($filters['status'])) {
            $result['status'] = $this->enum($filters['status'], array('open', 'planned', 'resolved', 'dismissed'), 'signal status');
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
            if (! is_string($filters['cursor']) || 100 < strlen($filters['cursor'])) {
                throw new InvalidArgumentException('Signal cursor is invalid.');
            }
            $result['cursor'] = $filters['cursor'];
        }
        if (isset($filters['limit'])) {
            if (! is_int($filters['limit']) || $filters['limit'] < 1 || $filters['limit'] > 8) {
                throw new InvalidArgumentException('Signal limit must be between 1 and 8.');
            }
            $result['limit'] = $filters['limit'];
        }

        return $result;
    }

    /** @param mixed $value Date value. */
    private function date_boundary($value, bool $exclusive_end): string
    {
        if (! is_string($value) || 1 !== preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
            throw new InvalidArgumentException('Signal dates must use YYYY-MM-DD.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Signal date is invalid.');
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

    /** @param list<array<string, mixed>> $items Public grouped items. */
    private function grouped_size(array $items, bool $has_more, int $limit): int
    {
        return strlen(
            Json::encode(
                array(
                    'scope'       => array('visibility' => 'current_demo_session'),
                    'items'       => $items,
                    'next_cursor' => $has_more ? 'cursor-placeholder' : null,
                    'has_more'    => $has_more,
                    'limit'       => $limit,
                    'truncated'   => false,
                )
            )
        );
    }

    private function decode_cursor(?string $cursor): int
    {
        if (null === $cursor) {
            return 0;
        }
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{1,100}\z/', $cursor)) {
            throw new InvalidArgumentException('Signal cursor is invalid.');
        }
        $padding = strlen($cursor) % 4;
        if (0 !== $padding) {
            $cursor .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if (false === $decoded) {
            throw new InvalidArgumentException('Signal cursor is invalid.');
        }
        try {
            $payload = Json::decode_object($decoded);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Signal cursor is invalid.');
        }
        if (! isset($payload['offset']) || ! is_int($payload['offset']) || $payload['offset'] < 0 || $payload['offset'] > 100000) {
            throw new InvalidArgumentException('Signal cursor is invalid.');
        }

        return $payload['offset'];
    }

    /** @param mixed $json JSON object. */
    private function decode_object($json): array
    {
        if (! is_string($json) || '' === $json) {
            return array();
        }
        try {
            return Json::decode_object($json);
        } catch (\JsonException | InvalidArgumentException) {
            return array();
        }
    }

    /** @param mixed $json JSON list. @return list<string> */
    private function decode_string_list($json, int $limit): array
    {
        if (! is_string($json) || '' === $json) {
            return array();
        }
        try {
            $values = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return array();
        }
        if (! is_array($values)) {
            return array();
        }

        return array_values(array_filter(array_slice($values, 0, $limit), 'is_string'));
    }

    /** @param mixed $json JSON list. @return list<int> */
    private function decode_integer_list($json, int $limit): array
    {
        if (! is_string($json) || '' === $json) {
            return array();
        }
        try {
            $values = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return array();
        }
        if (! is_array($values)) {
            return array();
        }

        return array_values(array_filter(array_slice($values, 0, $limit), static fn ($value): bool => is_int($value) && 0 < $value));
    }

    /** @param list<mixed> $args SQL arguments. @return list<array<string, mixed>> */
    private function results(string $sql, array $args): array
    {
        $prepared = array() === $args ? $sql : $this->database->prepare($sql, ...$args);
        $rows     = $this->database->get_results($prepared, ARRAY_A);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : array();
    }

    private function now(): string
    {
        return $this->schema->mysql_timestamp(($this->clock)());
    }

    private function signals_table(): string
    {
        return $this->database->prefix . 'wmcp_capability_gaps';
    }

    private function workflows_table(): string
    {
        return $this->database->prefix . 'wmcp_workflows';
    }
}
