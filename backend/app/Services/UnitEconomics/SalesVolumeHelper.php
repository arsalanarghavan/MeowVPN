<?php

namespace App\Services\UnitEconomics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GB / panel attribution from approved purchase|renew transactions (WP Unit_Economics_Sales_Volume parity).
 */
class SalesVolumeHelper
{
    /**
     * @param  object|array<string, mixed>  $tx
     * @param  array<int, object>  $plans
     * @param  array<int, object>  $services
     */
    public function gbFromTransactionRow(object|array $tx, array &$plans = [], array &$services = []): float
    {
        $tx = (object) $tx;
        $type = (string) ($tx->type ?? '');
        $meta = $this->decodeMeta($tx->meta_json ?? null);
        $this->normalizeIntentMeta($meta);
        $intent = (string) ($meta['intent'] ?? '');

        if ($type === 'purchase') {
            if ($intent === 'add_volume') {
                return max(0.0, (float) (int) ($meta['extra_gb'] ?? 0));
            }
            if (in_array($intent, ['renew_same', 'add_user_slots'], true)) {
                return 0.0;
            }
            $planId = (int) ($meta['plan_id'] ?? 0);
            if ($planId > 0) {
                $plan = $this->planCached($planId, $plans);
                if ($plan) {
                    if ($this->isPerGbPlan($plan)) {
                        return max(0.0, (float) (int) ($meta['volume_gb'] ?? 0));
                    }

                    return max(0.0, (float) (int) ($plan->traffic_gb ?? 0));
                }
                if (isset($meta['volume_gb']) && (int) $meta['volume_gb'] > 0) {
                    return max(0.0, (float) (int) $meta['volume_gb']);
                }
            }
            if (isset($meta['volume_gb']) && (int) $meta['volume_gb'] > 0) {
                return max(0.0, (float) (int) $meta['volume_gb']);
            }

            return 0.0;
        }

        if ($type === 'renew') {
            foreach (['volume_gb', 'extra_gb', 'gb', 'add_gb', 'traffic_gb', 'extra_traffic_gb'] as $key) {
                if (isset($meta[$key]) && (int) $meta[$key] > 0) {
                    return max(0.0, (float) (int) $meta[$key]);
                }
            }

            return 0.0;
        }

        return 0.0;
    }

    /**
     * @param  object|array<string, mixed>  $tx
     * @param  array<int, object>  $plans
     * @param  array<int, object>  $services
     */
    public function panelIdFromTransactionRow(object|array $tx, array &$plans = [], array &$services = []): int
    {
        $tx = (object) $tx;
        $sid = (int) ($tx->service_id ?? 0);
        if ($sid > 0) {
            $svc = $this->serviceCached($sid, $services);
            if ($svc && (int) ($svc->panel_id ?? 0) > 0) {
                return (int) $svc->panel_id;
            }
        }

        $meta = $this->decodeMeta($tx->meta_json ?? null);
        $planId = (int) ($meta['plan_id'] ?? 0);
        if ($planId > 0) {
            $plan = $this->planCached($planId, $plans);
            if ($plan && (int) ($plan->panel_id ?? 0) > 0) {
                return (int) $plan->panel_id;
            }
        }

        $metaSid = (int) ($meta['service_id'] ?? 0);
        if ($metaSid > 0) {
            $svc = $this->serviceCached($metaSid, $services);
            if ($svc && (int) ($svc->panel_id ?? 0) > 0) {
                return (int) $svc->panel_id;
            }
        }

        return 0;
    }

    /** @return array<string, mixed> */
    protected function decodeMeta(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param  array<string, mixed>  $meta */
    protected function normalizeIntentMeta(array &$meta): void
    {
        if (! isset($meta['intent']) && isset($meta['action'])) {
            $meta['intent'] = $meta['action'];
        }
    }

    protected function isPerGbPlan(object $plan): bool
    {
        $pricing = (string) ($plan->pricing_type ?? '');

        return $pricing === 'per_gb' || (float) ($plan->price_per_gb ?? 0) > 0;
    }

    /**
     * @param  array<int, object>  $plans
     */
    protected function planCached(int $planId, array &$plans): ?object
    {
        if ($planId < 1) {
            return null;
        }
        if (isset($plans[$planId])) {
            return $plans[$planId];
        }
        if (! Schema::hasTable('svp_plans')) {
            return null;
        }
        $row = DB::table('svp_plans')->where('id', $planId)->first();
        if ($row) {
            $plans[$planId] = $row;
        }

        return $plans[$planId] ?? null;
    }

    /**
     * @param  array<int, object>  $services
     */
    protected function serviceCached(int $serviceId, array &$services): ?object
    {
        if ($serviceId < 1) {
            return null;
        }
        if (isset($services[$serviceId])) {
            return $services[$serviceId];
        }
        if (! Schema::hasTable('svp_services')) {
            return null;
        }
        $row = DB::table('svp_services')->where('id', $serviceId)->first();
        if ($row) {
            $services[$serviceId] = $row;
        }

        return $services[$serviceId] ?? null;
    }
}
