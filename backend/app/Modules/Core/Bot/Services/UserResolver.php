<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;

class UserResolver
{
    /** @param  array<string, mixed>  $from */
    public function resolve(BotContext $ctx, array $from): ?SvpUser
    {
        $fromId = (int) ($from['id'] ?? 0);
        if ($fromId < 1) {
            return null;
        }

        $col = $ctx->platform === 'bale' ? 'bale_user_id' : 'tg_user_id';
        $user = SvpUser::query()->where($col, $fromId)->first();

        if ($user) {
            $ctx->user = $user;
            $this->trackLastTelegramMirror($ctx, $user);

            return $user;
        }

        return null;
    }

    /** @param  array<string, mixed>  $from */
    public function findOrCreateFromStart(BotContext $ctx, array $from, string $startText = ''): SvpUser
    {
        $existing = $this->resolve($ctx, $from);
        if ($existing) {
            return $existing;
        }

        $fromId = (int) ($from['id'] ?? 0);
        $autoApprove = $ctx->platform === 'telegram';

        $data = [
            'first_name' => (string) ($from['first_name'] ?? ''),
            'last_name' => (string) ($from['last_name'] ?? ''),
            'username' => (string) ($from['username'] ?? ''),
            'role' => 'user',
            'balance' => 0,
            'status' => $autoApprove ? 'approved' : 'pending',
            'admin_mode' => false,
            'state' => null,
            'state_data' => [],
            'created_at' => now(),
        ];

        if ($autoApprove) {
            $data['tg_user_id'] = $fromId;
            $data['approved_by'] = 'auto:telegram';
            $data['approved_at'] = now();
        } else {
            $data['bale_user_id'] = $fromId;
        }

        if ($ctx->isResellerBot()) {
            $data['signup_reseller_svp_id'] = $ctx->resellerSvpUserId;
        }

        $user = SvpUser::query()->create($data);
        $ctx->user = $user;
        $this->trackLastTelegramMirror($ctx, $user);

        return $user;
    }

    protected function trackLastTelegramMirror(BotContext $ctx, SvpUser $user): void
    {
        if ($ctx->platform !== 'telegram' || $ctx->isResellerBot()) {
            return;
        }

        $mid = $ctx->isMirrorBot() ? $ctx->mirrorBotId : 0;
        if ((int) ($user->last_tg_mirror_bot_id ?? 0) === $mid) {
            return;
        }

        $user->last_tg_mirror_bot_id = $mid;
        $user->save();
    }
}
