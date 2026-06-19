<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminLogsHandler extends AbstractAdminHandler
{
    public const PAGE_SIZE = 8;

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.logs', $user, '📋 Logs');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey, int $offset = 0): void
    {
        if ($tabKey === 'audit') {
            $this->sendAuditPage($ctx, $chatId, $user, $offset);

            return;
        }
        $this->sendLogsPage($ctx, $chatId, $user, $offset);
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $off = (int) ($parts[2] ?? 0);
        $kind = (string) ($parts[3] ?? 'logs');
        if ($kind === 'audit') {
            $this->sendAuditPage($ctx, $chatId, $user, $off);
        } else {
            $this->sendLogsPage($ctx, $chatId, $user, $off);
        }
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $clearBtn = $this->texts->getForUser('btn.admin.logs_clear', $user, 'Clear logs');
        $retentionBtn = $this->texts->getForUser('btn.admin.edit_logs_retention', $user, 'Edit logs retention');
        if (trim($text) === $clearBtn) {
            $this->state->set($user, 'admin_logs_clear', ['step' => 'confirm']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_logs_clear', $user, 'Reply yes to clear logs'));

            return true;
        }
        if (trim($text) === $retentionBtn) {
            app(AdminSettingsHandler::class)->startTabFieldWizard($ctx, $chatId, $user, 'logs', 'logs_retention_days', 'Send retention days');

            return true;
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        if ($this->state->get($user) !== 'admin_logs_clear') {
            return;
        }
        if (in_array(mb_strtolower(trim($text)), ['yes', 'y', 'بله', 'تایید'], true)) {
            $result = $this->mutate->applyForUser($user, 'logs_clear', []);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    protected function sendLogsPage(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        $off = max(0, $offset);
        $rows = [];
        $total = 0;
        if (Schema::hasTable('svp_logs')) {
            $total = (int) DB::table('svp_logs')->count();
            $rows = DB::table('svp_logs')->orderByDesc('id')->offset($off)->limit(self::PAGE_SIZE)->get()->all();
        }
        $body = "Logs ({$total})\n";
        if ($rows === []) {
            $body .= '—';
        } else {
            foreach ($rows as $r) {
                $body .= '• ['.(string) ($r->level ?? '').'] '.mb_substr((string) ($r->message ?? ''), 0, 70)."\n";
            }
        }
        $body .= "\n".$this->texts->getForUser('btn.admin.logs_clear', $user, 'Clear logs');
        $days = (string) app(\App\Services\SettingsStore::class)->get('logs_retention_days', '—');
        $body .= "\nRetention: {$days} days";
        $body .= "\n".$this->texts->getForUser('btn.admin.edit_logs_retention', $user, 'Edit logs retention');
        $this->state->set($user, 'admin_log_page', ['offset' => $off, 'kind' => 'logs']);
        $this->send($ctx, $chatId, $body, ['reply_markup' => $this->logsKeyboard($off, count($rows), $total, 'logs')]);
    }

    protected function sendAuditPage(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        $off = max(0, $offset);
        $rows = [];
        $total = 0;
        if (Schema::hasTable('svp_audit_log')) {
            $total = (int) DB::table('svp_audit_log')->count();
            $rows = DB::table('svp_audit_log')->orderByDesc('id')->offset($off)->limit(self::PAGE_SIZE)->get()->all();
        }
        $body = "Audit log ({$total})\n";
        if ($rows === []) {
            $body .= '—';
        } else {
            foreach ($rows as $r) {
                $body .= '• '.(string) ($r->event_type ?? '').' #'.(int) $r->id."\n";
            }
        }
        $this->state->set($user, 'admin_log_page', ['offset' => $off, 'kind' => 'audit']);
        $this->send($ctx, $chatId, $body, ['reply_markup' => $this->logsKeyboard($off, count($rows), $total, 'audit')]);
    }

    /** @return array<string, mixed> */
    protected function logsKeyboard(int $off, int $count, int $total, string $kind): array
    {
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'pnl:lg:'.max(0, $off - self::PAGE_SIZE).':'.$kind];
        }
        if ($total > $off + $count && $count >= self::PAGE_SIZE) {
            $nav[] = ['text' => '▶', 'callback_data' => 'pnl:lg:'.($off + self::PAGE_SIZE).':'.$kind];
        }
        $switch = $kind === 'audit'
            ? ['text' => 'Logs', 'callback_data' => 'pnl:lg:0:logs']
            : ['text' => 'Audit', 'callback_data' => 'pnl:lg:0:audit'];
        $rows = [];
        if ($nav !== []) {
            $rows[] = $nav;
        }
        $rows[] = [$switch];

        return ['inline_keyboard' => $rows];
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $count = Schema::hasTable('svp_audit_log')
            ? (int) DB::table('svp_audit_log')->count()
            : 0;

        return "Logs: {$count}";
    }
}
