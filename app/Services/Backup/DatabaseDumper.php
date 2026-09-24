<?php

namespace App\Services\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseDumper
{
    private Connection $db;

    public function __construct()
    {
        $this->db = DB::connection();
    }

    public function driver(): string
    {
        return $this->db->getDriverName();
    }

    public function tables(): array
    {
        return match ($this->driver()) {
            'mysql', 'mariadb' => array_map(fn ($row) => array_values((array) $row)[0], $this->db->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")),
            'sqlite' => array_column($this->db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name'),
            default => throw new RuntimeException("Backups don't support the {$this->driver()} database driver."),
        };
    }

    public function header(): string
    {
        $lines = [
            '-- '.config('app.name').' database backup',
            '-- Created '.now()->toDateTimeString().' ('.config('app.timezone').'), driver: '.$this->driver(),
            '',
        ];

        if ($this->isMysql()) {
            $lines = [...$lines, 'SET NAMES utf8mb4;', 'SET FOREIGN_KEY_CHECKS = 0;', "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';", ''];
        } else {
            $lines = [...$lines, 'PRAGMA foreign_keys = OFF;', 'BEGIN TRANSACTION;', ''];
        }

        return implode("\n", $lines)."\n";
    }

    public function footer(): string
    {
        return $this->isMysql() ? "\nSET FOREIGN_KEY_CHECKS = 1;\n" : "\nCOMMIT;\nPRAGMA foreign_keys = ON;\n";
    }

    public function structure(string $table): string
    {
        $create = $this->isMysql()
            ? ((array) $this->db->selectOne('SHOW CREATE TABLE '.$this->quoteName($table)))['Create Table']
            : $this->db->selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table])->sql;

        return "\n-- Table {$table}\nDROP TABLE IF EXISTS {$this->quoteName($table)};\n{$create};\n";
    }

    public function primaryKey(string $table): ?string
    {
        if ($this->isMysql()) {
            $keys = $this->db->select('SHOW KEYS FROM '.$this->quoteName($table)." WHERE Key_name = 'PRIMARY'");

            return count($keys) === 1 ? $keys[0]->Column_name : null;
        }

        $keys = array_filter($this->db->select('PRAGMA table_info('.$this->quoteName($table).')'), fn ($column) => $column->pk > 0);

        return count($keys) === 1 ? array_values($keys)[0]->name : null;
    }

    public function rows(string $table, ?string $key, array $cursor, int $limit): array
    {
        $query = $this->db->table($table)->limit($limit);

        if ($key) {
            $query->orderBy($key);
            if (array_key_exists('key', $cursor)) {
                $query->where($key, '>', $cursor['key']);
            }
        } else {
            $query->offset($cursor['offset'] ?? 0);
        }

        $rows = $query->get()->map(fn ($row) => (array) $row)->all();

        if (! $rows) {
            return ['sql' => '', 'rows' => 0, 'cursor' => $cursor];
        }

        $columns = implode(', ', array_map(fn ($column) => $this->quoteName($column), array_keys($rows[0])));
        $values = implode(",\n", array_map(fn ($row) => '('.implode(', ', array_map(fn ($value) => $this->quoteValue($value), $row)).')', $rows));

        $next = $key ? ['key' => end($rows)[$key]] : ['offset' => ($cursor['offset'] ?? 0) + count($rows)];

        return [
            'sql' => "INSERT INTO {$this->quoteName($table)} ({$columns}) VALUES\n{$values};\n",
            'rows' => count($rows),
            'cursor' => $next,
        ];
    }

    private function isMysql(): bool
    {
        return in_array($this->driver(), ['mysql', 'mariadb'], true);
    }

    private function quoteName(string $name): string
    {
        return $this->isMysql() ? '`'.str_replace('`', '``', $name).'`' : '"'.str_replace('"', '""', $name).'"';
    }

    private function quoteValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => $this->db->getPdo()->quote((string) $value),
        };
    }
}
