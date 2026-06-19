<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\File;

class WpDumpParser
{
    public function __construct(protected SqlInsertParser $insertParser) {}

    public function parseFile(string $path, string $prefix = 'wp_'): WpDumpData
    {
        $sql = File::get($path);

        return $this->parseSql($sql, $prefix);
    }

    public function parseSql(string $sql, string $prefix = 'wp_'): WpDumpData
    {
        $tables = [];
        $options = [];
        $wpUsers = [];
        $wpUsermeta = [];

        foreach ($this->splitStatements($sql) as $stmt) {
            if (! preg_match('/^INSERT INTO `([^`]+)`/i', $stmt, $m)) {
                continue;
            }
            $table = $m[1];
            $rows = $this->insertParser->parseInsertStatement($stmt);
            if ($rows === []) {
                continue;
            }

            if (str_ends_with($table, 'options') || preg_match('/_options$/', $table)) {
                foreach ($rows as $row) {
                    $name = (string) ($row['option_name'] ?? '');
                    if ($name !== '') {
                        $options[$name] = $row['option_value'] ?? '';
                    }
                }
                continue;
            }

            if (str_ends_with($table, 'users') && ! str_contains($table, 'svp_users') && ! str_contains($table, 'usermeta')) {
                foreach ($rows as $row) {
                    $wpUsers[] = $row;
                }
                continue;
            }

            if (str_ends_with($table, 'usermeta')) {
                foreach ($rows as $row) {
                    $wpUsermeta[] = $row;
                }
                continue;
            }

            if (str_starts_with($table, $prefix.'svp_')) {
                $target = substr($table, strlen($prefix));
                if (! isset($tables[$target])) {
                    $tables[$target] = [];
                }
                array_push($tables[$target], ...$rows);
            }
        }

        return new WpDumpData($tables, $options, $wpUsers, $wpUsermeta);
    }

    /** @return array<int, string> */
    protected function splitStatements(string $sql): array
    {
        $parts = preg_split('/;\s*\n/', $sql) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $stmt = $this->stripLeadingComments(trim($part));
            if ($stmt !== '' && stripos($stmt, 'INSERT INTO') === 0) {
                $out[] = $stmt.';';
            }
        }

        return $out;
    }

    protected function stripLeadingComments(string $sql): string
    {
        while (preg_match('/^\s*--[^\n]*\n?/', $sql)) {
            $sql = preg_replace('/^\s*--[^\n]*\n?/', '', $sql, 1) ?? $sql;
        }

        return trim($sql);
    }
}
