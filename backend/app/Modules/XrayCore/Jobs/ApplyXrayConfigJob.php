<?php

namespace App\Modules\XrayCore\Jobs;

use App\Modules\XrayCore\Services\NativeXrayProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplyXrayConfigJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $nodeId) {}

    public function handle(NativeXrayProvisioner $provisioner): void
    {
        if (! svp_modules()->isEnabled('xray_core')) {
            return;
        }
        $provisioner->pushNodeConfig($this->nodeId);
    }
}
