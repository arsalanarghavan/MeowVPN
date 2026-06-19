<?php

namespace App\Services\Migration;

class SqlInsertParser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseInsertStatement(string $stmt): array
    {
        if (! preg_match('/^INSERT INTO `([^`]+)`\s*\(([^)]+)\)\s*VALUES\s*(.+);?\s*$/is', trim($stmt), $m)) {
            return [];
        }

        $cols = array_map(fn ($c) => trim($c, " `\t\n\r"), explode(',', $m[2]));
        $valueBlock = rtrim((string) $m[3], ';');
        $tuples = $this->splitTuples($valueBlock);
        $rows = [];

        foreach ($tuples as $tuple) {
            $inner = trim($tuple, '()');
            $vals = $this->parseSqlValues($inner);
            if (count($cols) !== count($vals)) {
                continue;
            }
            $row = array_combine($cols, $vals);
            if ($row !== false) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<int, string> */
    protected function splitTuples(string $block): array
    {
        $tuples = [];
        $depth = 0;
        $cur = '';
        $inStr = false;
        $len = strlen($block);

        for ($i = 0; $i < $len; $i++) {
            $ch = $block[$i];
            if ($inStr) {
                $cur .= $ch;
                if ($ch === "'" && ($i + 1 < $len && $block[$i + 1] === "'")) {
                    $cur .= "'";
                    $i++;
                } elseif ($ch === "'") {
                    $inStr = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inStr = true;
                $cur .= $ch;
                continue;
            }
            if ($ch === '(') {
                if ($depth === 0) {
                    $cur = '';
                } else {
                    $cur .= $ch;
                }
                $depth++;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $cur;
                    $cur = '';
                } else {
                    $cur .= $ch;
                }
                continue;
            }
            if ($depth > 0) {
                $cur .= $ch;
            }
        }

        return $tuples;
    }

    /** @return array<int, mixed> */
    protected function parseSqlValues(string $chunk): array
    {
        $vals = [];
        $cur = '';
        $inStr = false;
        $len = strlen($chunk);

        for ($i = 0; $i < $len; $i++) {
            $ch = $chunk[$i];
            if ($inStr) {
                if ($ch === "'" && ($i + 1 < $len && $chunk[$i + 1] === "'")) {
                    $cur .= "'";
                    $i++;
                } elseif ($ch === "'") {
                    $inStr = false;
                } else {
                    $cur .= $ch;
                }
                continue;
            }
            if ($ch === "'") {
                $inStr = true;
                continue;
            }
            if ($ch === ',') {
                $vals[] = $this->castSqlToken(trim($cur));
                $cur = '';
                continue;
            }
            $cur .= $ch;
        }
        if ($cur !== '' || str_ends_with($chunk, ',')) {
            $vals[] = $this->castSqlToken(trim($cur));
        }

        return $vals;
    }

    protected function castSqlToken(string $token): mixed
    {
        if ($token === "''") {
            return '';
        }
        if (strtoupper($token) === 'NULL') {
            return null;
        }
        if ($token === '1' || $token === '0') {
            return $token === '1' ? 1 : 0;
        }
        if (is_numeric($token)) {
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }

        return $token;
    }
}
