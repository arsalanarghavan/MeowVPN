<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use Illuminate\Support\Facades\Schema;

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
            $this->syncPlatformIdentity($user, $ctx->platform, $from);
            $this->trackLastTelegramMirror($ctx, $user);

            return $user->fresh() ?? $user;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $from
     */
    public function syncPlatformIdentity(SvpUser $user, string $platform, array $from): void
    {
        $plat = $platform === 'bale' ? 'bale' : 'telegram';
        $updates = [];

        $fromUsername = $this->normalizeUsername((string) ($from['username'] ?? ''));
        if ($fromUsername !== '') {
            if ($plat === 'bale') {
                if ($this->columnValue($user, 'bale_username') !== $fromUsername) {
                    $updates['bale_username'] = $fromUsername;
                }
            } elseif ($this->columnValue($user, 'tg_username') !== $fromUsername) {
                $updates['tg_username'] = $fromUsername;
            }
            if (trim((string) ($user->username ?? '')) === '') {
                $updates['username'] = $fromUsername;
            }
        }

        $fromId = (int) ($from['id'] ?? 0);
        if ($fromId > 0) {
            if ($plat === 'bale') {
                if ((int) ($user->bale_user_id ?? 0) !== $fromId) {
                    $updates['bale_user_id'] = $fromId;
                }
            } elseif ((int) ($user->tg_user_id ?? 0) !== $fromId) {
                $updates['tg_user_id'] = $fromId;
            }
        }

        if (! empty($from['first_name']) || ! empty($from['last_name'])) {
            $fn = mb_substr(trim((string) ($from['first_name'] ?? '')), 0, 191, 'UTF-8');
            $ln = mb_substr(trim((string) ($from['last_name'] ?? '')), 0, 191, 'UTF-8');
            if ((string) ($user->first_name ?? '') !== $fn) {
                $updates['first_name'] = $fn;
            }
            if ((string) ($user->last_name ?? '') !== $ln) {
                $updates['last_name'] = $ln;
            }
        }

        if ($updates === []) {
            return;
        }

        foreach ($updates as $key => $value) {
            if (! $this->hasColumn($key)) {
                unset($updates[$key]);
            }
        }

        if ($updates === []) {
            return;
        }

        $user->fill($updates);
        $user->save();
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
            'username' => $this->normalizeUsername((string) ($from['username'] ?? '')),
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

        $username = $this->normalizeUsername((string) ($from['username'] ?? ''));
        if ($username !== '') {
            if ($ctx->platform === 'bale' && $this->hasColumn('bale_username')) {
                $data['bale_username'] = $username;
            } elseif ($ctx->platform === 'telegram' && $this->hasColumn('tg_username')) {
                $data['tg_username'] = $username;
            }
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

    protected function normalizeUsername(string $username): string
    {
        $username = trim($username);
        if ($username === '') {
            return '';
        }

        return mb_substr(ltrim($username, '@'), 0, 191, 'UTF-8');
    }

    protected function hasColumn(string $column): bool
    {
        if ($column === 'tg_username' || $column === 'bale_username') {
            return Schema::hasColumn('svp_users', $column);
        }

        return true;
    }

    protected function columnValue(SvpUser $user, string $column): string
    {
        if (! $this->hasColumn($column)) {
            return '';
        }

        return (string) ($user->{$column} ?? '');
    }
}
