<?php

/**
 * Deterministic demo-session workflow lifecycle management.
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

final class WorkflowService
{
    private const STATUS_ACTIVE = 'active';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_ABANDONED = 'abandoned';
    private const STATUS_EXPIRED = 'expired';

    private object $database;

    private EventRecorder $events;

    private EventSchema $schema;

    /** @var callable(): string */
    private $clock;

    /** @var callable(): string */
    private $ulid_generator;

    /** @var callable(): string */
    private $site_id_resolver;

    private int $active_ttl;

    /**
     * @param object|null       $database         wpdb-compatible database object.
     * @param callable():string $clock            UTC DATETIME provider.
     * @param callable():string $ulid_generator   ULID provider.
     * @param callable():string $site_id_resolver Stable site ID provider.
     */
    public function __construct(
        ?object $database = null,
        ?EventRecorder $events = null,
        ?EventSchema $schema = null,
        ?callable $clock = null,
        ?callable $ulid_generator = null,
        ?callable $site_id_resolver = null,
        int $active_ttl = 1800
    ) {
        if (null === $database) {
            global $wpdb;
            $database = $wpdb;
        }

        if (! is_object($database)) {
            throw new InvalidArgumentException('A wpdb-compatible database object is required.');
        }
        if ($active_ttl < 60 || $active_ttl > 86400) {
            throw new InvalidArgumentException('Workflow active TTL must be between 60 seconds and 24 hours.');
        }

        $this->database         = $database;
        $this->schema           = $schema ?? new EventSchema();
        $this->clock            = $clock ?? static fn (): string => Clock::mysql();
        $this->ulid_generator   = $ulid_generator ?? static fn (): string => IdGenerator::ulid();
        $this->site_id_resolver = $site_id_resolver ?? static fn (): string => (string) get_option('wmcp_agentsnr_site_id', '');
        $this->active_ttl       = $active_ttl;
        $this->events           = $events ?? new EventRecorder($database, $this->schema, $this->clock);
    }

    /**
     * Reuse the sole active workflow for a demo session and surface, or start one.
     *
     * The agent-monitoring surface deliberately receives its own audit workflow. Commerce
     * queries only include storefront workflows.
     *
     * @return array<string, mixed>
     */
    public function current(string $session_hash_hex, string $surface): array
    {
        $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        $surface          = $this->schema->surface($surface);
        $now              = $this->now();

        $this->expire_scope($session_hash_hex, $surface, $now);
        $workflow = $this->find_active($session_hash_hex, $surface);
        if (null !== $workflow) {
            $this->ensure_started($workflow);

            return $this->public_workflow($workflow, true);
        }

        $workflow_id = $this->new_ulid();
        $site_id     = $this->site_id();
        $row         = array(
            'id'                => $workflow_id,
            'site_id'           => $site_id,
            'demo_session_hash' => $session_hash_hex,
            'protocol'          => 'webmcp',
            'surface'           => $surface,
            'status'            => self::STATUS_ACTIVE,
            'wp_user_id'        => null,
            'actor_hash'        => null,
            'wc_session_hash'   => null,
            'client_name'       => null,
            'client_version'    => null,
            'intent_source'     => 'unknown',
            'intent_text'       => null,
            'consent_state'     => 'demo',
            'started_at'        => $now,
            'ended_at'          => null,
            'last_event_at'     => $now,
            'tool_count'        => 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        );

        $inserted = $this->database->insert(
            $this->workflows_table(),
            $row,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        if (false === $inserted) {
            $workflow = $this->find_active($session_hash_hex, $surface);
            if (null === $workflow) {
                throw new RuntimeException('The workflow could not be started.');
            }
            $this->ensure_started($workflow);

            return $this->public_workflow($workflow, true);
        }

        // A post-insert lookup makes concurrent starters converge on the same
        // lexically earliest workflow even without a schema-level composite key.
        $canonical = $this->find_active($session_hash_hex, $surface);
        if (null === $canonical) {
            throw new RuntimeException('The workflow could not be loaded after creation.');
        }
        $this->ensure_started($canonical);
        if ($canonical['id'] !== $workflow_id) {
            $this->ensure_started($row);
            $this->resolve($workflow_id, self::STATUS_ABANDONED, 'duplicate_start');
        }

        return $this->public_workflow($canonical, $canonical['id'] !== $workflow_id);
    }

    /**
     * Associate a workflow with a one-way WooCommerce session hash.
     */
    public function attach_woocommerce_session(string $workflow_id, string $wc_session_hash_hex): bool
    {
        $workflow_id        = $this->schema->workflow_id($workflow_id);
        $wc_session_hash_hex = $this->schema->hash_hex($wc_session_hash_hex);
        $sql                = $this->database->prepare(
            "UPDATE {$this->workflows_table()}
             SET wc_session_hash = %s, updated_at = %s
             WHERE id = %s
               AND (wc_session_hash IS NULL OR wc_session_hash = %s)",
            $wc_session_hash_hex,
            $this->now(),
            $workflow_id,
            $wc_session_hash_hex
        );

        if (false === $this->database->query($sql)) {
            return false;
        }

        $workflow = $this->find($workflow_id);

        return null !== $workflow && hash_equals($wc_session_hash_hex, (string) $workflow['wc_session_hash']);
    }

    /**
     * Complete a workflow exactly once.
     *
     * @return array<string, mixed>
     */
    public function complete(string $workflow_id, string $reason_code = 'resolved'): array
    {
        return $this->resolve($workflow_id, self::STATUS_COMPLETED, $reason_code);
    }

    /**
     * Abandon a workflow exactly once.
     *
     * @return array<string, mixed>
     */
    public function abandon(string $workflow_id, string $reason_code = 'abandoned'): array
    {
        return $this->resolve($workflow_id, self::STATUS_ABANDONED, $reason_code);
    }

    /**
     * Resolve an active workflow. Existing terminal state wins deterministically.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $workflow_id, string $status, string $reason_code = 'resolved'): array
    {
        $workflow_id = $this->schema->workflow_id($workflow_id);
        if (! in_array($status, array(self::STATUS_COMPLETED, self::STATUS_ABANDONED, self::STATUS_EXPIRED), true)) {
            throw new InvalidArgumentException('Unknown terminal workflow status.');
        }
        $reason_code = $this->reason_code($reason_code);
        $now         = $this->now();
        $sql         = $this->database->prepare(
            "UPDATE {$this->workflows_table()}
             SET status = %s, ended_at = %s, last_event_at = %s, updated_at = %s
             WHERE id = %s AND status = %s",
            $status,
            $now,
            $now,
            $now,
            $workflow_id,
            self::STATUS_ACTIVE
        );
        $changed = (int) $this->database->query($sql) > 0;
        $row     = $this->find($workflow_id);
        if (null === $row) {
            throw new RuntimeException('The workflow does not exist.');
        }

        if ($changed) {
            $event_name = match ($status) {
                self::STATUS_COMPLETED => EventName::WORKFLOW_COMPLETED,
                self::STATUS_ABANDONED => EventName::WORKFLOW_ABANDONED,
                self::STATUS_EXPIRED => EventName::WORKFLOW_EXPIRED,
            };
            $this->events->record(
                $workflow_id,
                $event_name,
                array('properties' => array('reason_code' => $reason_code, 'status' => $status)),
                'workflow-terminal'
            );
        }

        return $this->public_workflow($row, ! $changed);
    }

    /**
     * Expire stale active workflows, optionally within one session.
     */
    public function expire_stale(?string $session_hash_hex = null): int
    {
        if (null !== $session_hash_hex) {
            $session_hash_hex = $this->schema->hash_hex($session_hash_hex);
        }

        $now    = $this->now();
        $cutoff = $this->cutoff($now);
        $where  = 'status = %s AND last_event_at < %s';
        $args   = array(self::STATUS_ACTIVE, $cutoff);
        if (null !== $session_hash_hex) {
            $where .= ' AND demo_session_hash = %s';
            $args[] = $session_hash_hex;
        }

        $sql  = $this->prepare(
            "SELECT id FROM {$this->workflows_table()} WHERE {$where} ORDER BY last_event_at ASC, id ASC LIMIT 500",
            $args
        );
        $rows = $this->database->get_results($sql, ARRAY_A);
        $count = 0;
        foreach (is_array($rows) ? $rows : array() as $row) {
            if (! isset($row['id']) || ! is_string($row['id'])) {
                continue;
            }
            $resolved = $this->resolve($row['id'], self::STATUS_EXPIRED, 'inactivity_timeout');
            if (false === $resolved['reused']) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Load a workflow internally by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $workflow_id): ?array
    {
        $workflow_id = $this->schema->workflow_id($workflow_id);
        $sql = $this->database->prepare(
            "SELECT id, site_id, demo_session_hash, protocol, surface, status, wc_session_hash, intent_source, consent_state, started_at, ended_at, last_event_at, tool_count
             FROM {$this->workflows_table()}
             WHERE id = %s
             LIMIT 1",
            $workflow_id
        );
        $row = $this->database->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private function expire_scope(string $session_hash_hex, string $surface, string $now): void
    {
        $cutoff = $this->cutoff($now);
        $sql    = $this->database->prepare(
            "SELECT id
             FROM {$this->workflows_table()}
             WHERE demo_session_hash = %s
               AND surface = %s
               AND status = %s
               AND last_event_at < %s
             ORDER BY last_event_at ASC, id ASC
             LIMIT 20",
            $session_hash_hex,
            $surface,
            self::STATUS_ACTIVE,
            $cutoff
        );
        $rows = $this->database->get_results($sql, ARRAY_A);
        foreach (is_array($rows) ? $rows : array() as $row) {
            if (isset($row['id']) && is_string($row['id'])) {
                $this->resolve($row['id'], self::STATUS_EXPIRED, 'inactivity_timeout');
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_active(string $session_hash_hex, string $surface): ?array
    {
        $sql = $this->database->prepare(
            "SELECT id, site_id, demo_session_hash, protocol, surface, status, wc_session_hash, intent_source, consent_state, started_at, ended_at, last_event_at, tool_count
             FROM {$this->workflows_table()}
             WHERE demo_session_hash = %s
               AND surface = %s
               AND status = %s
             ORDER BY started_at ASC, id ASC
             LIMIT 1",
            $session_hash_hex,
            $surface,
            self::STATUS_ACTIVE
        );
        $row = $this->database->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $workflow Workflow row.
     */
    private function ensure_started(array $workflow): void
    {
        $this->events->record(
            (string) $workflow['id'],
            EventName::WORKFLOW_STARTED,
            array(
                'properties' => array(
                    'protocol'          => (string) $workflow['protocol'],
                    'agent_surface'     => (string) $workflow['surface'],
                    'analytics_consent' => (string) $workflow['consent_state'],
                ),
                'occurred_at' => (string) $workflow['started_at'],
            ),
            'workflow-started'
        );
    }

    /**
     * @param array<string, mixed> $row Workflow row.
     * @return array<string, mixed>
     */
    private function public_workflow(array $row, bool $reused): array
    {
        return array(
            'id'             => (string) $row['id'],
            'workflow_id'    => (string) $row['id'],
            'site_id'        => (string) $row['site_id'],
            'protocol'       => (string) $row['protocol'],
            'surface'        => (string) $row['surface'],
            'status'         => (string) $row['status'],
            'started_at'     => (string) $row['started_at'],
            'ended_at'       => null === $row['ended_at'] ? null : (string) $row['ended_at'],
            'last_event_at'  => (string) $row['last_event_at'],
            'tool_count'     => (int) $row['tool_count'],
            'reused'         => $reused,
        );
    }

    private function site_id(): string
    {
        $site_id = ($this->site_id_resolver)();
        try {
            return $this->schema->workflow_id($site_id);
        } catch (InvalidArgumentException) {
            $site_id = $this->new_ulid();
            update_option('wmcp_agentsnr_site_id', $site_id, false);

            return $site_id;
        }
    }

    private function cutoff(string $now): string
    {
        $date = new DateTimeImmutable($this->schema->mysql_timestamp($now), new DateTimeZone('UTC'));

        return $date->modify('-' . $this->active_ttl . ' seconds')->format('Y-m-d H:i:s');
    }

    private function reason_code(string $reason_code): string
    {
        $reason_code = strtolower(trim($reason_code));
        if (1 !== preg_match('/\A[a-z][a-z0-9_.-]{0,63}\z/', $reason_code)) {
            throw new InvalidArgumentException('Workflow reason code is invalid.');
        }

        return $reason_code;
    }

    private function new_ulid(): string
    {
        return $this->schema->workflow_id(($this->ulid_generator)());
    }

    private function now(): string
    {
        return $this->schema->mysql_timestamp(($this->clock)());
    }

    /**
     * @param list<mixed> $args Prepare arguments.
     */
    private function prepare(string $sql, array $args): string
    {
        return $this->database->prepare($sql, ...$args);
    }

    private function workflows_table(): string
    {
        return $this->database->prefix . 'wmcp_workflows';
    }
}
