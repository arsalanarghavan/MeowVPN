<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminBotScopeService;
use App\Modules\Core\Bot\Services\AdminKeyboardBuilder;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;

class AdminCatalogHandler extends AbstractAdminHandler
{
    public const PAGE_SIZE = 8;

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminKeyboardBuilder $keyboards,
        protected AdminPermissionService $permissions,
        protected AdminBotScopeService $scope,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
        protected AdminPlanInboundsHandler $planInbounds,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.catalog', $user, '📦 Catalog');
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, ?SvpUser $user, int $chatId, int $msgId): void
    {
        if (! $user || count($parts) < 3 || ($parts[1] ?? '') !== 'cat') {
            return;
        }
        $action = (string) ($parts[2] ?? '');
        match ($action) {
            'l' => $this->sendList($ctx, $chatId, $user, (string) ($parts[3] ?? 'plans'), (int) ($parts[4] ?? 0)),
            't' => $this->toggleItem($ctx, $chatId, $user, (string) ($parts[3] ?? ''), (int) ($parts[4] ?? 0)),
            'e' => $this->startEditWizard($ctx, $chatId, $user, (string) ($parts[3] ?? ''), (int) ($parts[4] ?? 0)),
            'd' => $this->confirmDelete($ctx, $chatId, $user, (string) ($parts[3] ?? ''), (int) ($parts[4] ?? 0)),
            'dy' => $this->deleteItem($ctx, $chatId, $user, (string) ($parts[3] ?? ''), (int) ($parts[4] ?? 0)),
            'n' => $this->startNewWizard($ctx, $chatId, $user, (string) ($parts[3] ?? '')),
            default => null,
        };
    }

    public function sendList(BotContext $ctx, int $chatId, SvpUser $user, string $kind, int $offset = 0, string $prefixMsg = ''): void
    {
        $kind = $this->normalizeKind($kind);
        $op = in_array($kind, ['cards'], true) ? 'card_manage' : 'plan_manage';
        if (! $this->permissions->mayCallOp($user, $op)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }

        $rows = $this->rowsForKind($kind, $user);
        $off = max(0, $offset);
        $total = count($rows);
        $slice = array_slice($rows, $off, self::PAGE_SIZE);
        $this->state->set($user, 'admin_panel_catalog', ['kind' => $kind, 'offset' => $off]);

        $body = $prefixMsg !== '' ? $prefixMsg."\n\n" : '';
        $body .= $this->headerForKind($kind, $user);
        $body .= "\n(".($total > 0 ? $off + 1 : 0).'–'.min($off + self::PAGE_SIZE, $total)." / {$total})\n";
        if ($slice === []) {
            $body .= "\n".$this->texts->getForUser('msg.admin.catalog.empty', $user, 'Empty');
        } else {
            foreach ($slice as $row) {
                $id = (int) ($row->id ?? 0);
                $label = $this->rowLabel($row, $kind);
                $act = ! empty($row->active) ? '✅' : '⏸';
                $body .= "\n• #{$id} {$label} {$act}";
            }
        }
        if ($kind === 'cards') {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.card_reorder', $user, 'Reorder cards');
        }

        $extra = ['reply_markup' => $this->keyboards->inlineCatalogList($kind, $slice, $off, $total, self::PAGE_SIZE)];
        $this->send($ctx, $chatId, $body, $extra);
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        if ($state === 'admin_catalog_card_reorder') {
            $trimmed = trim($text);
            $ids = array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $trimmed) ?: [])));
            if ($ids !== []) {
                $result = $this->mutate->applyForUser($user, 'card_reorder', ['order' => $ids]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }
        match ($state) {
            'admin_catalog_plan_edit' => $this->routePlanEdit($ctx, $user, $chatId, $text),
            'admin_catalog_card_edit' => $this->routeCardEdit($ctx, $user, $chatId, $text),
            'admin_catalog_category_edit' => $this->routeCategoryEdit($ctx, $user, $chatId, $text),
            default => null,
        };
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $btn = $this->texts->getForUser('btn.admin.card_reorder', $user, 'Reorder cards');
        if (trim($text) === $btn) {
            if (! $this->permissions->mayCallOp($user, 'card_manage')) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                return true;
            }
            $this->state->set($user, 'admin_catalog_card_reorder', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_card_reorder', $user, 'Send card ids in order (comma-separated)'));

            return true;
        }

        return false;
    }

    protected function toggleItem(BotContext $ctx, int $chatId, SvpUser $user, string $ent, int $id): void
    {
        if (! $this->guardEntity($user, $ent, $id)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $result = match ($ent) {
            'pl' => $this->togglePlan($user, $id),
            'pc' => $this->toggleCategory($user, $id),
            'cd' => $this->toggleCard($user, $id),
            default => ['ok' => false, 'message' => 'invalid'],
        };
        $msg = $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]);
        $kind = $this->kindFromEnt($ent);
        $off = (int) ($this->state->data($user)['offset'] ?? 0);
        $this->sendList($ctx, $chatId, $user, $kind, $off, $msg);
    }

    protected function confirmDelete(BotContext $ctx, int $chatId, SvpUser $user, string $ent, int $id): void
    {
        if ($id < 1 || ! $this->guardEntity($user, $ent, $id)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $body = $this->texts->format(
            $this->texts->getForUser('msg.admin.catalog.delete_confirm', $user, 'Delete #{id}?'),
            ['id' => (string) $id]
        );
        $this->send($ctx, $chatId, $body, [
            'reply_markup' => $this->keyboards->inlineCatalogDeleteConfirm($ent, $id),
        ]);
    }

    protected function deleteItem(BotContext $ctx, int $chatId, SvpUser $user, string $ent, int $id): void
    {
        if (! $this->guardEntity($user, $ent, $id)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $result = match ($ent) {
            'pl' => $this->deletePlan($user, $id),
            'pc' => $this->deleteCategory($user, $id),
            'cd' => $this->mutate->applyForUser($user, 'card_delete', ['id' => $id]),
            default => ['ok' => false, 'message' => 'invalid'],
        };
        $msg = $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]);
        $kind = $this->kindFromEnt($ent);
        $off = (int) ($this->state->data($user)['offset'] ?? 0);
        $this->sendList($ctx, $chatId, $user, $kind, $off, $msg);
    }

    protected function startEditWizard(BotContext $ctx, int $chatId, SvpUser $user, string $ent, int $id): void
    {
        if (! $this->guardEntity($user, $ent, $id)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        if ($ent === 'pl') {
            $row = DB::table('svp_plans')->where('id', $id)->first();
            if (! $row) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.mutate.not_found', $user, 'Not found'));

                return;
            }
            $this->state->set($user, 'admin_catalog_plan_edit', ['plan_id' => $id]);
            $hint = "Plan #{$id}\n".(string) ($row->name ?? '')."\nSend 7+ lines: name, category, days, traffic_gb, price, inbound_id, clients_count";
            $this->send($ctx, $chatId, $hint);

            return;
        }
        if ($ent === 'cd') {
            $row = DB::table('svp_cards')->where('id', $id)->first();
            if (! $row) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.mutate.not_found', $user, 'Not found'));

                return;
            }
            $this->state->set($user, 'admin_catalog_card_edit', ['card_id' => $id]);
            $hint = "Card #{$id}\nSend: number|holder|bank|method|daily_limit|priority|note|active";
            $this->send($ctx, $chatId, $hint);

            return;
        }
        if ($ent === 'pc') {
            $row = DB::table('svp_plan_categories')->where('id', $id)->first();
            if (! $row) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.mutate.not_found', $user, 'Not found'));

                return;
            }
            $this->state->set($user, 'admin_catalog_category_edit', ['pc_id' => $id]);
            $hint = "Category #{$id}\nSend 2+ lines: label, sort_order, active";
            $this->send($ctx, $chatId, $hint);
        }
    }

    protected function startNewWizard(BotContext $ctx, int $chatId, SvpUser $user, string $ent): void
    {
        if ($ent === 'pl') {
            $this->state->set($user, 'admin_catalog_plan_edit', ['plan_id' => 0]);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_catalog_plan_new', $user, 'New plan — send fields'));

            return;
        }
        if ($ent === 'cd') {
            $this->state->set($user, 'admin_catalog_card_edit', ['card_id' => 0]);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_catalog_card_new', $user, 'New card — send fields'));

            return;
        }
        if ($ent === 'pc') {
            $this->state->set($user, 'admin_catalog_category_edit', ['pc_id' => 0]);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_catalog_category_new', $user, 'New category'));
        }
    }

    protected function routePlanEdit(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $data = $this->state->data($user);
        $planId = (int) ($data['plan_id'] ?? 0);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
        if (count($lines) < 7) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.catalog.plan_lines', $user, 'Need 7 lines'));

            return;
        }
        $existing = $planId > 0 ? DB::table('svp_plans')->where('id', $planId)->first() : null;
        $draft = [
            'plan_id' => $planId,
            'name' => $lines[0],
            'category' => $lines[1],
            'duration_days' => (int) preg_replace('/\D/', '', $lines[2]),
            'traffic_gb' => (int) preg_replace('/\D/', '', $lines[3]),
            'price' => (float) str_replace(',', '.', $lines[4]),
            'clients_count' => max(1, (int) preg_replace('/\D/', '', $lines[6])),
            'active' => isset($lines[7]) ? ((int) preg_replace('/\D/', '', $lines[7]) ? 1 : 0) : (int) ($existing->active ?? 1),
            'plan_active' => isset($lines[7]) ? ((int) preg_replace('/\D/', '', $lines[7]) ? 1 : 0) : (int) ($existing->active ?? 1),
        ];
        $panelId = $existing ? max(1, (int) ($existing->panel_id ?? 1)) : 1;
        if ($existing) {
            $draft['panel_id'] = $panelId;
            $draft['plan_pricing_type'] = (string) ($existing->pricing_type ?? 'fixed');
            $draft['pricing_type'] = (string) ($existing->pricing_type ?? 'fixed');
            $draft['service_type'] = (string) ($existing->service_type ?? 'xray');
            $draft['price_per_gb'] = (float) ($existing->price_per_gb ?? 0);
            $draft['traffic_gb_min'] = (int) ($existing->traffic_gb_min ?? 0);
            $draft['traffic_gb_max'] = (int) ($existing->traffic_gb_max ?? 0);
            $draft['l2tp_server_id'] = (int) ($existing->l2tp_server_id ?? 0);
            $draft['sort_order'] = (int) ($existing->sort_order ?? 0);
        } else {
            $draft['panel_id'] = $panelId;
        }

        $selected = [];
        $lineInbound = (int) preg_replace('/\D/', '', $lines[5]);
        if ($lineInbound > 0) {
            $selected[] = $lineInbound;
        } elseif ($existing) {
            $selected = $this->planInboundIdsFromRow($existing);
        }

        $nextState = $planId > 0
            ? AdminPlanInboundsHandler::STATE_EDIT
            : AdminPlanInboundsHandler::STATE_CREATE;
        $this->planInbounds->beginPickerAfterLines($ctx, $chatId, $user, $draft, $panelId, $selected, $nextState);
    }

    /** @return array<int, int> */
    protected function planInboundIdsFromRow(object $plan): array
    {
        if (isset($plan->inbound_ids) && is_string($plan->inbound_ids) && $plan->inbound_ids !== '') {
            $decoded = json_decode($plan->inbound_ids, true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $v) {
                    $n = (int) $v;
                    if ($n > 0) {
                        $out[] = $n;
                    }
                }
                if ($out !== []) {
                    return $out;
                }
            }
        }
        $single = (int) ($plan->inbound_id ?? 0);

        return $single > 0 ? [$single] : [];
    }

    protected function routeCardEdit(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $data = $this->state->data($user);
        $cardId = (int) ($data['card_id'] ?? 0);
        $segs = array_map('trim', explode('|', $text));
        if (count($segs) < 6) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.catalog.card_lines', $user, 'Need 6 fields'));

            return;
        }
        $params = [
            'card_number' => $segs[0],
            'holder_name' => $segs[1],
            'bank_name' => $segs[2],
            'method_key' => $segs[3] ?: 'c2c',
            'daily_limit' => (float) str_replace(',', '.', $segs[4]),
            'priority' => (int) $segs[5],
            'note' => $segs[6] ?? '',
            'active' => isset($segs[7]) ? ((int) preg_replace('/\D/', '', $segs[7]) ? 1 : 0) : 1,
        ];
        if ($cardId > 0) {
            $params['id'] = $cardId;
            $result = $this->mutate->applyForUser($user, 'card_update', $params);
        } else {
            $result = $this->mutate->applyForUser($user, 'card_add', $params);
        }
        $this->state->clear($user);
        $msg = $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]);
        if (! empty($result['ok'])) {
            $this->sendList($ctx, $chatId, $user, 'cards', 0, $msg);
        } else {
            $this->send($ctx, $chatId, $msg);
        }
    }

    protected function routeCategoryEdit(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $data = $this->state->data($user);
        $pcId = (int) ($data['pc_id'] ?? 0);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
        if (count($lines) < 2) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.catalog.category_lines', $user, 'Need 2 lines'));

            return;
        }
        $params = [
            'id' => $pcId,
            'label' => $lines[0],
            'slug' => \Illuminate\Support\Str::slug($lines[0]),
            'sort_order' => (int) preg_replace('/\D/', '', $lines[1]),
            'active' => isset($lines[2]) ? ((int) preg_replace('/\D/', '', $lines[2]) ? 1 : 0) : 1,
        ];
        $result = $this->mutate->applyForUser($user, 'plan_category', $params);
        $this->state->clear($user);
        $msg = $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]);
        if (! empty($result['ok'])) {
            $this->sendList($ctx, $chatId, $user, 'plan_cats', 0, $msg);
        } else {
            $this->send($ctx, $chatId, $msg);
        }
    }

    /** @return array<string, mixed> */
    protected function toggleCard(SvpUser $user, int $id): array
    {
        $card = DB::table('svp_cards')->where('id', $id)->first();
        if (! $card) {
            return ['ok' => false, 'message' => 'not_found'];
        }

        return $this->mutate->applyForUser($user, 'card_update', [
            'id' => $id,
            'card_number' => (string) ($card->card_number ?? ''),
            'holder_name' => (string) ($card->holder_name ?? ''),
            'bank_name' => (string) ($card->bank_name ?? ''),
            'method_key' => (string) ($card->method_key ?? 'c2c'),
            'daily_limit' => (float) ($card->daily_limit ?? 0),
            'note' => (string) ($card->note ?? ''),
            'active' => empty($card->active) ? 1 : 0,
        ]);
    }

    /** @return array<string, mixed> */
    protected function togglePlan(SvpUser $user, int $id): array
    {
        $row = DB::table('svp_plans')->where('id', $id)->first();
        if (! $row) {
            return ['ok' => false, 'message' => 'not_found'];
        }

        return $this->mutate->applyForUser($user, 'plan', [
            'id' => $id,
            'active' => empty($row->active) ? 1 : 0,
        ]);
    }

    /** @return array<string, mixed> */
    protected function toggleCategory(SvpUser $user, int $id): array
    {
        $row = DB::table('svp_plan_categories')->where('id', $id)->first();
        if (! $row) {
            return ['ok' => false, 'message' => 'not_found'];
        }

        return $this->mutate->applyForUser($user, 'plan_category', [
            'id' => $id,
            'active' => empty($row->active) ? 1 : 0,
        ]);
    }

    /** @return array<string, mixed> */
    protected function deletePlan(SvpUser $user, int $id): array
    {
        $row = DB::table('svp_plans')->where('id', $id)->first();
        if (! $row) {
            return ['ok' => false, 'message' => 'not_found'];
        }

        return $this->mutate->applyForUser($user, 'plan', [
            'id' => $id,
            'active' => 0,
            'name' => (string) ($row->name ?? 'plan'),
        ]);
    }

    /** @return array<string, mixed> */
    protected function deleteCategory(SvpUser $user, int $id): array
    {
        $row = DB::table('svp_plan_categories')->where('id', $id)->first();
        if (! $row) {
            return ['ok' => false, 'message' => 'not_found'];
        }

        return $this->mutate->applyForUser($user, 'plan_category', [
            'id' => $id,
            'active' => 0,
            'label' => (string) ($row->label ?? ''),
            'slug' => (string) ($row->slug ?? ''),
        ]);
    }

    protected function guardEntity(SvpUser $admin, string $ent, int $id): bool
    {
        if ($id < 1) {
            return false;
        }
        $actor = $this->permissions->permissionActorId($admin);
        if ($actor < 1) {
            return true;
        }
        $table = match ($ent) {
            'pl' => 'svp_plans',
            'cd' => 'svp_cards',
            'pc' => 'svp_plan_categories',
            default => null,
        };
        if (! $table) {
            return false;
        }
        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) {
            return false;
        }
        $owner = (int) ($row->owner_svp_user_id ?? 0);

        return $owner === 0 || $owner === $actor;
    }

    protected function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return in_array($kind, ['plans', 'cards', 'plan_cats'], true) ? $kind : 'plans';
    }

    protected function kindFromEnt(string $ent): string
    {
        return match ($ent) {
            'cd' => 'cards',
            'pc' => 'plan_cats',
            default => 'plans',
        };
    }

    /** @return list<object> */
    protected function rowsForKind(string $kind, SvpUser $admin): array
    {
        $actor = $this->permissions->permissionActorId($admin);
        $q = match ($kind) {
            'cards' => DB::table('svp_cards')->orderBy('sort_order'),
            'plan_cats' => DB::table('svp_plan_categories')->orderBy('sort_order'),
            default => DB::table('svp_plans')->orderBy('sort_order'),
        };
        if ($actor > 0) {
            $q->where(function ($w) use ($actor) {
                $w->where('owner_svp_user_id', 0)->orWhere('owner_svp_user_id', $actor);
            });
        }

        return $q->get()->all();
    }

    protected function headerForKind(string $kind, SvpUser $user): string
    {
        return match ($kind) {
            'cards' => $this->texts->getForUser('msg.admin.tutorial.cards', $user, 'Cards'),
            'plan_cats' => $this->texts->getForUser('msg.admin.tutorial.plan_cats', $user, 'Plan categories'),
            default => $this->texts->getForUser('msg.admin.tutorial.plans', $user, 'Plans'),
        };
    }

    protected function rowLabel(object $row, string $kind): string
    {
        return match ($kind) {
            'cards' => (string) ($row->bank_name ?? $row->card_number ?? ''),
            'plan_cats' => (string) ($row->name ?? $row->label ?? ''),
            default => (string) ($row->name ?? ''),
        };
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $plans = DB::table('svp_plans')->count();
        $cards = DB::table('svp_cards')->count();

        return "Catalog\nPlans: {$plans}\nCards: {$cards}";
    }
}
