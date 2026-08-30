<?php

/**
 * Minimal wpdb-compatible cleanup test double.
 *
 * @package WPWebMCP\AgentOps\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Tests\Unit\Demo;

final class CleanupDatabaseDouble
{
    public string $prefix = 'wp_';

    public int $get_col_calls = 0;

    /** @var list<array{table:string,where:array<string, mixed>}> */
    public array $deletes = array();

    /** @var list<string> */
    public array $workflow_ids = array();

    public ?string $failing_delete_table = null;

    /** @param mixed ...$arguments */
    public function prepare(string $query, ...$arguments): string
    {
        unset($arguments);

        return $query;
    }

    /** @return list<string> */
    public function get_col(string $query): array
    {
        unset($query);
        ++$this->get_col_calls;

        return $this->workflow_ids;
    }

    /**
     * @param array<string, mixed> $where Row filter.
     * @param list<string>         $where_format Format list.
     * @return int|false
     */
    public function delete(string $table, array $where, array $where_format = array()): int|false
    {
        unset($where_format);
        $this->deletes[] = array('table' => $table, 'where' => $where);

        if ($this->failing_delete_table === $table) {
            return false;
        }

        return 1;
    }
}
