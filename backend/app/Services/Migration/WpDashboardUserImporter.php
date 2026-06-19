<?php

namespace App\Services\Migration;

use App\Models\DashboardUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class WpDashboardUserImporter
{
    public function __construct(protected WpOptionsDecoder $decoder) {}

    /**
     * @param  array<int, array<string, mixed>>  $wpUsers
     * @param  array<int, array<string, mixed>>  $wpUsermeta
     * @param  array<string, array<int, array<string, mixed>>>  $svpTables
     */
    public function import(
        array $wpUsers,
        array $wpUsermeta,
        array $svpTables,
        string $defaultPassword,
        bool $dryRun = false,
    ): int {
        $metaByUser = $this->groupUsermeta($wpUsermeta);
        $count = 0;

        foreach ($wpUsers as $user) {
            $wpId = (int) ($user['ID'] ?? $user['id'] ?? 0);
            if ($wpId < 1) {
                continue;
            }
            if (! $this->isWpAdmin($wpId, $metaByUser)) {
                continue;
            }
            $login = (string) ($user['user_login'] ?? '');
            if ($login === '') {
                continue;
            }
            if ($dryRun) {
                $count++;
                continue;
            }
            DashboardUser::query()->updateOrCreate(
                ['username' => $login],
                [
                    'password' => Hash::make($defaultPassword),
                    'role' => 'admin',
                ]
            );
            $this->applyUiMeta($login, $wpId, $metaByUser, $dryRun);
            $count++;
        }

        $svpUsers = $svpTables['svp_users'] ?? [];
        foreach ($svpUsers as $row) {
            if ((string) ($row['role'] ?? '') !== 'reseller') {
                continue;
            }
            $wpId = (int) ($row['wp_user_id'] ?? 0);
            $svpId = (int) ($row['id'] ?? 0);
            if ($wpId < 1 || $svpId < 1) {
                continue;
            }
            $wpUser = $this->findWpUser($wpUsers, $wpId);
            if (! $wpUser) {
                continue;
            }
            $login = (string) ($wpUser['user_login'] ?? '');
            if ($login === '') {
                continue;
            }
            if ($dryRun) {
                $count++;
                continue;
            }
            DashboardUser::query()->updateOrCreate(
                ['username' => $login],
                [
                    'password' => Hash::make($defaultPassword),
                    'role' => 'reseller',
                    'svp_user_id' => $svpId,
                ]
            );
            $this->applyUiMeta($login, $wpId, $metaByUser, $dryRun);
            $count++;
        }

        return $count;
    }

    /** @param  array<int, array<string, mixed>>  $wpUsermeta */
    /** @return array<int, array<string, mixed>> */
    protected function groupUsermeta(array $wpUsermeta): array
    {
        $out = [];
        foreach ($wpUsermeta as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $key = (string) ($row['meta_key'] ?? '');
            if ($uid < 1 || $key === '') {
                continue;
            }
            $out[$uid][$key] = $row['meta_value'] ?? '';
        }

        return $out;
    }

    /** @param  array<int, array<string, mixed>>  $metaByUser */
    protected function isWpAdmin(int $wpId, array $metaByUser): bool
    {
        $caps = (string) ($metaByUser[$wpId]['wp_capabilities'] ?? '');
        if ($caps === '') {
            return false;
        }
        $decoded = $this->decoder->decode($caps);

        return is_array($decoded) && ! empty($decoded['administrator']);
    }

    /** @param  array<int, array<string, mixed>>  $wpUsers */
    protected function findWpUser(array $wpUsers, int $wpId): ?array
    {
        foreach ($wpUsers as $user) {
            if ((int) ($user['ID'] ?? $user['id'] ?? 0) === $wpId) {
                return $user;
            }
        }

        return null;
    }

    /** @param  array<int, array<string, mixed>>  $metaByUser */
    protected function applyUiMeta(string $username, int $wpId, array $metaByUser, bool $dryRun): void
    {
        if ($dryRun || ! Schema::hasColumn('dashboard_users', 'ui_accent')) {
            return;
        }
        $meta = $metaByUser[$wpId] ?? [];
        $patch = [];
        foreach ([
            'svp_dashboard_accent' => 'ui_accent',
            'svp_dashboard_theme' => 'ui_theme',
            'svp_dashboard_sidebar' => 'ui_sidebar',
            'svp_dashboard_lang' => 'ui_lang',
        ] as $from => $to) {
            if (! empty($meta[$from])) {
                $patch[$to] = (string) $meta[$from];
            }
        }
        if ($patch !== []) {
            DashboardUser::query()->where('username', $username)->update($patch);
        }
    }
}
