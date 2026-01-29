<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function check()
    {
        $status = [
            'status' => 'ok',
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'timestamp' => now()->toIso8601String(),
        ];

        $allHealthy = $status['database'] && $status['redis'];
        $status['status'] = $allHealthy ? 'ok' : 'degraded';

        return response()->json($status, $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

