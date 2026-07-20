<?php

namespace App\Modules\Core\Jobs;

use App\Services\Commerce\ReceiptRecoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReceiptNotifyRecoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReceiptRecoveryService $recovery): void
    {
        $recovery->runNotifyRecovery();
    }
}
