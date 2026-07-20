<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Services\Portal\UsageSampleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsageSampleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UsageSampleService $samples): void
    {
        if (! Schema::hasTable('svp_service_usage_samples') || ! Schema::hasTable('svp_services')) {
            return;
        }
        $rows = DB::table('svp_services')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'used_traffic']);
        foreach ($rows as $row) {
            $samples->record((int) $row->id, (int) ($row->used_traffic ?? 0));
        }
        $samples->pruneOlderThanDays(120);
    }
}
