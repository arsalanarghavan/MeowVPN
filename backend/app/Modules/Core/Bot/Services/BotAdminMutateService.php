<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\Mutations\MutationPipeline;
use Illuminate\Support\Facades\DB;

class BotAdminMutateService
{
    /** @var array<string, string|null> null = site-admin only */
    protected const BOT_OP_PERMISSION = [
        'discount_save' => 'plans.manage',
        'discount_delete' => 'plans.manage',
        'marketing_rule_save' => null,
        'marketing_rule_delete' => null,
        'marketing_run_rule_now' => null,
        'reseller_panel_prices_save' => null,
        'reseller_wallet_topup_checkout' => 'plans.manage',
        'plan' => 'plans.manage',
        'plan_category' => 'plans.manage',
        'card_add' => 'plans.manage',
        'card_update' => 'plans.manage',
        'card_delete' => 'plans.manage',
        'unit_economics_config_save' => null,
        'unit_economics_save' => null,
        'panel_economics_save' => null,
        'shared_economics_save' => null,
        'panel_economics_mark_paid' => null,
    ];

    public function __construct(
        protected MutationPipeline $pipeline,
        protected AdminPermissionService $permissions,
        protected ResellerScopeService $scope,
        protected TextService $texts,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function applyForUser(SvpUser $actor, string $op, array $params = []): array
    {
        $op = strtolower(preg_replace('/[^a-z0-9_]/', '', $op) ?? '');
        if ($op === '') {
            return ['ok' => false, 'message' => 'invalid_request'];
        }

        $dash = $this->resolveDashboardActor($actor);
        if (! $dash) {
            return ['ok' => false, 'message' => 'forbidden'];
        }

        $permActor = $this->permissions->permissionActorId($actor);
        if ($permActor > 0) {
            $params['owner_svp_user_id'] = $permActor;
        }

        $gate = $this->authorize($actor, $op);
        if (empty($gate['ok'])) {
            return $gate;
        }

        $scopeErr = $this->enforceScope($actor, $op, $params);
        if ($scopeErr !== null) {
            return $scopeErr;
        }

        $out = $this->pipeline->dispatch($op, $params, $dash);

        return is_array($out['result'] ?? null) ? $out['result'] : ['ok' => false, 'message' => 'unknown_error'];
    }

    /** @param  array<string, mixed>  $result */
    public function resultMessage(SvpUser $user, array $result): string
    {
        if (! empty($result['ok'])) {
            return $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Done');
        }
        $code = (string) ($result['message'] ?? $result['code'] ?? 'error');
        if (in_array($code, ['forbidden', 'forbidden_op', 'forbidden_perm', 'forbidden_scope'], true)) {
            return $this->texts->getForUser('msg.admin.denied_permission', $user);
        }

        return '⛔ '.$code;
    }

    public function resolveDashboardActor(SvpUser $actor): ?DashboardUser
    {
        if ((int) $actor->id > 0) {
            $linked = DashboardUser::query()->where('svp_user_id', (int) $actor->id)->first();
            if ($linked) {
                return $linked;
            }
        }

        if ($actor->role === 'reseller') {
            return null;
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function authorize(SvpUser $actor, string $op): array
    {
        $permActor = $this->permissions->permissionActorId($actor);
        if ($permActor < 1) {
            if ($op === 'reseller_wallet_topup_checkout') {
                return ['ok' => false, 'message' => 'forbidden'];
            }

            return ['ok' => true];
        }

        if (array_key_exists($op, self::BOT_OP_PERMISSION)) {
            $pk = self::BOT_OP_PERMISSION[$op];
            if ($pk === null) {
                return ['ok' => false, 'message' => 'forbidden_op'];
            }
            if (! $this->permissions->hasPermission($actor, $pk)) {
                return ['ok' => false, 'message' => 'forbidden_perm'];
            }

            return ['ok' => true];
        }

        if (! $this->permissions->mayCallMutateOp($actor, $op)) {
            return ['ok' => false, 'message' => 'forbidden_perm'];
        }

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    protected function enforceScope(SvpUser $actor, string $op, array $params): ?array
    {
        $permActor = $this->permissions->permissionActorId($actor);
        if ($permActor < 1) {
            return null;
        }

        $targetUid = (int) ($params['svp_user_id'] ?? $params['user_id'] ?? $params['target_user_id'] ?? 0);
        if ($targetUid > 0 && ! $this->scope->resellerMayModerateUser($permActor, $targetUid)) {
            return ['ok' => false, 'message' => 'forbidden_scope'];
        }

        if ($op === 'plan' && isset($params['plan_id']) && (int) $params['plan_id'] > 0) {
            $row = DB::table('svp_plans')->where('id', (int) $params['plan_id'])->first();
            if ($row && (int) ($row->owner_svp_user_id ?? 0) !== $permActor && (int) ($row->owner_svp_user_id ?? 0) > 0) {
                return ['ok' => false, 'message' => 'forbidden_scope'];
            }
        }

        if (in_array($op, ['card_update', 'card_delete'], true)) {
            $cid = (int) ($params['edit_id'] ?? $params['card_id'] ?? 0);
            if ($cid > 0) {
                $row = DB::table('svp_cards')->where('id', $cid)->first();
                if ($row && (int) ($row->owner_svp_user_id ?? 0) !== $permActor && (int) ($row->owner_svp_user_id ?? 0) > 0) {
                    return ['ok' => false, 'message' => 'forbidden_scope'];
                }
            }
        }

        return null;
    }
}
