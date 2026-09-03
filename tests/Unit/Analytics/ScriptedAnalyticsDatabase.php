<?php

/**
 * Scripted wpdb-compatible query double.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\Analytics;

final class ScriptedAnalyticsDatabase
{
    public string $prefix = 'wp_';

    /** @var list<array<string, mixed>|null> */
    public array $row_results = array();

    /** @var list<list<array<string, mixed>>> */
    public array $result_sets = array();

    /** @var list<mixed> */
    public array $var_results = array();

    /** @var list<array{sql:string,args:list<mixed>}> */
    public array $queries = array();

    /** @var array<string, array{sql:string,args:list<mixed>}> */
    private array $prepared = array();

    private int $sequence = 0;

    /** @param mixed ...$args Prepare values. */
    public function prepare(string $sql, ...$args): string
    {
        $token = '__scripted_' . ++$this->sequence . '__';
        $this->prepared[$token] = array('sql' => $sql, 'args' => $args);
        $this->queries[] = $this->prepared[$token];

        return $token;
    }

    /** @return array<string, mixed>|null */
    public function get_row(string $prepared, string $output): ?array
    {
        unset($output);
        $this->record_unprepared($prepared);
        $row = array_shift($this->row_results);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function get_results(string $prepared, string $output): array
    {
        unset($output);
        $this->record_unprepared($prepared);
        $rows = array_shift($this->result_sets);

        return is_array($rows) ? $rows : array();
    }

    /** @return mixed */
    public function get_var(string $prepared)
    {
        $this->record_unprepared($prepared);

        return array_shift($this->var_results);
    }

    private function record_unprepared(string $prepared): void
    {
        if (! isset($this->prepared[$prepared])) {
            $this->queries[] = array('sql' => $prepared, 'args' => array());
        }
    }
}
