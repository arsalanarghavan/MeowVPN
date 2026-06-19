<?php

namespace App\Modules\Core\Services\Portal;

use App\Services\Commerce\ServiceProvisionService;
use Illuminate\Support\Facades\DB;

class PortalBulkOpsService
{
    public function __construct(protected ServiceProvisionService $provision) {}

    /** @return array{ok:bool, done:int, errors:int} */
    public function bulkExtendDays(int $days, int $maxOps = 200): array
    {
        $d = max(1, min(3650, $days));
        $done = 0;
        $errors = 0;
        $n = 0;
        foreach ($this->xrayServices() as $svc) {
            if ($n >= $maxOps) {
                break;
            }
            $n++;
            $res = $this->provision->addDays((int) $svc->id, $d);
            if (! empty($res['ok'])) {
                $done++;
            } else {
                $errors++;
            }
        }

        return ['ok' => true, 'done' => $done, 'errors' => $errors];
    }

    /** @return array{ok:bool, done:int, errors:int} */
    public function bulkAddVolume(int $gb, int $maxOps = 200): array
    {
        $g = max(1, $gb);
        $done = 0;
        $errors = 0;
        $n = 0;
        foreach ($this->xrayServices() as $svc) {
            if ($n >= $maxOps) {
                break;
            }
            $n++;
            $res = $this->provision->addVolume((int) $svc->id, $g, 'free');
            if (! empty($res['ok'])) {
                $done++;
            } else {
                $errors++;
            }
        }

        return ['ok' => true, 'done' => $done, 'errors' => $errors];
    }

    /** @return iterable<object> */
    protected function xrayServices(): iterable
    {
        return DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('service_type')
                    ->orWhere('service_type', '')
                    ->orWhere('service_type', 'xray');
            })
            ->orderBy('id')
            ->get();
    }
}
