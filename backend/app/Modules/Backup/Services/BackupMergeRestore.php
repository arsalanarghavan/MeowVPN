<?php

namespace App\Modules\Backup\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Non-destructive merge restore — port of SimpleVPBot_Backup_Merge_Restore.
 */
class BackupMergeRestore
{
    /** @var array<int, string> */
    protected const IMPORT_ORDER = [
        'svp_texts',
        'svp_panels',
        'svp_plan_categories',
        'svp_plans',
        'svp_l2tp_servers',
        'svp_monitor_hosts',
        'svp_panel_inbound_api',
        'svp_panel_inbound_clients',
        'svp_panel_online_daily',
        'svp_users',
        'svp_cards',
        'svp_discount_codes',
        'svp_services',
        'svp_transactions',
        'svp_receipts',
        'svp_pending_approvals',
        'svp_sync_codes',
        'svp_broadcasts',
        'svp_broadcast_queue',
        'svp_referral_events',
        'svp_user_activity',
        'svp_reseller_panel_prices',
        'svp_reseller_wholesale_lines',
        'svp_reseller_wholesale_tiers',
        'svp_reseller_wholesale_line_assignments',
        'svp_reseller_wholesale_accruals',
        'svp_reseller_parent_panel_floors',
        'svp_reseller_bot_profiles',
        'svp_reseller_closure',
        'svp_marketing_rules',
        'svp_marketing_offers',
        'svp_discount_redemptions',
        'svp_service_ip_log',
        'svp_users_bulk_jobs',
        'svp_users_bulk_job_items',
        'svp_audit_log',
        'svp_logs',
        'svp_service_transfer_codes',
    ];

