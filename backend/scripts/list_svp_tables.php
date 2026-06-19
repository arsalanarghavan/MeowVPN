#!/usr/bin/env php
<?php

/**
 * Assert 43 unique svp_* tables: 42 from parity SQL + svp_settings migration.
 * Usage: php backend/scripts/list_svp_tables.php
 */

$parityPath = dirname(__DIR__).'/database/schema/svp_schema.sql';
if (! is_readable($parityPath)) {
    fwrite(STDERR, "Missing parity SQL: {$parityPath}\n");
    exit(1);
}

$sql = file_get_contents($parityPath);
preg_match_all('/CREATE TABLE\s+(`?)(svp_\w+)\1/i', $sql, $matches);
$parityTables = array_values(array_unique($matches[2]));
sort($parityTables);

$duplicates = array_diff_assoc($matches[2], array_unique($matches[2]));
if ($duplicates !== []) {
    fwrite(STDERR, "Duplicate CREATE TABLE in parity SQL: ".implode(', ', array_unique($duplicates))."\n");
    exit(1);
}

$all = array_merge($parityTables, ['svp_settings']);
$all = array_values(array_unique($all));
sort($all);

$expected = 43;
$count = count($all);
if ($count !== $expected) {
    fwrite(STDERR, "Expected {$expected} unique svp_* tables, got {$count}\n");
    fwrite(STDERR, implode("\n", $all)."\n");
    exit(1);
}

echo "OK: {$count} unique svp_* tables (parity=".count($parityTables)."+settings)\n";
foreach ($all as $table) {
    echo "  - {$table}\n";
}

exit(0);
