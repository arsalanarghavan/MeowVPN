#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$botDir = $root.'/app/Modules/Core/Bot';
$rows = [];
$dynamic = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($botDir));
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $rel = str_replace($root.'/', '', $file->getPathname());
    $content = file_get_contents($file->getPathname()) ?: '';
    if (preg_match_all("/'callback_data'\s*=>\s*'([^']+)'/", $content, $m)) {
        foreach ($m[1] as $cb) {
            if ($cb === 'noop') {
                continue;
            }
            $rows[$cb] = $rel;
        }
    }
    if (preg_match_all("/\\\$cb\s*=\s*'([^']+)'\\./", $content, $m)) {
        foreach ($m[1] as $prefix) {
            $dynamic[$prefix.'*'] = $rel;
        }
    }
}

ksort($rows);
ksort($dynamic);

echo "| Callback | Source | Handler area |\n";
echo "|----------|--------|--------------|\n";
foreach ($rows as $cb => $src) {
    $handler = match (true) {
        str_starts_with($cb, 'buy:') => 'BuyHandler / CallbackHandler',
        str_starts_with($cb, 'svc:') => 'ServiceHandler / CallbackHandler',
        str_starts_with($cb, 'wal:') => 'WalletHandler / CallbackHandler',
        str_starts_with($cb, 'rc:') => 'CallbackHandler',
        str_starts_with($cb, 'pnl:inb:') => 'AdminInboundHandler / AdminHandlerRegistry',
        str_starts_with($cb, 'pnl:relay:') => 'AdminRelayHandler',
        str_starts_with($cb, 'pnl:bk:') => 'AdminBackupHandler',
        str_starts_with($cb, 'pnl:lg:') => 'AdminLogsHandler',
        str_starts_with($cb, 'pnl:txt:') => 'AdminTextsHandler',
        str_starts_with($cb, 'pnl:res:') => 'AdminResellersHandler',
        str_starts_with($cb, 'pnl:') => 'AdminHandlerRegistry',
        default => 'Bot module',
    };
    echo '| `'.str_replace('|', '\\|', $cb).'` | `'.$src.'` | '.$handler." |\n";
}

echo "\nTotal callbacks: ".count($rows)."\n";

if ($dynamic !== []) {
    echo "\n## Dynamic callback patterns\n\n";
    echo "| Pattern | Source | Handler area |\n";
    echo "|---------|--------|--------------|\n";
    foreach ($dynamic as $pattern => $src) {
        $handler = match (true) {
            str_starts_with($pattern, 'rc:rr:') => 'CallbackHandler',
            str_starts_with($pattern, 'pnl:fin:tab:') => 'AdminFinanceHandler',
            default => 'Bot module',
        };
        echo '| `'.str_replace('|', '\\|', $pattern).'` | `'.$src.'` | '.$handler." |\n";
    }
    echo "\nTotal dynamic patterns: ".count($dynamic)."\n";
}