    /** @var array<int, string> */
    protected const USER_FK_COLUMNS = [
        'user_id',
        'owner_svp_user_id',
        'inviter_svp_user_id',
        'resulting_svp_user_id',
        'reseller_svp_user_id',
        'parent_svp_user_id',
        'child_svp_user_id',
        'subject_svp_user_id',
        'actor_svp_user_id',
        'svp_user_id',
        'created_by_svp_user_id',
        'restricted_svp_user_id',
        'signup_reseller_svp_id',
        'owner_id',
    ];

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $dumpByTable
     * @return array<string, mixed>
     */
    public function restoreFromDump(array $dumpByTable): array
    {
        return $this->restoreMerge($dumpByTable);
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $dumpByTable
     * @return array<string, mixed>
     */
    public function restoreMerge(array $dumpByTable): array
    {
        return DB::transaction(function () use ($dumpByTable) {
            $stats = [
                'users_matched' => 0,
                'users_inserted' => 0,
                'users_skipped' => 0,
                'rows_inserted' => [],
                'rows_skipped' => [],
                'errors' => [],
            ];

            $idMaps = [];
            $usersTable = 'svp_users';
            $userRows = $dumpByTable[$usersTable] ?? [];
            unset($dumpByTable[$usersTable]);

            $userMap = $this->importUsers($userRows, $stats);
            $idMaps[$usersTable] = $userMap;

            foreach ($this->orderedTables(array_keys($dumpByTable)) as $table) {
                if ($table === $usersTable || empty($dumpByTable[$table])) {
                    continue;
                }
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $map = $this->importGenericTable($table, $dumpByTable[$table], $idMaps, $stats);
                if ($map !== []) {
                    $idMaps[$table] = $map;
                }
            }

            $this->patchUserSelfFks($userRows, $userMap, $stats);

            return $stats;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $userRows
     * @param  array<string, mixed>  $stats
     * @return array<int, int>
     */
    protected function importUsers(array $userRows, array &$stats): array
    {
        $map = [];
        $deferred = [];

        foreach ($userRows as $row) {
            $backupId = (int) ($row['id'] ?? 0);
            if ($backupId < 1) {
                $stats['errors'][] = ['table' => 'svp_users', 'reason' => 'missing_backup_user_id'];
                $stats['users_skipped']++;

                continue;
            }

            $resolve = $this->resolveLiveUser($row);
            if ($resolve['status'] === 'ambiguous') {
                $stats['errors'][] = ['table' => 'svp_users', 'reason' => 'ambiguous_identity', 'id' => $backupId];
                $stats['users_skipped']++;

                continue;
            }

            if ($resolve['status'] === 'matched' && $resolve['live_id'] > 0) {
                $map[$backupId] = $resolve['live_id'];
                $stats['users_matched']++;
                $deferred[$backupId] = [
                    'invited_by' => (int) ($row['invited_by'] ?? 0),
                    'signup_reseller_svp_id' => (int) ($row['signup_reseller_svp_id'] ?? 0),
                ];
                $this->maybeFillEmptyUserFields($resolve['live_id'], $row);

                continue;
            }

            $insert = $row;
            unset($insert['id'], $insert['invited_by'], $insert['signup_reseller_svp_id']);
            $insert = $this->filterRowToColumns($insert, 'svp_users');
            $newId = (int) DB::table('svp_users')->insertGetId($insert);
            $map[$backupId] = $newId;
            $stats['users_inserted']++;
            $deferred[$backupId] = [
                'invited_by' => (int) ($row['invited_by'] ?? 0),
                'signup_reseller_svp_id' => (int) ($row['signup_reseller_svp_id'] ?? 0),
            ];
        }

        $stats['_deferred_user_self_fk'] = $deferred;

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{status:string, live_id:int}
     */
    protected function resolveLiveUser(array $row): array
    {
        $tg = (int) ($row['tg_user_id'] ?? 0);
        $bl = (int) ($row['bale_user_id'] ?? 0);
        if ($tg < 1 && $bl < 1) {
            return ['status' => 'new', 'live_id' => 0];
        }

        $hits = [];
        if ($tg > 0) {
            $id = DB::table('svp_users')->where('tg_user_id', $tg)->value('id');
            if ($id) {
                $hits[(int) $id] = true;
            }
        }
        if ($bl > 0) {
            $id = DB::table('svp_users')->where('bale_user_id', $bl)->value('id');
            if ($id) {
                $hits[(int) $id] = true;
            }
        }

        if (count($hits) > 1) {
            return ['status' => 'ambiguous', 'live_id' => 0];
        }
        if (count($hits) === 1) {
            return ['status' => 'matched', 'live_id' => (int) array_key_first($hits)];
        }

        return ['status' => 'new', 'live_id' => 0];
    }

    /** @param  array<string, mixed>  $backupRow */
    protected function maybeFillEmptyUserFields(int $liveId, array $backupRow): void
    {
        $patch = [];
        $live = DB::table('svp_users')->where('id', $liveId)->first();
        if (! $live) {
            return;
        }
        foreach (['tg_user_id', 'bale_user_id'] as $col) {
            $liveVal = (int) ($live->{$col} ?? 0);
            $bakVal = (int) ($backupRow[$col] ?? 0);
            if ($liveVal < 1 && $bakVal > 0) {
                $patch[$col] = $bakVal;
            }
        }
        if ($patch !== []) {
            DB::table('svp_users')->where('id', $liveId)->update($patch);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<int, int>>  $idMaps
     * @param  array<string, mixed>  $stats
     * @return array<int, int>
     */
    protected function importGenericTable(string $table, array $rows, array &$idMaps, array &$stats): array
    {
        $map = [];
        $cols = Schema::getColumnListing($table);
        $hasId = in_array('id', $cols, true);

        foreach ($rows as $row) {
            $backupPk = $hasId ? (int) ($row['id'] ?? 0) : 0;
            if ($hasId && $backupPk > 0 && DB::table($table)->where('id', $backupPk)->exists()) {
                $map[$backupPk] = $backupPk;
                $this->bumpStat($stats, 'rows_skipped', $table);

                continue;
            }

            $prepared = $this->remapForeignKeys($table, $row, $idMaps, $stats);
            if ($prepared === null) {
                $this->bumpStat($stats, 'rows_skipped', $table);

                continue;
            }

            $insert = $prepared;
            if ($hasId) {
                unset($insert['id']);
            }
            $insert = $this->filterRowToColumns($insert, $table);
            $newPk = (int) DB::table($table)->insertGetId($insert);
            if ($backupPk > 0 && $newPk > 0) {
                $map[$backupPk] = $newPk;
            }
            $this->bumpStat($stats, 'rows_inserted', $table);
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<int, int>>  $idMaps
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>|null
     */
    protected function remapForeignKeys(string $table, array $row, array $idMaps, array &$stats): ?array
    {
        $out = $row;
        $usersTable = 'svp_users';

        foreach (self::USER_FK_COLUMNS as $col) {
            if (! array_key_exists($col, $out)) {
                continue;
            }
            $raw = (int) $out[$col];
            if ($raw < 1) {
                continue;
            }
            if (! isset($idMaps[$usersTable][$raw])) {
                $stats['errors'][] = [
                    'table' => $table,
                    'reason' => 'missing_user_map',
                    'column' => $col,
                    'value' => $raw,
                ];

                return null;
            }
            $out[$col] = $idMaps[$usersTable][$raw];
        }

        if (isset($out['service_id'])) {
            $sid = (int) $out['service_id'];
            if ($sid > 0) {
                $svcTable = 'svp_services';
                if (! isset($idMaps[$svcTable][$sid])) {
                    $stats['errors'][] = ['table' => $table, 'reason' => 'missing_service_map', 'value' => $sid];

                    return null;
                }
                $out['service_id'] = $idMaps[$svcTable][$sid];
            }
        }

        if (isset($out['panel_id'])) {
            $pid = (int) $out['panel_id'];
            if ($pid > 0 && isset($idMaps['svp_panels'][$pid])) {
                $out['panel_id'] = $idMaps['svp_panels'][$pid];
            }
        }

        if (isset($out['plan_id'])) {
            $plid = (int) $out['plan_id'];
            if ($plid > 0 && isset($idMaps['svp_plans'][$plid])) {
                $out['plan_id'] = $idMaps['svp_plans'][$plid];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $userRows
     * @param  array<int, int>  $userMap
     * @param  array<string, mixed>  $stats
     */
    protected function patchUserSelfFks(array $userRows, array $userMap, array &$stats): void
    {
        $deferred = is_array($stats['_deferred_user_self_fk'] ?? null) ? $stats['_deferred_user_self_fk'] : [];
        unset($stats['_deferred_user_self_fk']);

        foreach ($deferred as $backupId => $fks) {
            if (! isset($userMap[$backupId])) {
                continue;
            }
            $liveId = $userMap[$backupId];
            $patch = [];
            $inv = (int) ($fks['invited_by'] ?? 0);
            if ($inv > 0 && isset($userMap[$inv])) {
                $patch['invited_by'] = $userMap[$inv];
            }
            $signup = (int) ($fks['signup_reseller_svp_id'] ?? 0);
            if ($signup > 0 && isset($userMap[$signup])) {
                $patch['signup_reseller_svp_id'] = $userMap[$signup];
            }
            if ($patch !== []) {
                DB::table('svp_users')->where('id', $liveId)->update($patch);
            }
        }
    }

    /** @param  array<int, string>  $tables */
    /** @return array<int, string> */
    protected function orderedTables(array $tables): array
    {
        $order = array_flip(self::IMPORT_ORDER);
        usort($tables, function ($a, $b) use ($order) {
            $oa = $order[$a] ?? 9999;
            $ob = $order[$b] ?? 9999;
            if ($oa === $ob) {
                return strcmp($a, $b);
            }

            return $oa <=> $ob;
        });

        return $tables;
    }

    /** @param  array<string, mixed>  $row */
    /** @return array<string, mixed> */
    protected function filterRowToColumns(array $row, string $table): array
    {
        $cols = Schema::getColumnListing($table);
        $out = [];
        foreach ($row as $k => $v) {
            if (in_array($k, $cols, true)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $stats */
    protected function bumpStat(array &$stats, string $bucket, string $table): void
    {
        if (! isset($stats[$bucket][$table])) {
            $stats[$bucket][$table] = 0;
        }
        $stats[$bucket][$table]++;
    }
}
