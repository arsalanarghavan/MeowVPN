#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$botDir = $root.'/app/Modules/Core/Bot';
$rows = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($botDir));
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $rel = str_replace($root.'/', '', $file->getPathname());
    $content = file_get_contents($file->getPathname()) ?: '';

    if (preg_match_all("/->applyForUser\(\s*\\\$[a-zA-Z_]+,\s*'([^']+)'/", $content, $m)) {
        foreach ($m[1] as $op) {
            $rows[$op][$rel] = true;
        }
    }

    if (preg_match_all("/'mutate'\s*=>\s*\[\s*'op'\s*=>\s*'([^']+)'/", $content, $m)) {
        foreach ($m[1] as $op) {
            $rows[$op][$rel] = true;
        }
    }

    if (preg_match_all("/=>\s*\['([a-z][a-z0-9_]*)',\s*\[/", $content, $m)) {
        foreach ($m[1] as $op) {
            $rows[$op][$rel] = true;
        }
    }
}

ksort($rows);

echo "| Mutate op | Bot entry (files) |\n";
echo "|-----------|-------------------|\n";
foreach ($rows as $op => $files) {
    $list = implode(', ', array_map(fn ($f) => '`'.$f.'`', array_keys($files)));
    echo '| `'.$op.'` | '.$list." |\n";
}

echo "\nTotal mutate ops: ".count($rows)."\n";
