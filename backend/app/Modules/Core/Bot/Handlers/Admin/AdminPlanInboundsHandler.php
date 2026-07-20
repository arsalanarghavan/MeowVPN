<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\XuiPanel\Services\PanelAdminService;

/**
 * Plan wizard: multi-inbound picker (reply keyboard toggles). WP Handler_Admin_Plan_Inbounds.
 */
class AdminPlanInboundsHandler extends AbstractAdminHandler
{
    public const STATE_CREATE = 'admin_w_pl_inb';

    public const STATE_EDIT = 'admin_catalog_plan_edit_inb';

    public const BTN_PREFIX = '#i';

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected BotStateService $state,
        protected BotAdminMutateService $mutate,
        protected PanelAdminService $panels,
    ) {
        parent::__construct($runtime, $texts);
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $st = $this->state->get($user);
        if ($st !== self::STATE_CREATE && $st !== self::STATE_EDIT) {
            return false;
        }

        $trimmed = trim($text);
        $data = $this->state->data($user);

        $doneLabel = $this->texts->getForUser('btn.admin.plan_inbounds_done', $user, '✅ Done (save inbounds)');
        if ($trimmed === $doneLabel) {
            return $this->submitFromState($ctx, $chatId, $user, $data, $st);
        }

        $iid = $this->parseToggleButton($trimmed);
        if ($iid > 0) {
            $selected = isset($data['inbound_ids']) && is_array($data['inbound_ids'])
                ? array_values(array_map('intval', $data['inbound_ids']))
                : [];
            if (in_array($iid, $selected, true)) {
                $selected = array_values(array_filter($selected, static fn ($id) => (int) $id !== $iid));
            } else {
                $selected[] = $iid;
                sort($selected);
            }
            $data['inbound_ids'] = $selected;
            $this->state->set($user, $st, $data);
            $this->sendPicker(
                $ctx,
                $chatId,
                $user,
                (int) ($data['panel_id'] ?? 1),
                $selected,
                'msg.admin.catalog.plan_inbounds_pick'
            );

            return true;
        }

        $this->send(
            $ctx,
            $chatId,
            $this->texts->getForUser('msg.admin.catalog.plan_inbounds_use_buttons', $user, 'Use the inbound buttons or Done.')
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<int, int>  $selectedIds
     */
    public function beginPickerAfterLines(
        BotContext $ctx,
        int $chatId,
        SvpUser $user,
        array $draft,
        int $panelId,
        array $selectedIds,
        string $nextState,
    ): void {
        $data = array_merge($draft, [
            'panel_id' => max(1, $panelId),
            'inbound_ids' => array_values(array_filter(array_map('intval', $selectedIds))),
        ]);
        $this->state->set($user, $nextState, $data);
        $this->sendPicker(
            $ctx,
            $chatId,
            $user,
            (int) $data['panel_id'],
            (array) $data['inbound_ids'],
            'msg.admin.catalog.plan_inbounds_pick'
        );
    }

    /**
     * @param  array<int, int>  $selectedIds
     */
    public function sendPicker(
        BotContext $ctx,
        int $chatId,
        SvpUser $user,
        int $panelId,
        array $selectedIds,
        string $promptKey,
    ): void {
        $panelId = max(0, $panelId);
        $r = $this->panels->inboundsList($panelId);
        if (empty($r['ok']) || empty($r['data']['inbounds']) || ! is_array($r['data']['inbounds'])) {
            $this->send(
                $ctx,
                $chatId,
                $this->texts->format(
                    $this->texts->getForUser(
                        'msg.admin.catalog.plan_inbounds_empty',
                        $user,
                        'No inbounds on panel. {message}'
                    ),
                    ['message' => (string) ($r['message'] ?? '')]
                )
            );

            return;
        }

        $selected = array_flip(array_map('intval', $selectedIds));
        $rows = [];
        foreach (array_slice($r['data']['inbounds'], 0, 18) as $inb) {
            if (! is_array($inb)) {
                continue;
            }
            $iid = (int) ($inb['id'] ?? 0);
            if ($iid < 1) {
                continue;
            }
            $mark = isset($selected[$iid]) ? '✅' : '⬜';
            $rem = mb_substr((string) ($inb['remark'] ?? ''), 0, 14);
            $proto = (string) ($inb['protocol'] ?? '?');
            $port = (int) ($inb['port'] ?? 0);
            $lab = self::BTN_PREFIX.$iid.' '.$mark.' '.$rem.' · '.$proto.':'.$port;
            if (mb_strlen($lab) > 64) {
                $lab = mb_substr($lab, 0, 64);
            }
            $rows[] = [['text' => $lab]];
        }
        $done = $this->texts->getForUser('btn.admin.plan_inbounds_done', $user, '✅ Done (save inbounds)');
        $rows[] = [['text' => $done]];
        $count = count($selectedIds);

        $this->send(
            $ctx,
            $chatId,
            $this->texts->format(
                $this->texts->getForUser(
                    $promptKey,
                    $user,
                    '📍 Pick inbounds (✅/⬜). Selected: {count}. Then tap Done.'
                ),
                ['count' => (string) $count]
            ),
            [
                'reply_markup' => [
                    'keyboard' => $rows,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false,
                ],
            ]
        );
    }

    protected function parseToggleButton(string $text): int
    {
        if (preg_match('/^'.preg_quote(self::BTN_PREFIX, '/').'(\d+)\s/u', $text, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function submitFromState(BotContext $ctx, int $chatId, SvpUser $user, array $data, string $st): bool
    {
        $inboundIds = isset($data['inbound_ids']) && is_array($data['inbound_ids'])
            ? array_values(array_filter(array_map('intval', $data['inbound_ids'])))
            : [];
        if ($inboundIds === []) {
            $this->send(
                $ctx,
                $chatId,
                $this->texts->getForUser('msg.admin.catalog.plan_inbounds_required', $user, '⛔ Select at least one inbound.')
            );

            return true;
        }

        $primary = (int) $inboundIds[0];
        if ($st === self::STATE_CREATE) {
            $post = [
                'plan_action' => 'add',
                'name' => (string) ($data['name'] ?? ''),
                'category' => (string) ($data['category'] ?? ''),
                'duration_days' => (int) ($data['duration_days'] ?? 0),
                'traffic_gb' => (int) ($data['traffic_gb'] ?? 0),
                'price' => (float) ($data['price'] ?? 0),
                'inbound_ids' => $inboundIds,
                'inbound_id' => $primary,
                'clients_count' => max(1, (int) ($data['clients_count'] ?? 1)),
                'plan_pricing_type' => 'fixed',
                'pricing_type' => 'fixed',
                'service_type' => 'xray',
                'plan_panel_id' => max(1, (int) ($data['panel_id'] ?? 1)),
                'sort_order' => 0,
                'price_per_gb' => 0,
                'traffic_gb_min' => 0,
                'traffic_gb_max' => 0,
                'l2tp_server_id' => 0,
                'plan_active' => 1,
            ];
            $res = $this->mutate->applyForUser($user, 'plan', $post);
            $this->state->clear($user);
            if (! empty($res['ok'])) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.catalog.plan_added', $user, 'Plan added.'));
            } else {
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($res) ? $res : ['ok' => false]));
            }

            return true;
        }

        $planId = (int) ($data['plan_id'] ?? 0);
        if ($planId < 1) {
            $this->state->clear($user);

            return true;
        }
        $post = [
            'plan_action' => 'update',
            'plan_id' => $planId,
            'name' => (string) ($data['name'] ?? ''),
            'category' => (string) ($data['category'] ?? ''),
            'duration_days' => (int) ($data['duration_days'] ?? 0),
            'traffic_gb' => (int) ($data['traffic_gb'] ?? 0),
            'price' => (float) ($data['price'] ?? 0),
            'inbound_ids' => $inboundIds,
            'inbound_id' => $primary,
            'clients_count' => max(1, (int) ($data['clients_count'] ?? 1)),
            'plan_pricing_type' => (string) ($data['plan_pricing_type'] ?? 'fixed'),
            'pricing_type' => (string) ($data['pricing_type'] ?? 'fixed'),
            'service_type' => (string) ($data['service_type'] ?? 'xray'),
            'plan_panel_id' => max(1, (int) ($data['panel_id'] ?? 1)),
            'price_per_gb' => (float) ($data['price_per_gb'] ?? 0),
            'traffic_gb_min' => (int) ($data['traffic_gb_min'] ?? 0),
            'traffic_gb_max' => (int) ($data['traffic_gb_max'] ?? 0),
            'l2tp_server_id' => (int) ($data['l2tp_server_id'] ?? 0),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'plan_active' => (int) ($data['plan_active'] ?? 1),
        ];
        $result = $this->mutate->applyForUser($user, 'plan', $post);
        $this->state->clear($user);
        $msg = $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]);
        if (! empty($result['ok'])) {
            app(AdminCatalogHandler::class)->sendList($ctx, $chatId, $user, 'plans', 0, $msg);
        } else {
            $this->send($ctx, $chatId, $msg);
        }

        return true;
    }
}
