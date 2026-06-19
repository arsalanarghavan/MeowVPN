<?php

namespace App\Services\AdminState;

use App\Models\SvpReceipt;
use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Support\Xui\ServiceNaming;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserDetailBuilder
{
    public function __construct(
        protected ResellerScopeService $scope,
    ) {}

    /** @return array<string, mixed> */
    public function build(int $userId, Request $request, bool $isReseller, int $actorSvpUserId): array
    {
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return ['ok' => false, 'message' => 'not_found'];
        }

        if ($isReseller && ! $this->scope->resellerMayModerateUser($actorSvpUserId, $userId)) {
            return ['ok' => false, 'message' => 'forbidden'];
        }

        $services = SvpService::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(function ($svc, int $idx) {
                $row = AdminRowFormatter::rowArray($svc);
                $tt = (int) ($row['total_traffic'] ?? 0);
                $ut = (int) ($row['used_traffic'] ?? 0);
                $row['quota_gb'] = $tt > 0 ? round($tt / (1024 * 1024 * 1024), 4) : 0.0;
                $row['used_gb'] = $ut > 0 ? round($ut / (1024 * 1024 * 1024), 4) : 0.0;
                $row['subscription_name'] = ServiceNaming::formatServiceDisplayLabel($svc, $idx + 1);

                return $row;
            })
            ->all();

        $actPage = max(1, (int) $request->query('activity_page', 1));
        $rcptPage = max(1, (int) $request->query('receipts_page', 1));
        $rcptPerPage = max(1, min(100, (int) $request->query('receipts_per_page', 20)));

        $activity = [];
        $activityPagination = ['page' => $actPage, 'perPage' => 20, 'total' => 0];
        if (DB::getSchemaBuilder()->hasTable('svp_user_activity')) {
            $actQ = DB::table('svp_user_activity')->where('user_id', $userId)->orderByDesc('id');
            $activityPagination['total'] = (clone $actQ)->count();
            $activity = (clone $actQ)->offset(($actPage - 1) * 20)->limit(20)->get()->map(fn ($r) => (array) $r)->all();
        }

        $rcptQ = SvpReceipt::query()->where('user_id', $userId)->orderByDesc('id');
        $rcptTotal = (clone $rcptQ)->count();
        $receipts = (clone $rcptQ)->offset(($rcptPage - 1) * $rcptPerPage)->limit($rcptPerPage)->get()
            ->map(fn ($r) => AdminRowFormatter::formatReceipt($r))
            ->all();

        return [
            'ok' => true,
            'user' => AdminRowFormatter::sanitizeUserRow(AdminRowFormatter::rowArray($user), true),
            'services' => $services,
            'activity' => $activity,
            'activityPagination' => $activityPagination,
            'receipts' => $receipts,
            'receiptsPagination' => ['page' => $rcptPage, 'perPage' => $rcptPerPage, 'total' => $rcptTotal],
            'receiptAggregates' => [],
            'referrals' => [],
            'marketingOffers' => [],
            'planCategories' => [],
            'resellerChoices' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function search(string $q, bool $isReseller, int $actorSvpUserId, array $moderatableIds = []): array
    {
        $q = trim($q);
        if (strlen($q) > 128) {
            $q = substr($q, 0, 128);
        }

        $query = SvpUser::query();
        UserListQuery::applySearch($query, $q);

        if ($isReseller) {
            UserListQuery::applyScope($query, $moderatableIds !== [] ? $moderatableIds : $this->scope->moderatableUserIds($actorSvpUserId));
        } elseif ($moderatableIds !== []) {
            UserListQuery::applyScope($query, $moderatableIds);
        }

        $rows = $query->orderByDesc('id')->limit(20)->get()->all();

        return [
            'ok' => true,
            'users' => AdminRowFormatter::usersListRows($rows, true),
        ];
    }
}
