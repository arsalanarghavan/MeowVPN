<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\File;

class WpBackupFilesImporter
{
    public function import(?string $sourceDir, bool $dryRun = false): int
    {
        if ($sourceDir === null || $sourceDir === '' || ! is_dir($sourceDir)) {
            return 0;
        }

        $dest = storage_path('app/backups');
        if (! is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $count = 0;
        foreach (glob(rtrim($sourceDir, '/').'/*.{zip,ZIP}', GLOB_BRACE) ?: [] as $path) {
            $name = basename($path);
            if (! preg_match('/^(simplevpbot|svp)-backup-[a-zA-Z0-9_-]+\.zip$/', $name)) {
                continue;
            }
            $target = $dest.'/'.$name;
            if ($dryRun) {
                $count++;
                continue;
            }
            if (! File::exists($target)) {
                File::copy($path, $target);
            }
            $count++;
        }

        return $count;
    }
}
