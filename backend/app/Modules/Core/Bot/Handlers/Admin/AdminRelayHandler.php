<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Services\SettingsStore;

class AdminRelayHandler extends AbstractAdminHandler
{
    /** @var list<string> */
    protected const NO_PARAM_OPS = [
        'telegram_relay_test',
        'telegram_relay_sync',
        'telegram_relay_set_webhook',
        'telegram_relay_rotate_secret',
        'telegram_relay_status',
        'telegram_relay_domains_sync',
        'telegram_relay_auto_sync',
        'telegram_relay_admin_dashboard',
        'telegram_relay_admin_doctor',
        'telegram_relay_admin_logs',
        'telegram_relay_admin_ssl_status',
        'telegram_relay_admin_nginx_render',
        'telegram_relay_admin_nginx_test',
        'telegram_relay_admin_nginx_reload',
        'telegram_relay_admin_service_restart',
        'telegram_relay_admin_update',
    ];

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected SettingsStore $settings,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
        protected AdminPermissionService $permissions,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.tab.relay', $user, 'Relay');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        if ($tabKey !== 'relay') {
            $this->send($ctx, $chatId, $this->sectionIntro($user));

            return;
        }
        if ($this->permissions->permissionActorId($user) > 0) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $enabled = ! empty($this->settings->get('telegram_relay_enabled', false)) ? '✅' : '❌';
        $url = (string) $this->settings->get('telegram_relay_admin_url', '—');
        $body = "Telegram relay {$enabled}\nAdmin URL: {$url}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->relayButtonsList($user);
        }
        $this->send($ctx, $chatId, $body);
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        if ($this->permissions->permissionActorId($user) > 0) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $action = (string) ($parts[2] ?? '');
        $op = match ($action) {
            't' => 'telegram_relay_test',
            'sy' => 'telegram_relay_sync',
            'wh' => 'telegram_relay_set_webhook',
            'rs' => 'telegram_relay_rotate_secret',
            'st' => 'telegram_relay_status',
            'ds' => 'telegram_relay_domains_sync',
            'as' => 'telegram_relay_auto_sync',
            default => '',
        };
        if ($op === '') {
            return;
        }
        $result = $this->mutate->applyForUser($user, $op, []);
        $this->send($ctx, $chatId, $this->mutateResult($user, $result));
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        if ($this->permissions->permissionActorId($user) > 0) {
            return false;
        }
        $trimmed = trim($text);

        foreach ($this->relayTextButtons($user) as $label => $cfg) {
            if ($trimmed !== $label) {
                continue;
            }
            if (($cfg['kind'] ?? '') === 'wizard') {
                $this->state->set($user, (string) $cfg['state'], ['step' => (string) ($cfg['step'] ?? 'input')]);
                $this->send($ctx, $chatId, (string) ($cfg['prompt'] ?? 'Send value'));

                return true;
            }
            $result = $this->mutate->applyForUser($user, (string) $cfg['op'], is_array($cfg['params'] ?? null) ? $cfg['params'] : []);
            $this->send($ctx, $chatId, $this->mutateResult($user, $result));

            return true;
        }

        $editRelayBtn = $this->texts->getForUser('btn.admin.relay_edit_enabled', $user, 'Edit relay enabled');
        if ($trimmed === $editRelayBtn) {
            $this->startRelaySettingsWizard($ctx, $chatId, $user, 'telegram_relay_enabled', 'Send 1/0 or yes/no');

            return true;
        }
        $editUrlBtn = $this->texts->getForUser('btn.admin.relay_edit_admin_url', $user, 'Edit relay admin URL');
        if ($trimmed === $editUrlBtn) {
            $this->startRelaySettingsWizard($ctx, $chatId, $user, 'telegram_relay_admin_url', 'Send relay admin URL');

            return true;
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $data = $this->state->data($user);
        $trimmed = trim($text);

        if ($state === 'admin_relay_domain_add' && $trimmed !== '') {
            $result = $this->mutate->applyForUser($user, 'telegram_relay_admin_domain_add', ['domain' => $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutateResult($user, $result));

            return;
        }
        if ($state === 'admin_relay_domain_remove' && $trimmed !== '') {
            $result = $this->mutate->applyForUser($user, 'telegram_relay_admin_domain_remove', ['domain' => $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutateResult($user, $result));

            return;
        }
        if ($state === 'admin_relay_ssl_issue' && $trimmed !== '') {
            $result = $this->mutate->applyForUser($user, 'telegram_relay_admin_ssl_issue', ['domain' => $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutateResult($user, $result));

            return;
        }
        if ($state === 'admin_relay_ssl_renew' && $trimmed !== '') {
            $result = $this->mutate->applyForUser($user, 'telegram_relay_admin_ssl_renew', ['domain' => $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutateResult($user, $result));

            return;
        }
        if ($state === 'admin_relay_admin_job' && $trimmed !== '') {
            $result = $this->mutate->applyForUser($user, 'telegram_relay_admin_job', ['job_id' => $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutateResult($user, $result));

            return;
        }
        if (str_starts_with($state, 'admin_relay_settings_')) {
            $key = (string) ($data['key'] ?? '');
            if ($key !== '' && $trimmed !== '') {
                $result = $this->mutate->applyForUser($user, 'settings_tab', [
                    'tab' => 'relay',
                    $key => $this->castBoolish($key, $trimmed),
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutateResult($user, $result));
            }
        }
    }

    protected function startRelaySettingsWizard(BotContext $ctx, int $chatId, SvpUser $user, string $key, string $prompt): void
    {
        $this->state->set($user, 'admin_relay_settings_'.$key, ['key' => $key]);
        $this->send($ctx, $chatId, $prompt);
    }

    /** @return array<string, array<string, mixed>> */
    protected function relayTextButtons(SvpUser $user): array
    {
        return [
            $this->texts->getForUser('btn.admin.relay_test', $user, 'Relay test') => ['op' => 'telegram_relay_test'],
            $this->texts->getForUser('btn.admin.relay_sync', $user, 'Relay sync') => ['op' => 'telegram_relay_sync'],
            $this->texts->getForUser('btn.admin.relay_set_webhook', $user, 'Relay set webhook') => ['op' => 'telegram_relay_set_webhook'],
            $this->texts->getForUser('btn.admin.relay_rotate_secret', $user, 'Rotate relay secret') => ['op' => 'telegram_relay_rotate_secret'],
            $this->texts->getForUser('btn.admin.relay_status', $user, 'Relay status') => ['op' => 'telegram_relay_status'],
            $this->texts->getForUser('btn.admin.relay_domains_sync', $user, 'Relay domains sync') => ['op' => 'telegram_relay_domains_sync'],
            $this->texts->getForUser('btn.admin.relay_auto_sync', $user, 'Relay auto sync') => ['op' => 'telegram_relay_auto_sync'],
            $this->texts->getForUser('btn.admin.relay_admin_dashboard', $user, 'Relay dashboard') => ['op' => 'telegram_relay_admin_dashboard'],
            $this->texts->getForUser('btn.admin.relay_admin_doctor', $user, 'Relay doctor') => ['op' => 'telegram_relay_admin_doctor'],
            $this->texts->getForUser('btn.admin.relay_admin_logs', $user, 'Relay logs') => ['op' => 'telegram_relay_admin_logs'],
            $this->texts->getForUser('btn.admin.relay_admin_ssl_status', $user, 'Relay SSL status') => ['op' => 'telegram_relay_admin_ssl_status'],
            $this->texts->getForUser('btn.admin.relay_admin_nginx_render', $user, 'Relay nginx render') => ['op' => 'telegram_relay_admin_nginx_render'],
            $this->texts->getForUser('btn.admin.relay_admin_nginx_test', $user, 'Relay nginx test') => ['op' => 'telegram_relay_admin_nginx_test'],
            $this->texts->getForUser('btn.admin.relay_admin_nginx_reload', $user, 'Relay nginx reload') => ['op' => 'telegram_relay_admin_nginx_reload'],
            $this->texts->getForUser('btn.admin.relay_admin_service_restart', $user, 'Relay service restart') => ['op' => 'telegram_relay_admin_service_restart'],
            $this->texts->getForUser('btn.admin.relay_admin_update', $user, 'Relay update') => ['op' => 'telegram_relay_admin_update'],
            $this->texts->getForUser('btn.admin.relay_domain_add', $user, 'Relay add domain') => [
                'kind' => 'wizard', 'state' => 'admin_relay_domain_add', 'prompt' => 'Send domain',
            ],
            $this->texts->getForUser('btn.admin.relay_domain_remove', $user, 'Relay remove domain') => [
                'kind' => 'wizard', 'state' => 'admin_relay_domain_remove', 'prompt' => 'Send domain',
            ],
            $this->texts->getForUser('btn.admin.relay_ssl_issue', $user, 'Relay SSL issue') => [
                'kind' => 'wizard', 'state' => 'admin_relay_ssl_issue', 'prompt' => 'Send domain for SSL issue',
            ],
            $this->texts->getForUser('btn.admin.relay_ssl_renew', $user, 'Relay SSL renew') => [
                'kind' => 'wizard', 'state' => 'admin_relay_ssl_renew', 'prompt' => 'Send domain for SSL renew',
            ],
            $this->texts->getForUser('btn.admin.relay_admin_job', $user, 'Relay admin job') => [
                'kind' => 'wizard', 'state' => 'admin_relay_admin_job', 'prompt' => 'Send job id',
            ],
        ];
    }

    protected function relayButtonsList(SvpUser $user): string
    {
        $lines = array_keys($this->relayTextButtons($user));
        $lines[] = $this->texts->getForUser('btn.admin.relay_edit_enabled', $user, 'Edit relay enabled');
        $lines[] = $this->texts->getForUser('btn.admin.relay_edit_admin_url', $user, 'Edit relay admin URL');

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $result */
    protected function mutateResult(SvpUser $user, array $result): string
    {
        $msg = $this->mutate->resultMessage($user, $result);
        if (! empty($result['ok']) && ! empty($result['message']) && is_string($result['message'])) {
            $msg .= "\n".$result['message'];
        }
        if (! empty($result['ok']) && ! empty($result['data']) && is_array($result['data'])) {
            $snippet = mb_substr(json_encode($result['data'], JSON_UNESCAPED_UNICODE) ?: '', 0, 400);
            if ($snippet !== '') {
                $msg .= "\n".$snippet;
            }
        }

        return $msg;
    }

    protected function castBoolish(string $key, string $value): mixed
    {
        if (! str_contains($key, 'enabled') && ! str_contains($key, 'verify') && ! str_contains($key, 'force')) {
            return $value;
        }
        $v = mb_strtolower(trim($value));

        return in_array($v, ['1', 'yes', 'y', 'true', 'on', 'بله'], true) ? true
            : (in_array($v, ['0', 'no', 'n', 'false', 'off', 'خیر'], true) ? false : $value);
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $enabled = ! empty($this->settings->get('telegram_relay_enabled', false)) ? 'on' : 'off';

        return "Relay: {$enabled}";
    }
}
