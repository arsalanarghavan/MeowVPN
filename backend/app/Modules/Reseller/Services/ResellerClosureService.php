<?php

namespace App\Modules\Reseller\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResellerClosureService
{
    public function rebuildAll(): void
    {
        if (! Schema::hasTable('svp_reseller_closure') || ! Schema::hasTable('svp_users')) {
            return;
        }

        DB::table('svp_reseller_closure')->truncate();

        $ids = DB::table('svp_users')->orderBy('id')->pluck('id');
        foreach ($ids as $rawId) {
            $uid = (int) $rawId;
            if ($uid > 0) {
                $this->rebuildForUser($uid);
            }
        }
    }

    public function rebuildForUser(int $userId): void
    {
        if ($userId < 1 || ! Schema::hasTable('svp_reseller_closure')) {
            return;
        }

        $this->deleteDescendantPaths($userId);
        $this->insertSelfAndAncestors($userId);

        foreach ($this->directChildren($userId) as $childId) {
            $this->rebuildForUser($childId);
        }
    }

    public function onInvitedByChanged(int $userId, int $oldParent, int $newParent): void
    {
        if ($userId < 1 || $oldParent === $newParent) {
            return;
        }

        if ($newParent > 0 && $this->wouldCreateCycle($userId, $newParent)) {
            return;
        }

        $this->rebuildForUser($userId);
    }

    public function wouldCreateCycle(int $userId, int $newParent): bool
    {
        return $this->isDescendantOf($userId, $newParent);
    }

    public function isDescendantOf(int $ancestorId, int $descendantId): bool
    {
        if ($ancestorId < 1 || $descendantId < 1) {
            return false;
        }

        if ($ancestorId === $descendantId) {
            return true;
        }

        if (! Schema::hasTable('svp_reseller_closure')) {
            return false;
        }

        return DB::table('svp_reseller_closure')
            ->where('ancestor_id', $ancestorId)
            ->where('descendant_id', $descendantId)
            ->exists();
    }

    /** @return array<int, int> */
    public function descendantIdsForAncestor(int $ancestorId): array
    {
        if ($ancestorId < 1 || ! Schema::hasTable('svp_reseller_closure')) {
            return [];
        }

        return DB::table('svp_reseller_closure')
            ->where('ancestor_id', $ancestorId)
            ->orderBy('descendant_id')
            ->pluck('descendant_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return array<int, int> */
    protected function directChildren(int $userId): array
    {
        return DB::table('svp_users')
            ->where('invited_by', $userId)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->all();
    }

    protected function deleteDescendantPaths(int $userId): void
    {
        DB::table('svp_reseller_closure')->where('descendant_id', $userId)->delete();
    }

    protected function insertSelfAndAncestors(int $userId): void
    {
        $user = DB::table('svp_users')->where('id', $userId)->first();
        if (! $user) {
            return;
        }

        DB::table('svp_reseller_closure')->insert([
            'ancestor_id' => $userId,
            'descendant_id' => $userId,
            'depth' => 0,
        ]);

        $parent = (int) ($user->invited_by ?? 0);
        if ($parent < 1) {
            return;
        }

        $rows = DB::table('svp_reseller_closure')
            ->where('descendant_id', $parent)
            ->get(['ancestor_id', 'depth']);

        foreach ($rows as $row) {
            $anc = (int) $row->ancestor_id;
            if ($anc < 1) {
                continue;
            }
            DB::table('svp_reseller_closure')->insert([
                'ancestor_id' => $anc,
                'descendant_id' => $userId,
                'depth' => (int) $row->depth + 1,
            ]);
        }
    }
}
