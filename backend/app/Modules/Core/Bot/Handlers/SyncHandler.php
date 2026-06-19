<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotStateService $state,
    ) {}

    public function generateCode(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $code = strtoupper(bin2hex(random_bytes(3)));
        Cache::put('svp_sync_'.$code, [
            'user_id' => $user->id,
            'platform' => $ctx->platform,
        ], 600);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->format(
            $this->texts->getForUser('msg.sync.code', $user, 'Code: {code}'),
            ['code' => $code]
        ));
    }

    public function promptCode(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->state->set($user, 'awaiting_sync_code', []);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.prompt', $user, 'Send sync code'));
    }

    public function handleCode(BotContext $ctx, SvpUser $user, int $chatId, string $code): void
    {
        $payload = Cache::get('svp_sync_'.strtoupper(trim($code)));
        if (! is_array($payload)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.invalid', $user));

            return;
        }
        $sourceUserId = (int) ($payload['user_id'] ?? 0);
        $sourcePlatform = (string) ($payload['platform'] ?? '');
        if ($sourceUserId < 1 || $sourceUserId === (int) $user->id) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.invalid', $user));

            return;
        }
        $source = SvpUser::query()->find($sourceUserId);
        if (! $source) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.invalid', $user));

            return;
        }

        if ($ctx->platform === 'bale' && empty($user->bale_user_id)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.invalid', $user));

            return;
        }
        if ($ctx->platform === 'telegram' && empty($user->tg_user_id)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.invalid', $user));

            return;
        }

        if ($ctx->platform === 'bale' && empty($source->bale_user_id)) {
            $baleId = (int) $user->bale_user_id;
            if ($baleId > 0 && (int) $user->id !== (int) $source->id) {
                $user->bale_user_id = null;
                $user->save();
            }
            $source->bale_user_id = $baleId ?: null;
            $source->save();
        } elseif ($ctx->platform === 'telegram' && empty($source->tg_user_id)) {
            $tgId = (int) $user->tg_user_id;
            if ($tgId > 0 && (int) $user->id !== (int) $source->id) {
                $user->tg_user_id = null;
                $user->save();
            }
            $source->tg_user_id = $tgId ?: null;
            $source->save();
        } else {
            DB::table('svp_services')->where('user_id', $user->id)->update(['user_id' => $sourceUserId]);
            DB::table('svp_transactions')->where('user_id', $user->id)->update(['user_id' => $sourceUserId]);
            $sourceBalance = (float) $source->balance;
            $mergeBalance = (float) $user->balance;
            if ($mergeBalance > 0) {
                $source->balance = $sourceBalance + $mergeBalance;
                $user->balance = 0;
            }
            if ((int) ($user->invited_by ?? 0) > 0 && empty($source->invited_by)) {
                $source->invited_by = (int) $user->invited_by;
            }
            $source->save();
            $user->status = 'merged';
            $user->save();
        }

        Cache::forget('svp_sync_'.strtoupper(trim($code)));
        $this->state->clear($user);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.ok', $user));
    }
}
