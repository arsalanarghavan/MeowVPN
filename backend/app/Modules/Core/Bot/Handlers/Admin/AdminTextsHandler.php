<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminTextsHandler extends AbstractAdminHandler
{
    public const PAGE_SIZE = 10;

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
        protected AdminPermissionService $permissions,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.texts', $user, '📝 Texts');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        if ($this->permissions->permissionActorId($user) > 0) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $this->sendKeyPage($ctx, $chatId, $user, 0);
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        if (($parts[2] ?? '') === 'p' && isset($parts[3])) {
            $this->sendKeyPage($ctx, $chatId, $user, (int) $parts[3]);
        }
    }

    /** @param  array<int, string>  $parts */
    public function handlePreviewCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $key = (string) ($parts[2] ?? '');
        $row = DB::table('svp_texts')->where('key_name', $key)->first();
        $val = $row ? (string) ($row->value ?? '') : $this->texts->get($key, '—');
        $this->send($ctx, $chatId, $key.":\n".$val);
    }

    /** @param  array<int, string>  $parts */
    public function handleEditCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $key = (string) ($parts[2] ?? '');
        if ($key === '') {
            return;
        }
        $this->state->set($user, 'admin_texts_edit', ['key' => $key]);
        $this->send($ctx, $chatId, 'Send new value for '.$key);
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $trimmed = trim($text);
        $prev = $this->texts->getForUser('btn.admin.texts_prev', $user, '◀ Prev');
        $next = $this->texts->getForUser('btn.admin.texts_next', $user, 'Next ▶');
        $reset = $this->texts->getForUser('btn.admin.texts_reset_all', $user, 'Reset all');
        $resetOne = $this->texts->getForUser('btn.admin.texts_reset_one', $user, 'Reset key');
        $data = $this->state->data($user);
        $off = (int) ($data['offset'] ?? 0);

        if ($trimmed === $next) {
            $this->sendKeyPage($ctx, $chatId, $user, $off + self::PAGE_SIZE);

            return true;
        }
        if ($trimmed === $prev) {
            $this->sendKeyPage($ctx, $chatId, $user, max(0, $off - self::PAGE_SIZE));

            return true;
        }
        if ($trimmed === $reset) {
            $result = $this->mutate->applyForUser($user, 'texts_reset', []);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return true;
        }
        if ($trimmed === $resetOne) {
            $this->state->set($user, 'admin_texts_reset_one', []);
            $this->send($ctx, $chatId, 'Send key_name to reset');

            return true;
        }

        return false;
    }

    protected function sendKeyPage(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        $off = max(0, $offset);
        $total = (int) DB::table('svp_texts')->count();
        $rows = DB::table('svp_texts')->orderBy('key_name')->offset($off)->limit(self::PAGE_SIZE)->get();
        $body = "Text overrides ({$total})\nPage ".(int) floor($off / self::PAGE_SIZE + 1);
        $ik = [];
        foreach ($rows as $r) {
            $key = (string) ($r->key_name ?? '');
            $body .= "\n• ".$key;
            $ik[] = [
                ['text' => '👁 '.$key, 'callback_data' => 'pnl:th:'.$key],
                ['text' => '✏️', 'callback_data' => 'pnl:tv:'.$key],
            ];
        }
        if ($rows->isEmpty()) {
            $body .= "\n—";
        }
        $body .= "\n\n".$this->texts->getForUser('btn.admin.texts_reset_one', $user, 'Reset key');
        $body .= "\n".$this->texts->getForUser('btn.admin.texts_reset_all', $user, 'Reset all');
        $this->state->set($user, 'admin_texts_browse', ['offset' => $off]);
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'pnl:txt:p:'.max(0, $off - self::PAGE_SIZE)];
        }
        if ($total > $off + self::PAGE_SIZE) {
            $nav[] = ['text' => '▶', 'callback_data' => 'pnl:txt:p:'.($off + self::PAGE_SIZE)];
        }
        if ($nav !== []) {
            $ik[] = $nav;
        }
        $extra = $ik !== [] ? ['reply_markup' => ['inline_keyboard' => $ik]] : [];
        $this->send($ctx, $chatId, $body, $extra);
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $data = $this->state->data($user);
        $trimmed = trim($text);

        if ($state === 'admin_texts_browse' && str_contains($trimmed, '.')) {
            $this->state->set($user, 'admin_texts_edit', ['key' => $trimmed]);
            $this->send($ctx, $chatId, 'Send new value for '.$trimmed);

            return;
        }

        if ($state === 'admin_texts_edit') {
            $key = (string) ($data['key'] ?? '');
            if ($key === '') {
                $this->state->clear($user);

                return;
            }
            $result = $this->mutate->applyForUser($user, 'texts_save', ['key' => $key, 'value' => $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_texts_reset_one' && str_contains($trimmed, '.')) {
            $result = $this->mutate->applyForUser($user, 'text_reset_one', ['key' => $trimmed, 'locale' => 'fa']);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $count = Schema::hasTable('svp_texts') ? (int) DB::table('svp_texts')->count() : 0;

        return $this->texts->getForUser('msg.admin.tutorial.texts', $user, "Texts\nOverrides: {$count}");
    }
}
