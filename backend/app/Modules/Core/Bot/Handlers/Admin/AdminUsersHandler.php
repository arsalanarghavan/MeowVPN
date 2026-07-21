<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminBotScopeService;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Modules\Core\Bot\Services\AdminKeyboardBuilder;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotManagedUserContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Services\Portal\PortalLinkService;
use Illuminate\Support\Facades\DB;

class AdminUsersHandler extends AbstractAdminHandler
{
    public const QUEUE_PAGE_SIZE = 5;

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminBotScopeService $scope,
        protected AdminGuard $adminGuard,
        protected AdminPermissionService $permissions,
        protected BotStateService $state,
        protected BotAdminMutateService $mutate,
        protected PortalLinkService $portal,
        protected AdminKeyboardBuilder $keyboards,
        protected BotManagedUserContext $managedContext,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.users', $user, '👥 Users');
    }

    public function openUsersSubmenu(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $this->scope->setActingAdmin((int) $user->id);
        $this->state->set($user, 'admin_panel_users_menu', ['section' => 'users']);
        $pending = $this->countByStatus($user, 'pending');
        $approved = $this->countByStatus($user, 'approved');
        $rejected = $this->countByStatus($user, 'rejected');
        $body = $this->texts->getForUser('msg.admin.tutorial.users', $user, 'Users');
        $body .= "\n\nPending: {$pending}\nApproved: {$approved}\nRejected: {$rejected}";
        $body .= "\n\n".$this->texts->getForUser('msg.admin.users_search_hint', $user, 'Send #123 to open user card');
        $this->send($ctx, $chatId, $body, [
            'reply_markup' => $this->keyboards->usersSubmenuReply($user),
        ]);
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $admin, int $chatId, int $msgId): void
    {
        $sub = (string) ($parts[1] ?? '');
        if ($sub === 'umsg' && isset($parts[2])) {
            $uid = (int) $parts[2];
            if ($this->scope->mayModerateUser($admin, $uid)) {
                $this->state->set($admin, 'admin_users_moderation', ['action' => 'dm', 'target_uid' => $uid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_dm_user', $admin, 'Send message text'));
            }

            return;
        }
        if (in_array($sub, ['wbp', 'wbm'], true) && isset($parts[2])) {
            $uid = (int) $parts[2];
            if ($this->scope->mayModerateUser($admin, $uid)) {
                $this->state->set($admin, 'admin_users_moderation', [
                    'action' => $sub === 'wbp' ? 'credit' : 'debit',
                    'target_uid' => $uid,
                ]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_balance_delta', $admin, 'Send amount'));
            }

            return;
        }
        if ($sub === 'ui' && isset($parts[2])) {
            $this->sendUserAdminCard($ctx, $chatId, $admin, (int) $parts[2]);

            return;
        }
        if ($sub === 'urcp' && isset($parts[2], $parts[3])) {
            $this->handleUserReceiptCallback($ctx, $admin, $chatId, (string) $parts[2], (int) $parts[3]);

            return;
        }
        if ($sub === 'cs' && isset($parts[2])) {
            $uid = (int) $parts[2];
            if ($this->scope->mayModerateUser($admin, $uid)) {
                $this->state->set($admin, 'admin_users_create_service', ['target_uid' => $uid, 'step' => 'plan']);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_create_service_plan', $admin, 'Send plan_id'));
            }

            return;
        }
        if ($sub === 'mem' && isset($parts[2], $parts[3])) {
            $uid = (int) $parts[2];
            $action = (string) $parts[3];
            if (! $this->scope->mayModerateUser($admin, $uid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

                return;
            }
            if ($action === 'fj') {
                $result = $this->mutate->applyForUser($admin, 'membership', ['user_id' => $uid]);
            } else {
                $status = match ($action) {
                    'a' => 'approved',
                    'r' => 'rejected',
                    'p' => 'pending',
                    default => '',
                };
                if ($status === '') {
                    return;
                }
                $result = $this->mutate->applyForUser($admin, 'user_status', [
                    'user_id' => $uid,
                    'status' => $status,
                ]);
            }
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($sub === 'asv' && isset($parts[2])) {
            $this->sendServiceAdminMenu($ctx, $chatId, $admin, (int) $parts[2]);

            return;
        }
        if ($sub === 'svc_del' && isset($parts[2])) {
            $sid = (int) $parts[2];
            if ($this->mayAccessService($admin, $sid)) {
                $result = $this->mutate->applyForUser($admin, 'service_delete', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }
        if (in_array($sub, ['ar', 'av', 'rv', 'ad', 'sd'], true) && isset($parts[2])) {
            $sid = (int) $parts[2];
            if (! $this->mayAccessService($admin, $sid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

                return;
            }
            if ($sub === 'ar') {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_renew_mode', $admin, 'Choose payment mode:'), [
                    'reply_markup' => $this->keyboards->adminServicePaymentModeKeyboard('renew', $sid),
                ]);

                return;
            }
            $state = match ($sub) {
                'av' => 'admin_users_service_add_volume',
                'rv' => 'admin_users_service_reduce_volume',
                'ad' => 'admin_users_service_add_days',
                'sd' => 'admin_users_service_reduce_days',
                default => '',
            };
            if ($state === '') {
                return;
            }
            $prompt = match ($sub) {
                'av', 'rv' => $this->texts->getForUser('msg.admin.prompt_volume_gb', $admin, 'Send GB amount'),
                'ad', 'sd' => $this->texts->getForUser('msg.admin.prompt_days', $admin, 'Send days'),
                default => '',
            };
            $this->state->set($admin, $state, ['service_id' => $sid, 'step' => 'value']);
            $this->send($ctx, $chatId, $prompt);

            return;
        }
        if ($sub === 'rr' && isset($parts[2])) {
            $uid = (int) $parts[2];
            if (! $this->scope->mayModerateUser($admin, $uid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

                return;
            }
            $result = $this->mutate->applyForUser($admin, 'membership', [
                'user_id' => $uid,
                'svp_user_membership_action' => 'reopen',
            ]);
            $ok = is_array($result) && ! empty($result['ok']);
            $this->send($ctx, $chatId, $ok
                ? $this->texts->format($this->texts->getForUser('msg.admin.user_requeued', $admin, '✅ User #{id} requeued.'), ['id' => $uid])
                : $this->texts->format($this->texts->getForUser('msg.admin.requeue_failed', $admin, 'Requeue failed: {reason}'), [
                    'reason' => (string) ($result['message'] ?? $result['reason'] ?? '—'),
                ]));

            return;
        }
        if (in_array($sub, ['nsx', 'nsm', 'nrr', 'nva', 'nus'], true)) {
            $this->handlePaymentModeCallback($ctx, $admin, $chatId, $parts);

            return;
        }
        if ($sub === 'svc' && isset($parts[2], $parts[3])) {
            $action = (string) $parts[2];
            $sid = (int) $parts[3];
            if (! $this->mayAccessService($admin, $sid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

                return;
            }
            if (in_array($action, ['sync', 'rk', 'rid', 'rf'], true)) {
                $op = match ($action) {
                    'sync' => 'service_panel_sync',
                    'rk' => 'service_regen_key',
                    'rid' => 'service_regen_sub_id',
                    'rf' => 'service_panel_refresh',
                    default => '',
                };
                if ($op !== '') {
                    $result = $this->mutate->applyForUser($admin, $op, ['service_id' => $sid]);
                    $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));
                }

                return;
            }
            if ($action === 'tg') {
                $enable = ! isset($parts[4]) || (string) $parts[4] !== '0';
                $result = $this->mutate->applyForUser($admin, 'user_service_toggle_enable', [
                    'service_id' => $sid,
                    'enable' => $enable ? 1 : 0,
                ]);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

                return;
            }
            if ($action === 'sn') {
                $this->state->set($admin, 'admin_users_service_note', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_service_note', $admin, 'Send service note'));

                return;
            }
            if ($action === 'sl') {
                $this->state->set($admin, 'admin_users_service_slots', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_service_slots', $admin, 'Send slot count'));

                return;
            }
            if ($action === 'rsl') {
                $this->state->set($admin, 'admin_users_service_reduce_slots', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_service_reduce_slots', $admin, 'Send slots to remove'));

                return;
            }
            if ($action === 'xf') {
                $this->state->set($admin, 'admin_users_service_transfer', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_service_transfer', $admin, 'Send target user id'));

                return;
            }
            if ($action === 'li') {
                $this->state->set($admin, 'admin_users_service_limit_ip', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_service_limit_ip', $admin, 'Send concurrent IP limit'));

                return;
            }
            if ($action === 'pt') {
                $this->state->set($admin, 'admin_users_service_panel_transfer', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_service_panel_transfer', $admin, 'Send target panel_id'));

                return;
            }
            if ($action === 'al') {
                $this->state->set($admin, 'admin_users_service_alerts', ['service_id' => $sid, 'step' => 'json']);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_service_alerts', $admin, 'Send alerts JSON e.g. {"traffic":1,"expiry":1}'));

                return;
            }
            if ($action === 'pdc' && ($parts[4] ?? '') === 'y') {
                $result = $this->mutate->applyForUser($admin, 'service_panel_delete_client', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

                return;
            }
            if ($action === 'pdc') {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.confirm_panel_delete_client', $admin, 'Delete panel client?'), [
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => '✅', 'callback_data' => 'pnl:svc:pdc:y:'.$sid],
                            ['text' => '❌', 'callback_data' => 'noop'],
                        ]],
                    ],
                ]);

                return;
            }
            if ($action === 'can' && ($parts[4] ?? '') === 'y') {
                $result = $this->mutate->applyForUser($admin, 'service_apply_canonical_panel_identity', ['service_id' => $sid]);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

                return;
            }
            if ($action === 'can') {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.confirm_canonical_identity', $admin, 'Apply canonical panel identity?'), [
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => '✅', 'callback_data' => 'pnl:svc:can:y:'.$sid],
                            ['text' => '❌', 'callback_data' => 'noop'],
                        ]],
                    ],
                ]);

                return;
            }
        }
        if (in_array($sub, ['pq', 'aq', 'rq'], true)) {
            $off = (int) ($parts[2] ?? 0);
            match ($sub) {
                'aq' => $this->sendApprovedPage($ctx, $chatId, $admin, $off),
                'rq' => $this->sendRejectedPage($ctx, $chatId, $admin, $off),
                default => $this->sendPendingPage($ctx, $chatId, $admin, $off),
            };
        }
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $trimmed = trim($text);
        $map = [
            $this->texts->getForUser('btn.admin.user_manual_create', $user, 'Create user') => 'admin_users_manual_create',
            $this->texts->getForUser('btn.admin.user_merge', $user, 'Merge users') => 'admin_users_merge',
            $this->texts->getForUser('btn.admin.user_set_role', $user, 'Set role') => 'admin_users_set_role',
            $this->texts->getForUser('btn.admin.user_set_referrer', $user, 'Set referrer') => 'admin_users_set_referrer',
        ];
        foreach ($map as $btn => $state) {
            if ($trimmed === $btn) {
                if ($state === 'admin_users_merge' && $this->permissions->permissionActorId($user) > 0) {
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                    return true;
                }
                $this->state->set($user, $state, ['step' => 'first']);
                $prompt = match ($state) {
                    'admin_users_manual_create' => $this->texts->getForUser('msg.admin.prompt_manual_username', $user, 'Send username|first_name'),
                    'admin_users_merge' => $this->texts->getForUser('msg.admin.prompt_merge_source', $user, 'Send source user id (drop)'),
                    'admin_users_set_role' => $this->texts->getForUser('msg.admin.prompt_user_id', $user, 'Send user id'),
                    'admin_users_set_referrer' => $this->texts->getForUser('msg.admin.prompt_user_id', $user, 'Send user id'),
                    default => '',
                };
                $this->send($ctx, $chatId, $prompt);

                return true;
            }
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $data = $this->state->data($user);
        $trimmed = trim($text);

        if ($state === 'admin_users_manual_create') {
            $step = (string) ($data['step'] ?? 'first');
            if ($step === 'first' && str_contains($trimmed, '|')) {
                $parts = array_map('trim', explode('|', $trimmed, 2));
                $this->state->set($user, 'admin_users_manual_create', [
                    'step' => 'balance',
                    'username' => (string) ($parts[0] ?? ''),
                    'first_name' => (string) ($parts[1] ?? ''),
                ]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_balance_delta', $user, 'Send initial balance (0 ok)'));

                return;
            }
            if ($step === 'balance' && is_numeric(str_replace(',', '.', $trimmed))) {
                $result = $this->mutate->applyForUser($user, 'user_manual_create', [
                    'username' => (string) ($data['username'] ?? ''),
                    'first_name' => (string) ($data['first_name'] ?? ''),
                    'balance' => (float) str_replace(',', '.', $trimmed),
                    'status' => 'approved',
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_users_set_role') {
            $step = (string) ($data['step'] ?? 'first');
            if ($step === 'first' && is_numeric($trimmed)) {
                $uid = (int) $trimmed;
                if (! $this->scope->mayModerateUser($user, $uid)) {
                    $this->state->clear($user);
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_not_found', $user));

                    return;
                }
                $this->state->set($user, 'admin_users_set_role', ['step' => 'role', 'user_id' => $uid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_user_role', $user, 'Send role: user|reseller|admin'));

                return;
            }
            if ($step === 'role' && $trimmed !== '') {
                $result = $this->mutate->applyForUser($user, 'user_set_role', [
                    'user_id' => (int) ($data['user_id'] ?? 0),
                    'role' => $trimmed,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_users_set_referrer') {
            $step = (string) ($data['step'] ?? 'first');
            if ($step === 'first' && is_numeric($trimmed)) {
                $uid = (int) $trimmed;
                if (! $this->scope->mayModerateUser($user, $uid)) {
                    $this->state->clear($user);
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_not_found', $user));

                    return;
                }
                $this->state->set($user, 'admin_users_set_referrer', ['step' => 'referrer', 'user_id' => $uid]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_referrer_id', $user, 'Send referrer user id (0 to clear)'));

                return;
            }
            if ($step === 'referrer' && is_numeric($trimmed)) {
                $result = $this->mutate->applyForUser($user, 'user_set_referrer', [
                    'user_id' => (int) ($data['user_id'] ?? 0),
                    'invited_by' => (int) $trimmed,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_users_merge') {
            $step = (string) ($data['step'] ?? 'first');
            if ($step === 'first' && is_numeric($trimmed)) {
                $this->state->set($user, 'admin_users_merge', [
                    'step' => 'target',
                    'source_id' => (int) $trimmed,
                ]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_merge_target', $user, 'Send target user id (keep)'));

                return;
            }
            if ($step === 'target' && is_numeric($trimmed)) {
                $preview = $this->mutate->applyForUser($user, 'user_merge_preview', [
                    'source_id' => (int) ($data['source_id'] ?? 0),
                    'target_id' => (int) $trimmed,
                ]);
                $summary = is_array($preview['data'] ?? null) ? json_encode($preview['data'], JSON_UNESCAPED_UNICODE) : '';
                $this->state->set($user, 'admin_users_merge', [
                    'step' => 'confirm',
                    'source_id' => (int) ($data['source_id'] ?? 0),
                    'target_id' => (int) $trimmed,
                ]);
                $body = $this->texts->getForUser('msg.admin.prompt_merge_confirm', $user, 'Reply yes to merge');
                if ($summary !== '') {
                    $body .= "\n\n".mb_substr($summary, 0, 1500);
                }
                $this->send($ctx, $chatId, $body);

                return;
            }
            if ($step === 'confirm' && in_array(mb_strtolower($trimmed), ['yes', 'y', 'بله', 'تایید'], true)) {
                $result = $this->mutate->applyForUser($user, 'user_merge', [
                    'source_id' => (int) ($data['source_id'] ?? 0),
                    'target_id' => (int) ($data['target_id'] ?? 0),
                    'confirm' => true,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_users_service_note' && $trimmed !== '') {
            $result = $this->mutate->applyForUser($user, 'service_set_note', [
                'service_id' => (int) ($data['service_id'] ?? 0),
                'note' => $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_users_service_slots' && is_numeric($trimmed)) {
            $sid = (int) ($data['service_id'] ?? 0);
            $slots = max(1, (int) $trimmed);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_slots_mode', $user, 'Choose payment mode:'), [
                'reply_markup' => $this->keyboards->adminServicePaymentModeKeyboard('slots', $sid, $slots),
            ]);

            return;
        }

        if ($state === 'admin_users_service_reduce_slots' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'user_service_reduce_slots', [
                'service_id' => (int) ($data['service_id'] ?? 0),
                'slots' => (int) $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_users_service_transfer' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'user_service_transfer', [
                'service_id' => (int) ($data['service_id'] ?? 0),
                'target' => (string) $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_users_service_limit_ip' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'service_set_limit_ip', [
                'service_id' => (int) ($data['service_id'] ?? 0),
                'limit_ip' => (int) $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_users_service_panel_transfer' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'service_panel_transfer', [
                'service_id' => (int) ($data['service_id'] ?? 0),
                'target_panel_id' => (int) $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_users_service_alerts' && $trimmed !== '') {
            $alerts = json_decode($trimmed, true);
            if (! is_array($alerts)) {
                return;
            }
            $result = $this->mutate->applyForUser($user, 'service_alerts_patch', [
                'service_id' => (int) ($data['service_id'] ?? 0),
                'alerts' => $alerts,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_users_receipt_update') {
            $step = (string) ($data['step'] ?? 'amount');
            if ($step === 'amount' && is_numeric(str_replace(',', '.', $trimmed))) {
                $result = $this->mutate->applyForUser($user, 'receipt_update', [
                    'id' => (int) ($data['receipt_id'] ?? 0),
                    'amount' => (float) str_replace(',', '.', $trimmed),
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            } elseif ($step === 'status' && $trimmed !== '') {
                $result = $this->mutate->applyForUser($user, 'receipt_set_status', [
                    'receipt_id' => (int) ($data['receipt_id'] ?? 0),
                    'status' => $trimmed,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if (in_array($state, [
            'admin_users_service_renew',
            'admin_users_service_add_volume',
            'admin_users_service_reduce_volume',
            'admin_users_service_add_days',
            'admin_users_service_reduce_days',
        ], true)) {
            $sid = (int) ($data['service_id'] ?? 0);
            $step = (string) ($data['step'] ?? '');
            if ($state === 'admin_users_service_renew' && $step === 'mode' && $trimmed !== '') {
                $mode = $this->paymentModeFromLetter($trimmed) ?: strtolower($trimmed);
                if (! in_array($mode, ['wallet', 'free', 'invoice'], true)) {
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.method_invalid', $user, 'Invalid method'));

                    return;
                }
                $result = $this->mutate->applyForUser($user, 'user_renew_service', [
                    'service_id' => $sid,
                    'mode' => $mode,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

                return;
            }
            if ($step !== 'value' || ! is_numeric(str_replace(',', '.', $trimmed))) {
                return;
            }
            $val = (int) str_replace(',', '.', $trimmed);
            if ($state === 'admin_users_service_add_volume') {
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_volume_mode', $user, 'Choose payment mode:'), [
                    'reply_markup' => $this->keyboards->adminServicePaymentModeKeyboard('vol', $sid, $val),
                ]);

                return;
            }
            $op = match ($state) {
                    'admin_users_service_reduce_volume' => 'user_reduce_volume',
                    'admin_users_service_add_days' => 'user_add_days',
                    'admin_users_service_reduce_days' => 'user_reduce_days',
                    default => '',
                };
            if ($op === '') {
                return;
            }
            $params = ['service_id' => $sid];
            if (str_contains($op, 'volume')) {
                $params['reduce_gb'] = $val;
            } else {
                $params['days'] = $val;
            }
            $result = $this->mutate->applyForUser($user, $op, $params);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    public function handleRegistration(BotContext $ctx, string $action, int $uid, array $from, int $chatId, string $cbId): void
    {
        $fromId = (int) ($from['id'] ?? 0);
        if (! $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
            return;
        }

        $admin = $this->adminGuard->resolveAdminByPlatformId($ctx->platform, $fromId);
        if (! $admin) {
            return;
        }

        $target = SvpUser::query()->find($uid);
        if (! $target) {
            return;
        }

        if (! $this->scope->mayModerateUser($admin, $uid)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

            return;
        }
        $status = $action === 'a' ? 'approved' : 'rejected';
        $result = $this->mutate->applyForUser($admin, 'user_status', [
            'user_id' => $uid,
            'status' => $status,
        ]);
        $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));
    }

    public function sendUserAdminCard(BotContext $ctx, int $chatId, SvpUser $admin, int $uid): void
    {
        if (! $this->scope->mayModerateUser($admin, $uid)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_not_found', $admin, 'User not found'));

            return;
        }
        $u = SvpUser::query()->find($uid);
        if (! $u) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_not_found', $admin, 'User not found'));

            return;
        }
        $this->managedContext->set($admin, $uid);
        $services = DB::table('svp_services')->where('user_id', $uid)->whereNull('deleted_at')->orderByDesc('id')->limit(8)->get();
        $svcCount = (int) DB::table('svp_services')->where('user_id', $uid)->whereNull('deleted_at')->count();
        $uname = (string) ($u->username ?? $u->tg_username ?? '#'.$uid);
        $body = "👤 {$uname}\n";
        $body .= 'Status: '.(string) $u->status."\n";
        $body .= 'Role: '.(string) $u->role."\n";
        $body .= 'Balance: '.number_format((float) $u->balance)."\n";
        $body .= "Services: {$svcCount}";
        $link = $this->portal->buildPortalLink($uid);
        $body .= "\n\n🌐 ".url('/portal').'?'.http_build_query($link);

        $ik = [
            [['text' => '✉️ DM', 'callback_data' => 'pnl:umsg:'.$uid]],
            [
                ['text' => '💰 +', 'callback_data' => 'pnl:wbp:'.$uid],
                ['text' => '📉 -', 'callback_data' => 'pnl:wbm:'.$uid],
            ],
            [
                ['text' => '➕ Service', 'callback_data' => 'pnl:cs:'.$uid],
                ['text' => '⛔ Block', 'callback_data' => 'pnl:usr:blk:'.$uid],
            ],
            [['text' => '✅ Unblock', 'callback_data' => 'pnl:usr:unb:'.$uid]],
        ];
        if ((string) $u->status === 'pending') {
            $ik[] = [
                ['text' => '✅ Approve', 'callback_data' => 'pnl:mem:'.$uid.':a'],
                ['text' => '❌ Reject', 'callback_data' => 'pnl:mem:'.$uid.':r'],
            ];
        }
        if ((string) $u->status === 'rejected') {
            $ik[] = [['text' => '↩ Reopen', 'callback_data' => 'pnl:mem:'.$uid.':p']];
        }
        $ik[] = [['text' => '📣 Force join', 'callback_data' => 'pnl:mem:'.$uid.':fj']];

        $this->send($ctx, $chatId, $body, [
            'reply_markup' => ['inline_keyboard' => $ik],
        ]);

        $pendingReceipts = DB::table('svp_receipts')
            ->where('user_id', $uid)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
        if ($pendingReceipts->isNotEmpty()) {
            foreach ($pendingReceipts as $rec) {
                $rid = (int) $rec->id;
                $rcpBody = '🧾 #'.$rid.' '.number_format((float) $rec->amount, 0);
                $rcpIk = $this->keyboards->inlineReceipt($rid);
                $rcpIk['inline_keyboard'][] = [
                    ['text' => '✏️ Amount', 'callback_data' => 'pnl:urcp:amt:'.$rid],
                    ['text' => '📋 Status', 'callback_data' => 'pnl:urcp:st:'.$rid],
                ];
                $this->send($ctx, $chatId, $rcpBody, ['reply_markup' => $rcpIk]);
            }
        }

        if ($services->isNotEmpty()) {
            $svcIk = [];
            foreach ($services as $svc) {
                $sid = (int) $svc->id;
                $label = mb_substr((string) ($svc->remark ?? 'svc #'.$sid), 0, 24);
                $svcIk[] = [['text' => '📦 '.$label, 'callback_data' => 'pnl:asv:'.$sid]];
            }
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_services', $admin, 'Services'), [
                'reply_markup' => ['inline_keyboard' => $svcIk],
            ]);
        }
    }

    public function sendServiceAdminMenu(BotContext $ctx, int $chatId, SvpUser $admin, int $serviceId): void
    {
        if (! $this->mayAccessService($admin, $serviceId)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

            return;
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.mutate.not_found', $admin, 'Not found'));

            return;
        }
        $this->managedContext->set($admin, (int) ($svc->user_id ?? 0), $serviceId);
        $body = 'Service #'.$serviceId."\n".(string) ($svc->remark ?? '');
        $sid = $serviceId;
        $ik = [
            [
                ['text' => '🔄 Sync', 'callback_data' => 'pnl:svc:sync:'.$sid],
                ['text' => '♻ Refresh', 'callback_data' => 'pnl:svc:rf:'.$sid],
            ],
            [
                ['text' => '🔑 Regen key', 'callback_data' => 'pnl:svc:rk:'.$sid],
                ['text' => '🔁 Regen sub', 'callback_data' => 'pnl:svc:rid:'.$sid],
            ],
            [
                ['text' => '♻ Renew', 'callback_data' => 'pnl:ar:'.$sid],
                ['text' => '➕ GB', 'callback_data' => 'pnl:av:'.$sid],
                ['text' => '➖ GB', 'callback_data' => 'pnl:rv:'.$sid],
            ],
            [
                ['text' => '➕ Days', 'callback_data' => 'pnl:ad:'.$sid],
                ['text' => '➖ Days', 'callback_data' => 'pnl:sd:'.$sid],
            ],
            [
                ['text' => '👥 +Slots', 'callback_data' => 'pnl:svc:sl:'.$sid],
                ['text' => '👥 -Slots', 'callback_data' => 'pnl:svc:rsl:'.$sid],
            ],
            [
                ['text' => '↔ Transfer', 'callback_data' => 'pnl:svc:xf:'.$sid],
                ['text' => '🌐 Limit IP', 'callback_data' => 'pnl:svc:li:'.$sid],
            ],
            [
                ['text' => '🔔 Alerts', 'callback_data' => 'pnl:svc:al:'.$sid],
                ['text' => '📝 Note', 'callback_data' => 'pnl:svc:sn:'.$sid],
            ],
            [
                ['text' => '🏷 Canonical', 'callback_data' => 'pnl:svc:can:'.$sid],
                ['text' => '📦 Panel xfer', 'callback_data' => 'pnl:svc:pt:'.$sid],
            ],
            [
                ['text' => '🗑 Panel client', 'callback_data' => 'pnl:svc:pdc:'.$sid],
            ],
            [
                ['text' => '✅ Enable', 'callback_data' => 'pnl:svc:tg:'.$sid.':1'],
                ['text' => '⏸ Disable', 'callback_data' => 'pnl:svc:tg:'.$sid.':0'],
            ],
            [['text' => '🗑 Delete', 'callback_data' => 'pnl:svc_del:'.$sid]],
        ];
        $this->send($ctx, $chatId, $body, ['reply_markup' => ['inline_keyboard' => $ik]]);
    }

    public function sendPendingPage(BotContext $ctx, int $chatId, SvpUser $admin, int $offset = 0): void
    {
        $this->sendQueuePage($ctx, $chatId, $admin, 'pending', $offset);
    }

    public function sendApprovedPage(BotContext $ctx, int $chatId, SvpUser $admin, int $offset = 0): void
    {
        $this->sendQueuePage($ctx, $chatId, $admin, 'approved', $offset);
    }

    public function sendRejectedPage(BotContext $ctx, int $chatId, SvpUser $admin, int $offset = 0): void
    {
        $this->sendQueuePage($ctx, $chatId, $admin, 'rejected', $offset);
    }

    protected function sendQueuePage(BotContext $ctx, int $chatId, SvpUser $admin, string $status, int $offset): void
    {
        $off = max(0, $offset);
        $bundle = $this->usersByStatus($admin, $status, $off, self::QUEUE_PAGE_SIZE);
        $list = $bundle['list'];
        $total = $bundle['total'];
        $queueKey = match ($status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'pending',
        };
        $body = ucfirst($status)." users ({$total})\nPage ".($off + 1);
        if ($list === []) {
            $body .= "\n—";
        } else {
            foreach ($list as $row) {
                $body .= "\n#".(int) $row->id.' '.(string) ($row->username ?? $row->tg_username ?? '');
            }
        }
        $extra = ['reply_markup' => $this->keyboards->inlineUsersQueue($queueKey, $list, $off, $total, self::QUEUE_PAGE_SIZE)];
        if ($status === 'pending') {
            foreach ($list as $row) {
                $uid = (int) $row->id;
                $this->send($ctx, $chatId, "⏳ #{$uid}", [
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => '✅', 'callback_data' => 'reg:a:'.$uid],
                            ['text' => '❌', 'callback_data' => 'reg:r:'.$uid],
                        ]],
                    ],
                ]);
            }
        }
        $this->send($ctx, $chatId, $body, $extra);
    }

    /** @param  array<string, mixed>  $from */
    public function routeModerationText(BotContext $ctx, SvpUser $admin, int $chatId, string $text, array $from): bool
    {
        $this->scope->setActingAdmin((int) $admin->id);
        $trimmed = trim($text);

        if ($this->routeModerationReplyShortcuts($ctx, $admin, $chatId, $trimmed, $from)) {
            return true;
        }

        if ($this->routeText($ctx, $admin, $chatId, $trimmed)) {
            return true;
        }

        $pendingBtn = $this->texts->getForUser('btn.admin.users_pending', $admin, 'Pending');
        $approvedBtn = $this->texts->getForUser('btn.admin.users_approved', $admin, 'Approved');
        $rejectedBtn = $this->texts->getForUser('btn.admin.users_rejected', $admin, 'Rejected');
        $searchBtn = $this->texts->getForUser('btn.admin.users_search', $admin, 'Search');
        if ($trimmed === $pendingBtn) {
            $this->sendPendingPage($ctx, $chatId, $admin, 0);

            return true;
        }
        if ($trimmed === $approvedBtn) {
            $this->sendApprovedPage($ctx, $chatId, $admin, 0);

            return true;
        }
        if ($trimmed === $rejectedBtn) {
            $this->sendRejectedPage($ctx, $chatId, $admin, 0);

            return true;
        }
        if ($trimmed === $searchBtn) {
            $this->state->set($admin, 'admin_users_search', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_user_search', $admin, 'Send user id'));

            return true;
        }

        if (preg_match('/^#(\d+)$/u', $trimmed, $m)) {
            $this->sendUserAdminCard($ctx, $chatId, $admin, (int) $m[1]);

            return true;
        }

        if ($this->state->get($admin) === 'admin_users_create_service') {
            return $this->routeCreateServiceText($ctx, $admin, $chatId, $trimmed);
        }

        $data = $this->state->data($admin);
        $action = (string) ($data['action'] ?? '');
        $targetUid = (int) ($data['target_uid'] ?? 0);
        if ($targetUid > 0 && $action === 'dm') {
            $result = $this->mutate->applyForUser($admin, 'user_admin_message', [
                'user_id' => $targetUid,
                'text' => $trimmed,
                'channel' => $ctx->platform,
            ]);
            $this->state->clear($admin);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return true;
        }
        if ($targetUid > 0 && in_array($action, ['credit', 'debit'], true) && is_numeric(str_replace([',', '٬'], '.', $trimmed))) {
            $amt = (float) str_replace([',', '٬'], '.', $trimmed);
            $delta = $action === 'credit' ? $amt : -$amt;
            $result = $this->mutate->applyForUser($admin, 'user_balance_delta', [
                'user_id' => $targetUid,
                'delta' => $delta,
            ]);
            $this->state->clear($admin);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return true;
        }

        if ($this->state->get($admin) === 'admin_users_search' && preg_match('/^\d+$/', $trimmed)) {
            $this->state->clear($admin);
            $this->sendUserAdminCard($ctx, $chatId, $admin, (int) $trimmed);

            return true;
        }

        return false;
    }

    /**
     * WP route_moderation_reply_text — approve/reject signup & receipt from reply keyboard
     * even when admin_mode is off (one-tap from notify messages).
     *
     * @param  array<string, mixed>  $from
     */
    public function routeModerationReplyShortcuts(BotContext $ctx, SvpUser $admin, int $chatId, string $text, array $from): bool
    {
        $fromId = (int) ($from['id'] ?? 0);
        if ($fromId < 1 || ! $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
            return false;
        }

        $tn = $this->normalizeDigits($text);
        $label = (string) ($from['username'] ?? $from['first_name'] ?? 'admin');

        $uid = $this->matchIdButton($tn, $admin, 'btn.admin.reg_approve', '✅ ثبت‌نام #{id}');
        if ($uid === null && preg_match('/^✅\s*ثبت‌نام\s*#(\d+)$/u', $tn, $m)) {
            $uid = (int) $m[1];
        }
        if ($uid !== null && $uid > 0) {
            if (! $this->permissions->mayCallOp($admin, 'user_approve') && ! $this->permissions->mayCallOp($admin, 'membership')) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

                return true;
            }
            if (! $this->scope->mayModerateUser($admin, $uid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_not_found', $admin, 'User not found'));

                return true;
            }
            $result = $this->mutate->applyForUser($admin, 'user_status', [
                'user_id' => $uid,
                'status' => 'approved',
            ]);
            $this->send($ctx, $chatId, ! empty($result['ok'])
                ? $this->texts->getForUser('msg.admin.signup_processed', $admin, 'Signup processed')
                : $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return true;
        }

        $uid = $this->matchIdButton($tn, $admin, 'btn.admin.reg_reject', '❌ رد ثبت‌نام #{id}');
        if ($uid === null && preg_match('/^❌\s*رد ثبت‌نام\s*#(\d+)$/u', $tn, $m)) {
            $uid = (int) $m[1];
        }
        if ($uid !== null && $uid > 0) {
            if (! $this->scope->mayModerateUser($admin, $uid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_not_found', $admin, 'User not found'));

                return true;
            }
            $result = $this->mutate->applyForUser($admin, 'user_status', [
                'user_id' => $uid,
                'status' => 'rejected',
            ]);
            $this->send($ctx, $chatId, ! empty($result['ok'])
                ? $this->texts->getForUser('msg.admin.signup_rejected_recorded', $admin, 'Signup rejected')
                : $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return true;
        }

        $rid = $this->matchIdButton($tn, $admin, 'btn.admin.receipt_approve', '✅ رسید {id}');
        if ($rid === null && preg_match('/^✅\s*رسید\s*(\d+)$/u', $tn, $m)) {
            $rid = (int) $m[1];
        }
        if ($rid !== null && $rid > 0) {
            if (! $this->permissions->mayCallOp($admin, 'receipt_review')) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

                return true;
            }
            $rec = DB::table('svp_receipts')->where('id', $rid)->first();
            if (! $rec || ! $this->scope->mayModerateUser($admin, (int) ($rec->user_id ?? 0))) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_not_found', $admin, 'User not found'));

                return true;
            }
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.processing', $admin, '⏳ Processing…'));
            $result = $this->mutate->applyForUser($admin, 'receipt_action', [
                'receipt_id' => $rid,
                'action' => 'approve',
                'admin_label' => $label,
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return true;
        }

        $rid = $this->matchIdButton($tn, $admin, 'btn.admin.receipt_reject', '❌ رد رسید {id}');
        if ($rid === null && preg_match('/^❌\s*رد رسید\s*(\d+)$/u', $tn, $m)) {
            $rid = (int) $m[1];
        }
        if ($rid !== null && $rid > 0) {
            if (! $this->permissions->mayCallOp($admin, 'receipt_review')) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

                return true;
            }
            $rec = DB::table('svp_receipts')->where('id', $rid)->first();
            if (! $rec || ! $this->scope->mayModerateUser($admin, (int) ($rec->user_id ?? 0))) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.user_not_found', $admin, 'User not found'));

                return true;
            }
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.pick_reject_reason', $admin, 'Pick reject reason'), [
                'reply_markup' => $this->keyboards->inlineReceiptRejectReasons($rid),
            ]);

            return true;
        }

        return false;
    }

    protected function normalizeDigits(string $text): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $t = str_replace($fa, $en, $text);
        $t = str_replace($ar, $en, $t);

        return trim($t);
    }

    /** @return int|null */
    protected function matchIdButton(string $text, SvpUser $admin, string $tplKey, string $fallbackTpl)
    {
        $raw = $this->texts->getForUser($tplKey, $admin, $fallbackTpl);
        $pattern = preg_replace('/\{id\}/', '(\\d+)', preg_quote($raw, '/'));
        if (! is_string($pattern) || $pattern === '') {
            return null;
        }
        if (preg_match('/^'.$pattern.'$/u', $text, $m)) {
            return (int) ($m[1] ?? 0);
        }

        return null;
    }

    public function routeCreateServiceText(BotContext $ctx, SvpUser $admin, int $chatId, string $text): bool
    {
        $data = $this->state->data($admin);
        $uid = (int) ($data['target_uid'] ?? 0);
        $step = (string) ($data['step'] ?? '');
        if ($uid < 1) {
            $this->state->clear($admin);

            return true;
        }
        if ($step === 'plan' && is_numeric($text)) {
            $planId = (int) $text;
            $plan = \App\Models\SvpPlan::query()->find($planId);
            if (! $plan || ! $plan->active) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.plan_unavailable', $admin, 'Plan unavailable'));

                return true;
            }
            if (app(\App\Modules\Core\Bot\Services\BotPlanPricingService::class)->planNeedsTrafficPrompt($plan)) {
                $this->state->set($admin, 'admin_users_create_service', [
                    'target_uid' => $uid,
                    'step' => 'volume',
                    'plan_id' => $planId,
                ]);
                $min = max(1, (int) ($plan->traffic_gb_min ?? 1));
                $max = max($min, (int) ($plan->traffic_gb_max ?? $min));
                $this->send($ctx, $chatId, $this->texts->format(
                    $this->texts->getForUser('msg.admin.prompt_create_service_volume', $admin, 'Send GB ({min}-{max})'),
                    ['min' => $min, 'max' => $max]
                ));

                return true;
            }
            $this->state->clear($admin);
            $this->send($ctx, $chatId, $this->texts->format(
                $this->texts->getForUser('msg.admin.prompt_create_service_mode', $admin, 'Create service for #{id} — choose mode:'),
                ['id' => $uid]
            ), [
                'reply_markup' => $this->keyboards->adminCreateServiceModeKeyboard($uid, $planId, null),
            ]);

            return true;
        }
        if ($step === 'volume' && is_numeric($text)) {
            $planId = (int) ($data['plan_id'] ?? 0);
            $gb = max(1, (int) $text);
            $this->state->clear($admin);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_create_service_mode', $admin, 'Choose payment mode:'), [
                'reply_markup' => $this->keyboards->adminCreateServiceModeKeyboard($uid, $planId, $gb),
            ]);

            return true;
        }
        if ($step === 'panel' && is_numeric($text)) {
            $result = $this->mutate->applyForUser($admin, 'user_create_service', [
                'user_id' => $uid,
                'plan_id' => (int) ($data['plan_id'] ?? 0),
                'panel_id' => (int) $text,
            ]);
            $this->state->clear($admin);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return true;
        }

        return false;
    }

    /**
     * pnl:nsx / nsm / nrr / nva / nus payment-mode execute (WP Handler_Admin_Pnl).
     *
     * @param  array<int, string>  $parts
     */
    protected function handlePaymentModeCallback(BotContext $ctx, SvpUser $admin, int $chatId, array $parts): void
    {
        $sub = (string) ($parts[1] ?? '');
        if ($sub === 'nsx' && isset($parts[2], $parts[3], $parts[4])) {
            $uid = (int) $parts[2];
            $planId = (int) $parts[3];
            $mode = $this->paymentModeFromLetter((string) $parts[4]);
            if ($mode === '' || ! $this->scope->mayModerateUser($admin, $uid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.method_invalid', $admin, 'Invalid method'));

                return;
            }
            $result = $this->mutate->applyForUser($admin, 'user_create_service', [
                'user_id' => $uid,
                'plan_id' => $planId,
                'mode' => $mode,
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($sub === 'nsm' && isset($parts[2], $parts[3], $parts[4], $parts[5])) {
            $uid = (int) $parts[2];
            $planId = (int) $parts[3];
            $gb = (int) $parts[4];
            $mode = $this->paymentModeFromLetter((string) $parts[5]);
            if ($mode === '' || ! $this->scope->mayModerateUser($admin, $uid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.method_invalid', $admin, 'Invalid method'));

                return;
            }
            $result = $this->mutate->applyForUser($admin, 'user_create_service', [
                'user_id' => $uid,
                'plan_id' => $planId,
                'volume_gb' => $gb,
                'mode' => $mode,
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($sub === 'nrr' && isset($parts[2], $parts[3])) {
            $sid = (int) $parts[2];
            $mode = $this->paymentModeFromLetter((string) $parts[3]);
            if ($mode === '' || ! $this->mayAccessService($admin, $sid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.method_invalid', $admin, 'Invalid method'));

                return;
            }
            $result = $this->mutate->applyForUser($admin, 'user_renew_service', [
                'service_id' => $sid,
                'mode' => $mode,
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($sub === 'nva' && isset($parts[2], $parts[3], $parts[4])) {
            $sid = (int) $parts[2];
            $gb = (int) $parts[3];
            $mode = $this->paymentModeFromLetter((string) $parts[4]);
            if ($mode === '' || ! $this->mayAccessService($admin, $sid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.method_invalid', $admin, 'Invalid method'));

                return;
            }
            $result = $this->mutate->applyForUser($admin, 'user_add_volume', [
                'service_id' => $sid,
                'extra_gb' => $gb,
                'mode' => $mode,
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($sub === 'nus' && isset($parts[2], $parts[3], $parts[4])) {
            $sid = (int) $parts[2];
            $slots = (int) $parts[3];
            $mode = $this->paymentModeFromLetter((string) $parts[4]);
            if ($mode === '' || ! $this->mayAccessService($admin, $sid)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.method_invalid', $admin, 'Invalid method'));

                return;
            }
            $result = $this->mutate->applyForUser($admin, 'user_service_add_slots', [
                'service_id' => $sid,
                'slots' => $slots,
                'mode' => $mode,
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));
        }
    }

    protected function paymentModeFromLetter(string $letter): string
    {
        return match (strtolower(trim($letter))) {
            'w' => 'wallet',
            'f' => 'free',
            'i' => 'invoice',
            'wallet', 'free', 'invoice' => strtolower(trim($letter)),
            default => '',
        };
    }

    public function handleUserAction(BotContext $ctx, SvpUser $admin, int $chatId, string $action, int $uid): void
    {
        if (! $this->scope->mayModerateUser($admin, $uid)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

            return;
        }
        $status = match ($action) {
            'blk' => 'blocked',
            'unb' => 'approved',
            default => '',
        };
        if ($status === '') {
            return;
        }
        $result = $this->mutate->applyForUser($admin, 'user_status', [
            'user_id' => $uid,
            'status' => $status,
        ]);
        $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));
    }

    public function handleUserReceiptCallback(BotContext $ctx, SvpUser $admin, int $chatId, string $action, int $receiptId): void
    {
        if (! $this->permissions->mayCallOp($admin, 'receipt_review')) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

            return;
        }
        $rec = DB::table('svp_receipts')->where('id', $receiptId)->first();
        if (! $rec || ! $this->scope->mayModerateUser($admin, (int) ($rec->user_id ?? 0))) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

            return;
        }
        if ($action === 'a') {
            $result = $this->mutate->applyForUser($admin, 'receipt_action', [
                'receipt_id' => $receiptId,
                'action' => 'approve',
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($admin, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'r') {
            $reasons = $this->keyboards->inlineReceiptRejectReasons($receiptId);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.pick_reject_reason', $admin, 'Pick reject reason'), [
                'reply_markup' => $reasons,
            ]);

            return;
        }
        if ($action === 'amt') {
            $this->state->set($admin, 'admin_users_receipt_update', [
                'receipt_id' => $receiptId,
                'step' => 'amount',
                'managed_user_id' => (int) ($rec->user_id ?? 0),
            ]);
            $this->managedContext->set($admin, (int) ($rec->user_id ?? 0));
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_receipt_amount', $admin, 'Send new amount'));

            return;
        }
        if ($action === 'st') {
            $this->state->set($admin, 'admin_users_receipt_update', [
                'receipt_id' => $receiptId,
                'step' => 'status',
                'managed_user_id' => (int) ($rec->user_id ?? 0),
            ]);
            $this->managedContext->set($admin, (int) ($rec->user_id ?? 0));
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_receipt_status', $admin, 'Send status: pending|approved|rejected|delivered'));
        }
    }

    protected function mayAccessService(SvpUser $admin, int $serviceId): bool
    {
        if ($serviceId < 1) {
            return false;
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            return false;
        }

        return $this->scope->mayModerateUser($admin, (int) ($svc->user_id ?? 0));
    }

    /** @return array{list: list<object>, total: int} */
    protected function usersByStatus(SvpUser $admin, string $status, int $offset, int $limit): array
    {
        $scopeIds = $this->scope->botAdminScopeUserIds($admin);
        $q = DB::table('svp_users')->where('status', $status)->orderByDesc('id');
        if (is_array($scopeIds)) {
            $q->whereIn('id', $scopeIds !== [] ? $scopeIds : [-1]);
        }
        $total = (int) (clone $q)->count();
        $list = $q->offset($offset)->limit($limit)->get()->all();

        return ['list' => $list, 'total' => $total];
    }

    protected function countByStatus(SvpUser $admin, string $status): int
    {
        return $this->usersByStatus($admin, $status, 0, 1)['total'];
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return "Users\nPending: ".$this->countByStatus($user, 'pending');
    }
}
