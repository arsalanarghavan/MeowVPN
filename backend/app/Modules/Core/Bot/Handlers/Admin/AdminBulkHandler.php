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

class AdminBulkHandler extends AbstractAdminHandler
{
    public const PAGE_SIZE = 5;

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminPermissionService $permissions,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.bulk', $user, '⚡ Bulk');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        if (! $this->permissions->mayCallOp($user, 'users_bulk')) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $this->sendJobList($ctx, $chatId, $user, 0);
        $body = $this->texts->getForUser('msg.admin.tutorial.users_bulk', $user, 'Bulk');
        $body .= "\n".$this->texts->getForUser('btn.admin.bulk_wallet', $user, 'Wallet bulk');
        $body .= "\n".$this->texts->getForUser('btn.admin.bulk_volume', $user, 'Volume bulk');
        $body .= "\n".$this->texts->getForUser('btn.admin.bulk_extend', $user, 'Extend bulk');
        $body .= "\n".$this->texts->getForUser('btn.admin.bulk_slots', $user, 'Slots bulk');
        $body .= "\n".$this->texts->getForUser('btn.admin.bulk_alerts', $user, 'Alerts bulk');
        $body .= "\n".$this->texts->getForUser('btn.admin.bulk_run_worker', $user, 'Run bulk worker');
        $body .= "\n".$this->texts->getForUser('btn.admin.bulk_csv_scope', $user, 'Bulk with user ids');
        $body .= "\n".$this->texts->getForUser('btn.admin.bulk_resume', $user, 'Resume bulk job');
        $this->send($ctx, $chatId, $body);
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $action = (string) ($parts[2] ?? '');
        if ($action === 'p' && isset($parts[3])) {
            $this->sendJobList($ctx, $chatId, $user, (int) $parts[3]);

            return;
        }
        if ($action === 'c' && isset($parts[3])) {
            $result = $this->mutate->applyForUser($user, 'users_bulk_job_cancel', ['job_id' => (int) $parts[3]]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'r' && isset($parts[3])) {
            $result = $this->mutate->applyForUser($user, 'users_bulk_job_resume', ['job_id' => (int) $parts[3]]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $map = [
            $this->texts->getForUser('btn.admin.bulk_wallet', $user, 'Wallet bulk') => ['admin_bulk_wallet', 'delta', 'users_bulk_wallet', 'delta'],
            $this->texts->getForUser('btn.admin.bulk_volume', $user, 'Volume bulk') => ['admin_bulk_volume', 'gb', 'users_bulk_volume', 'extra_gb'],
            $this->texts->getForUser('btn.admin.bulk_extend', $user, 'Extend bulk') => ['admin_bulk_extend', 'days', 'users_bulk_extend', 'days'],
            $this->texts->getForUser('btn.admin.bulk_slots', $user, 'Slots bulk') => ['admin_bulk_slots', 'slots', 'users_bulk_slots', 'extra_users'],
            $this->texts->getForUser('btn.admin.bulk_alerts', $user, 'Alerts bulk') => ['admin_bulk_alerts', 'json', 'users_bulk_alerts', 'alerts_json'],
        ];
        $trimmed = trim($text);
        if ($trimmed === $this->texts->getForUser('btn.admin.bulk_resume', $user, 'Resume bulk job')) {
            $this->state->set($user, 'admin_bulk_resume', []);
            $this->send($ctx, $chatId, 'Send bulk job id to resume');

            return true;
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.bulk_run_worker', $user, 'Run bulk worker')) {
            $result = $this->mutate->applyForUser($user, 'users_bulk_run_worker', []);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return true;
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.bulk_csv_scope', $user, 'Bulk with user ids')) {
            $this->state->set($user, 'admin_bulk_csv_scope', ['step' => 'op']);
            $this->send($ctx, $chatId, 'Send operation: wallet|volume|extend|slots');

            return true;
        }
        foreach ($map as $btn => $cfg) {
            if ($trimmed === $btn) {
                $this->state->set($user, $cfg[0], ['step' => $cfg[1], 'op' => $cfg[2], 'field' => $cfg[3]]);
                $prompt = $cfg[2] === 'users_bulk_alerts'
                    ? 'Send alerts JSON e.g. {"traffic":1,"expiry":1}'
                    : 'Send '.$cfg[3];
                $this->send($ctx, $chatId, $prompt);

                return true;
            }
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        if (! str_starts_with($state, 'admin_bulk_')) {
            return;
        }
        $data = $this->state->data($user);
        $trimmed = trim($text);
        $step = (string) ($data['step'] ?? '');
        $op = (string) ($data['op'] ?? '');
        $field = (string) ($data['field'] ?? '');

        if ($state === 'admin_bulk_resume' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'users_bulk_job_resume', ['job_id' => (int) $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_bulk_csv_scope') {
            if ($step === 'op' && $trimmed !== '') {
                $opMap = [
                    'wallet' => ['users_bulk_wallet', 'delta'],
                    'volume' => ['users_bulk_volume', 'extra_gb'],
                    'extend' => ['users_bulk_extend', 'days'],
                    'slots' => ['users_bulk_slots', 'extra_users'],
                ];
                $key = mb_strtolower($trimmed);
                if (! isset($opMap[$key])) {
                    return;
                }
                [$mutateOp, $fieldKey] = $opMap[$key];
                $this->state->set($user, 'admin_bulk_csv_scope', [
                    'step' => 'ids',
                    'op' => $mutateOp,
                    'field' => $fieldKey,
                ]);
                $this->send($ctx, $chatId, 'Send user ids comma-separated, then value on next line as id1,id2|value');

                return;
            }
            if ($step === 'ids' && str_contains($trimmed, '|')) {
                [$idsPart, $valPart] = array_map('trim', explode('|', $trimmed, 2));
                $ids = array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $idsPart) ?: [])));
                $params = [
                    'scope' => 'custom_ids',
                    'user_ids' => $ids,
                    (string) ($data['field'] ?? 'delta') => is_numeric(str_replace(',', '.', $valPart))
                        ? (str_contains((string) ($data['field'] ?? ''), 'delta')
                            ? (float) str_replace(',', '.', $valPart)
                            : (int) str_replace(',', '.', $valPart))
                        : 0,
                ];
                $result = $this->mutate->applyForUser($user, (string) ($data['op'] ?? ''), $params);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($step !== '' && $op !== '' && $field !== '' && ($step === 'json' ? $trimmed !== '' : is_numeric(str_replace(',', '.', $text)))) {
            if ($step === 'json' && $op === 'users_bulk_alerts') {
                $alerts = json_decode($trimmed, true);
                if (! is_array($alerts)) {
                    return;
                }
                $params = ['alerts' => $alerts, 'scope' => 'all_approved'];
            } else {
                $val = str_contains($field, 'users') || $field === 'days'
                    ? (int) str_replace(',', '.', $text)
                    : (float) str_replace(',', '.', $text);
                $params = [$field => $val, 'scope' => 'all_approved'];
            }
            $result = $this->mutate->applyForUser($user, $op, $params);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    protected function sendJobList(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        if (! Schema::hasTable('svp_users_bulk_jobs')) {
            return;
        }
        $off = max(0, $offset);
        $total = (int) DB::table('svp_users_bulk_jobs')->count();
        $rows = DB::table('svp_users_bulk_jobs')->orderByDesc('id')->offset($off)->limit(self::PAGE_SIZE)->get();
        $body = "Bulk jobs ({$total})\n";
        $ik = [];
        foreach ($rows as $r) {
            $jid = (int) $r->id;
            $body .= "#{$jid} ".(string) ($r->operation ?? '').' '.(string) ($r->status ?? '')."\n";
            $ik[] = [
                ['text' => '❌ #'.$jid, 'callback_data' => 'pnl:bulk:c:'.$jid],
                ['text' => '▶ #'.$jid, 'callback_data' => 'pnl:bulk:r:'.$jid],
            ];
        }
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'pnl:bulk:p:'.max(0, $off - self::PAGE_SIZE)];
        }
        if ($total > $off + self::PAGE_SIZE) {
            $nav[] = ['text' => '▶', 'callback_data' => 'pnl:bulk:p:'.($off + self::PAGE_SIZE)];
        }
        if ($nav !== []) {
            $ik[] = $nav;
        }
        $this->send($ctx, $chatId, $body, ['reply_markup' => ['inline_keyboard' => $ik]]);
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return $this->texts->getForUser('msg.admin.tutorial.users_bulk', $user, 'Bulk operations');
    }
}
