<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\AdminKeyboardBuilder;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminFinanceHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminPermissionService $permissions,
        protected AdminReceiptsHandler $receipts,
        protected AdminCatalogHandler $catalog,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
        protected AdminKeyboardBuilder $keyboards,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.finance', $user, '💰 Finance');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        match ($tabKey) {
            'receipts' => $this->receipts->sendPendingReviewPaged($ctx, $user, $chatId, 0),
            'plans', 'cards', 'plan_cats' => $this->catalog->sendList($ctx, $chatId, $user, $tabKey, 0),
            'referral_reports' => $this->sendReferralReports($ctx, $chatId, $user),
            'reseller_charge' => $this->sendResellerCharge($ctx, $chatId, $user),
            'customer_charges' => $this->sendCustomerCharges($ctx, $chatId, $user, 0, 'all'),
            'unit_economics' => app(AdminEconomicsHandler::class)->openTab($ctx, $chatId, $user, 'unit_economics'),
            default => $this->sendFinanceIntro($ctx, $chatId, $user),
        };
    }

    protected function sendFinanceIntro(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $this->send($ctx, $chatId, $this->sectionIntro($user), [
            'reply_markup' => $this->keyboards->inlineFinanceTabs($user),
        ]);
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $action = (string) ($parts[2] ?? '');
        if ($action === 'cc') {
            $offset = (int) ($parts[3] ?? 0);
            $filter = (string) ($parts[4] ?? 'all');
            $this->sendCustomerCharges($ctx, $chatId, $user, $offset, $filter);

            return;
        }
        if ($action === 'tab' && isset($parts[3])) {
            $this->openTab($ctx, $chatId, $user, (string) $parts[3]);
        }
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $chargeBtn = $this->texts->getForUser('btn.admin.reseller_charge_start', $user, 'Top up wallet');
        $chargesBtn = $this->texts->getForUser('btn.admin.customer_charges', $user, 'Customer charges');
        $filterPurchase = $this->texts->getForUser('btn.admin.charges_filter_purchase', $user, 'Filter purchase');
        $filterTopup = $this->texts->getForUser('btn.admin.charges_filter_topup', $user, 'Filter topup');
        $filterAll = $this->texts->getForUser('btn.admin.charges_filter_all', $user, 'All charges');
        $trimmed = trim($text);

        if ($trimmed === $chargeBtn) {
            $this->state->set($user, 'admin_finance_reseller_charge', ['step' => 'amount']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_reseller_charge', $user, 'Send amount'));

            return true;
        }
        if ($trimmed === $chargesBtn) {
            $this->sendCustomerCharges($ctx, $chatId, $user, 0, 'all');

            return true;
        }
        if ($trimmed === $filterPurchase) {
            $data = $this->state->data($user);
            $this->sendCustomerCharges($ctx, $chatId, $user, (int) ($data['offset'] ?? 0), 'purchase');

            return true;
        }
        if ($trimmed === $filterTopup) {
            $data = $this->state->data($user);
            $this->sendCustomerCharges($ctx, $chatId, $user, (int) ($data['offset'] ?? 0), 'wallet_topup');

            return true;
        }
        if ($trimmed === $filterAll) {
            $this->sendCustomerCharges($ctx, $chatId, $user, 0, 'all');

            return true;
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $data = $this->state->data($user);
        $trimmed = trim($text);

        if ($state === 'admin_finance_reseller_charge') {
            $step = (string) ($data['step'] ?? '');
            if ($step === 'amount' && is_numeric(str_replace(',', '.', $trimmed))) {
                $amount = (float) str_replace(',', '.', $trimmed);
                $result = $this->mutate->applyForUser($user, 'reseller_wallet_topup_checkout', ['amount' => $amount]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_reseller_charges_list' && is_numeric($trimmed)) {
            $filter = (string) ($data['filter'] ?? 'all');
            $this->sendCustomerCharges($ctx, $chatId, $user, max(0, (int) $trimmed), $filter);
        }
    }

    protected function sendReferralReports(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        if (! $this->permissions->mayCallOp($user, 'referral_manage')) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $count = (int) DB::table('svp_transactions')->where('type', 'referral')->count();
        $sum = (float) DB::table('svp_transactions')->where('type', 'referral')->where('status', 'completed')->sum('amount');
        $body = $this->texts->getForUser('msg.admin.tutorial.referral_reports', $user, 'Referral reports');
        $body .= "\n\nTx: {$count}\nSum: ".number_format($sum, 0);
        $this->send($ctx, $chatId, $body);
    }

    protected function sendResellerCharge(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $actorId = $this->permissions->permissionActorId($user);
        if ($actorId < 1) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_tab', $user));

            return;
        }
        if (! $this->permissions->hasPermission($user, 'plans.manage')) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $balance = (float) (DB::table('svp_users')->where('id', $actorId)->value('balance') ?? 0);
        $body = $this->texts->getForUser('msg.admin.tutorial.reseller_charge', $user, 'Reseller wallet');
        $body .= "\n\nBalance: ".number_format($balance, 0);
        $body .= "\n\n".$this->texts->getForUser('btn.admin.reseller_charge_start', $user, 'Top up wallet');
        $this->send($ctx, $chatId, $body);
    }

    protected function sendCustomerCharges(BotContext $ctx, int $chatId, SvpUser $user, int $offset, string $filter = 'all'): void
    {
        $actorId = $this->permissions->permissionActorId($user);
        $off = max(0, $offset);
        $q = DB::table('svp_transactions')->orderByDesc('id');
        if ($actorId > 0) {
            $q->where('billing_reseller_svp_id', $actorId);
        }
        if ($filter === 'purchase') {
            $q->where('type', 'purchase');
        } elseif ($filter === 'wallet_topup') {
            $q->where('type', 'wallet_topup');
        }
        $total = (int) $q->count();
        $rows = (clone $q)->offset($off)->limit(10)->get();
        $body = "Customer charges ({$total})";
        if ($filter !== 'all') {
            $body .= " [{$filter}]";
        }
        $body .= "\n";
        foreach ($rows as $r) {
            $body .= '#'.(int) $r->id.' '.(string) $r->type.' '.number_format((float) $r->amount).' ('.(string) $r->status.")\n";
        }
        if ($rows->isEmpty()) {
            $body .= "—\n";
        }
        $body .= "\n".$this->texts->getForUser('btn.admin.charges_filter_purchase', $user, 'Filter purchase');
        $body .= "\n".$this->texts->getForUser('btn.admin.charges_filter_topup', $user, 'Filter topup');
        $body .= "\n".$this->texts->getForUser('btn.admin.charges_filter_all', $user, 'All charges');
        $this->state->set($user, 'admin_reseller_charges_list', ['offset' => $off, 'filter' => $filter]);
        $this->send($ctx, $chatId, $body, [
            'reply_markup' => $this->keyboards->inlineFinanceTabs($user),
        ]);
    }

    protected function sendUnitEconomics(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $panels = (int) DB::table('svp_panels')->count();
        $services = (int) DB::table('svp_services')->whereNull('deleted_at')->count();
        $servers = Schema::hasTable('svp_unit_economics_servers')
            ? (int) DB::table('svp_unit_economics_servers')->count()
            : 0;
        $this->send($ctx, $chatId, "Unit economics\nPanels: {$panels}\nServices: {$services}\nServers: {$servers}");
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return $this->texts->getForUser('msg.admin.section.finance.intro', $user, 'Finance');
    }
}
