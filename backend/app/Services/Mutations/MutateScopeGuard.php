<?php

namespace App\Services\Mutations;

use App\Models\SvpReceipt;
use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\ResellerModuleGuard;
use Illuminate\Support\Facades\DB;

class MutateScopeGuard
{
    public function __construct(
        protected ResellerScopeService $scope,
        protected ResellerModuleGuard $resellerModule,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: false, message: string}|null
     */
    public function assertPayloadScope(string $op, array $payload, MutateContext $ctx): ?array
    {
        if (! $ctx->isReseller || $ctx->actorSvpUserId < 1) {
            return $this->assertAdminResellerContext($payload, $ctx);
        }

        $actorId = $ctx->actorSvpUserId;

        $targetUid = $this->resolveTargetUserId($payload);
        if ($targetUid > 0 && ! $this->scope->resellerMayModerateUser($actorId, $targetUid)) {
            return ['ok' => false, 'message' => 'forbidden_scope'];
        }

        foreach ($this->resolveServiceIds($payload) as $serviceId) {
            $svc = SvpService::query()->find($serviceId);
            $ownerId = (int) ($svc?->user_id ?? 0);
            if ($ownerId < 1 || ! $this->scope->resellerMayModerateUser($actorId, $ownerId)) {
                return ['ok' => false, 'message' => 'forbidden_scope'];
            }
        }

        $receiptId = $this->resolveReceiptId($op, $payload);
        if ($receiptId > 0) {
            $rec = SvpReceipt::query()->find($receiptId);
            $recUid = (int) ($rec?->user_id ?? 0);
            if ($recUid < 1 || ! $this->scope->resellerMayModerateUser($actorId, $recUid)) {
                return ['ok' => false, 'message' => 'forbidden_scope'];
            }
        }

        $cardId = (int) ($payload['id'] ?? $payload['card_id'] ?? 0);
        if ($cardId > 0 && in_array($op, ['card_update', 'card_delete'], true)) {
            $card = DB::table('svp_cards')->where('id', $cardId)->first();
            if (! $card) {
                return ['ok' => false, 'message' => 'forbidden_scope'];
            }
            $cardOwner = (int) ($card->owner_svp_user_id ?? 0);
            if ($cardOwner < 1 || ($cardOwner !== $actorId && ! $this->scope->resellerMayModerateUser($actorId, $cardOwner))) {
                return ['ok' => false, 'message' => 'forbidden_scope'];
            }
        }

        if ($op === 'user_service_transfer') {
            $target = trim((string) ($payload['target'] ?? ''));
            if ($target !== '') {
                $transferUser = $this->resolveTransferTarget($target);
                if (! $transferUser || ! $this->scope->resellerMayModerateUser($actorId, (int) $transferUser->id)) {
                    return ['ok' => false, 'message' => 'forbidden_scope'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrichPayload(array $payload, MutateContext $ctx): array
    {
        if ($ctx->isReseller && $ctx->actorSvpUserId > 0) {
            $payload['__actor_svp_user_id'] = $ctx->actorSvpUserId;
            $payload['owner_svp_user_id'] = $ctx->actorSvpUserId;
            if ($ctx->op === 'user_manual_create') {
                $payload['invited_by'] = $ctx->actorSvpUserId;
            }
        }

        if ($ctx->resellerContextId > 0) {
            $payload['reseller_context_svp_user_id'] = $ctx->resellerContextId;
        }

        if (array_key_exists('owner_svp_user_id', $payload)) {
            $payload['owner_svp_user_id'] = $this->resellerModule->normalizeOwnerId((int) $payload['owner_svp_user_id']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: false, message: string}|null
     */
    protected function assertAdminResellerContext(array $payload, MutateContext $ctx): ?array
    {
        if (! $ctx->isAdmin()) {
            return null;
        }

        $ownerCtx = (int) ($payload['reseller_context_svp_user_id'] ?? 0);
        if ($ownerCtx > 0 && $this->scope->validateResellerContextId($ownerCtx) === null) {
            return ['ok' => false, 'message' => 'invalid_reseller_context'];
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    protected function resolveTargetUserId(array $payload): int
    {
        foreach (['svp_user_id', 'target_user_id', 'membership_user_id', 'user_id'] as $key) {
            $id = (int) ($payload[$key] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    protected function resolveServiceIds(array $payload): array
    {
        $ids = [];
        $single = (int) ($payload['service_id'] ?? 0);
        if ($single > 0) {
            $ids[] = $single;
        }
        if (is_array($payload['service_ids'] ?? null)) {
            foreach ($payload['service_ids'] as $raw) {
                $n = (int) $raw;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param  array<string, mixed>  $payload */
    protected function resolveReceiptId(string $op, array $payload): int
    {
        $receiptId = (int) ($payload['receipt_id'] ?? 0);
        if ($receiptId < 1 && isset($payload['id']) && in_array($op, ['receipt_action', 'receipt_update', 'receipt_set_status'], true)) {
            $receiptId = (int) $payload['id'];
        }

        return $receiptId;
    }

    protected function resolveTransferTarget(string $target): ?SvpUser
    {
        if (preg_match('/^\d+$/', $target)) {
            return SvpUser::query()->find((int) $target);
        }

        $u = ltrim($target, '@');

        return SvpUser::query()
            ->where('username', $u)
            ->orWhere('phone', $u)
            ->first();
    }
}
