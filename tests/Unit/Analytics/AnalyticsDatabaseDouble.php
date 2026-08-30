<?php

/**
 * Small wpdb-compatible doubles for dependency-free analytics unit tests.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Analytics;

final class AnalyticsDatabaseDouble
{
    public string $prefix = 'wp_';

    /** @var array<string, array<string, mixed>> */
    public array $workflows = array();

    /** @var list<array<string, mixed>> */
    public array $events = array();

    /** @var array<string, array<string, mixed>> */
    public array $gaps = array();

    /** @var list<array{sql:string,args:list<mixed>}> */
    public array $prepared_queries = array();

    /** @var array<string, array{sql:string,args:list<mixed>}> */
    private array $prepared = array();

    private int $sequence = 0;

    /**
     * @param mixed ...$args Prepare values.
     */
    public function prepare(string $sql, ...$args): string
    {
        $token = '__prepared_' . ++$this->sequence . '__';
        $this->prepared[$token] = array('sql' => $sql, 'args' => $args);
        $this->prepared_queries[] = array('sql' => $sql, 'args' => $args);

        return $token;
    }

    /**
     * @param array<string, mixed> $row Row to insert.
     * @param list<string>         $formats Ignored wpdb formats.
     * @return int|false
     */
    public function insert(string $table, array $row, array $formats = array())
    {
        unset($formats);
        if ($this->prefix . 'wmcp_workflows' === $table) {
            if (isset($this->workflows[$row['id']])) {
                return false;
            }
            $this->workflows[(string) $row['id']] = $row;

            return 1;
        }
        if ($this->prefix . 'wmcp_events' === $table) {
            foreach ($this->events as $existing) {
                if ($existing['dedupe_key'] === $row['dedupe_key'] || $existing['event_id'] === $row['event_id']) {
                    return false;
                }
            }
            $row['id'] = count($this->events) + 1;
            $this->events[] = $row;

            return 1;
        }
        if ($this->prefix . 'wmcp_capability_gaps' === $table) {
            if (isset($this->gaps[$row['id']])) {
                return false;
            }
            $this->gaps[(string) $row['id']] = $row;

            return 1;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_row(string $prepared, string $output): ?array
    {
        unset($output);
        [$sql, $args] = $this->query_parts($prepared);
        if (str_contains($sql, 'FROM wp_wmcp_events') && str_contains($sql, 'WHERE dedupe_key = %s')) {
            foreach ($this->events as $event) {
                if ($event['dedupe_key'] === ($args[0] ?? null)) {
                    return $event;
                }
            }

            return null;
        }
        if (str_contains($sql, 'FROM wp_wmcp_workflows') && str_contains($sql, 'demo_session_hash = %s') && str_contains($sql, 'status = %s')) {
            $matches = array_values(
                array_filter(
                    $this->workflows,
                    static fn (array $row): bool => $row['demo_session_hash'] === ($args[0] ?? null)
                        && $row['surface'] === ($args[1] ?? null)
                        && $row['status'] === ($args[2] ?? null)
                )
            );
            usort($matches, static fn (array $left, array $right): int => strcmp($left['started_at'] . $left['id'], $right['started_at'] . $right['id']));

            return $matches[0] ?? null;
        }
        if (str_contains($sql, 'FROM wp_wmcp_workflows') && str_contains($sql, 'WHERE id = %s')) {
            return $this->workflows[(string) ($args[0] ?? '')] ?? null;
        }
        if (str_contains($sql, 'FROM wp_wmcp_capability_gaps') && str_contains($sql, 'g.user_goal_redacted = %s')) {
            foreach ($this->gaps as $gap) {
                if (
                    $gap['demo_session_hash'] !== ($args[0] ?? null)
                    || $gap['workflow_id'] !== ($args[1] ?? null)
                    || $gap['capability_slug'] !== ($args[2] ?? null)
                    || $gap['user_goal_redacted'] !== ($args[3] ?? null)
                ) {
                    continue;
                }
                $offset = 4;
                if (str_contains($sql, 'g.related_product_id = %d')) {
                    if ($gap['related_product_id'] !== ($args[$offset] ?? null)) {
                        continue;
                    }
                    ++$offset;
                } elseif (null !== $gap['related_product_id']) {
                    continue;
                }
                if (str_contains($sql, 'g.context_json = %s')) {
                    if ($gap['context_json'] !== ($args[$offset] ?? null)) {
                        continue;
                    }
                } elseif (null !== $gap['context_json']) {
                    continue;
                }

                return $gap;
            }
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function get_var(string $prepared)
    {
        [$sql, $args] = $this->query_parts($prepared);
        if (str_contains($sql, 'COALESCE(MAX(step_number), 0) + 1')) {
            $max = 0;
            foreach ($this->events as $event) {
                if ($event['workflow_id'] === ($args[0] ?? null)) {
                    $max = max($max, (int) $event['step_number']);
                }
            }

            return $max + 1;
        }
        if (str_contains($sql, 'SELECT w.id') && str_contains($sql, 'w.demo_session_hash = %s')) {
            $workflow = $this->workflows[(string) ($args[1] ?? '')] ?? null;

            return null !== $workflow
                && $workflow['demo_session_hash'] === ($args[0] ?? null)
                && 'storefront' === $workflow['surface']
                ? $workflow['id']
                : null;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_results(string $prepared, string $output): array
    {
        unset($output);
        [$sql, $args] = $this->query_parts($prepared);
        if (str_contains($sql, 'FROM wp_wmcp_workflows') && str_contains($sql, 'last_event_at < %s')) {
            $matches = array();
            foreach ($this->workflows as $workflow) {
                if ('active' !== $workflow['status']) {
                    continue;
                }
                $cutoff = $args[count($args) - 1];
                if ($workflow['last_event_at'] < $cutoff) {
                    $matches[] = array('id' => $workflow['id']);
                }
            }

            return $matches;
        }

        return array();
    }

    /**
     * @return int|false
     */
    public function query(string $prepared)
    {
        [$sql, $args] = $this->query_parts($prepared);
        if (str_contains($sql, 'SET last_event_at = %s')) {
            $workflow_id = (string) ($args[2] ?? '');
            if (! isset($this->workflows[$workflow_id])) {
                return 0;
            }
            $this->workflows[$workflow_id]['last_event_at'] = $args[0];
            $this->workflows[$workflow_id]['updated_at']    = $args[1];
            if (str_contains($sql, 'tool_count = tool_count + 1')) {
                ++$this->workflows[$workflow_id]['tool_count'];
            }

            return 1;
        }
        if (str_contains($sql, 'SET status = %s')) {
            $workflow_id = (string) ($args[4] ?? '');
            if (! isset($this->workflows[$workflow_id]) || $this->workflows[$workflow_id]['status'] !== ($args[5] ?? null)) {
                return 0;
            }
            $this->workflows[$workflow_id]['status']        = $args[0];
            $this->workflows[$workflow_id]['ended_at']      = $args[1];
            $this->workflows[$workflow_id]['last_event_at'] = $args[2];
            $this->workflows[$workflow_id]['updated_at']    = $args[3];

            return 1;
        }
        if (str_contains($sql, 'SET wc_session_hash = %s')) {
            $workflow_id = (string) ($args[2] ?? '');
            if (! isset($this->workflows[$workflow_id])) {
                return 0;
            }
            $existing = $this->workflows[$workflow_id]['wc_session_hash'];
            if (null !== $existing && $existing !== ($args[3] ?? null)) {
                return 0;
            }
            $this->workflows[$workflow_id]['wc_session_hash'] = $args[0];
            $this->workflows[$workflow_id]['updated_at']      = $args[1];

            return 1;
        }

        return 0;
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function query_parts(string $prepared): array
    {
        $parts = $this->prepared[$prepared] ?? array('sql' => $prepared, 'args' => array());

        return array($parts['sql'], $parts['args']);
    }
}
