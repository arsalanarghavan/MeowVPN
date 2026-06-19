<?php

namespace App\Http\Controllers;

use App\Support\Metrics\SvpMetrics;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [];
        $lines[] = '# HELP svp_up Laravel app is serving metrics.';
        $lines[] = '# TYPE svp_up gauge';
        $lines[] = 'svp_up 1';

        if (Schema::hasTable('svp_users')) {
            $users = (int) DB::table('svp_users')->count();
            $lines[] = '# HELP svp_users_total Bot users in svp_users.';
            $lines[] = '# TYPE svp_users_total gauge';
            $lines[] = 'svp_users_total '.$users;
        }

        if (Schema::hasTable('svp_services')) {
            $services = (int) DB::table('svp_services')->whereNull('deleted_at')->count();
            $lines[] = '# HELP svp_services_active Active services (not deleted).';
            $lines[] = '# TYPE svp_services_active gauge';
            $lines[] = 'svp_services_active '.$services;
        }

        foreach ([
            'webhook_received_total' => 'counter',
            'mutate_op_total' => 'counter',
        ] as $metric => $type) {
            $val = SvpMetrics::get($metric);
            $lines[] = '# HELP '.$metric.' SVP '.$metric;
            $lines[] = '# TYPE '.$metric.' '.$type;
            $lines[] = $metric.' '.$val;
        }

        $cronJobs = [
            'svp:backup', 'svp:purge_expired', 'svp:broadcast', 'svp:users_bulk',
            'svp:panel_online', 'svp:panel_service_sync', 'svp:inbound_clients_cache',
            'svp:expiry', 'svp:autorenew', 'svp:idle_offers', 'svp:marketing',
            'svp:admin_alerts', 'svp:panel_economics_renewal', 'svp:inbound_queue_drain',
        ];
        $lines[] = '# HELP cron_job_duration_seconds Last cron job duration in seconds.';
        $lines[] = '# TYPE cron_job_duration_seconds gauge';
        $lines[] = 'cron_job_duration_seconds '.SvpMetrics::get('cron_job_duration_seconds');
        foreach ($cronJobs as $job) {
            $val = SvpMetrics::get('cron_job_duration_seconds:'.$job);
            if ($val > 0) {
                $safe = str_replace([':', ' '], '_', $job);
                $lines[] = 'cron_job_duration_seconds{job="'.$safe.'"} '.$val;
            }
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
