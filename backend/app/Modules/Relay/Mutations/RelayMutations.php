<?php

namespace App\Modules\Relay\Mutations;

use App\Modules\Relay\Services\TelegramRelayService;
use Illuminate\Contracts\Auth\Authenticatable;

class RelayMutations
{
    public function __construct(protected TelegramRelayService $relay) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'telegram_relay_test' => [self::class, 'test'],
            'telegram_relay_sync' => [self::class, 'sync'],
            'telegram_relay_set_webhook' => [self::class, 'setWebhook'],
            'telegram_relay_rotate_secret' => [self::class, 'rotateSecret'],
            'telegram_relay_status' => [self::class, 'status'],
            'telegram_relay_domains_sync' => [self::class, 'domainsSync'],
            'telegram_relay_set_webhook_reseller' => [self::class, 'setWebhookReseller'],
            'telegram_relay_auto_sync' => [self::class, 'autoSync'],
            'telegram_relay_admin_dashboard' => [self::class, 'adminDashboard'],
            'telegram_relay_admin_doctor' => [self::class, 'adminDoctor'],
            'telegram_relay_admin_logs' => [self::class, 'adminLogs'],
            'telegram_relay_admin_ssl_status' => [self::class, 'adminSslStatus'],
            'telegram_relay_admin_domain_add' => [self::class, 'adminDomainAdd'],
            'telegram_relay_admin_domain_remove' => [self::class, 'adminDomainRemove'],
            'telegram_relay_admin_nginx_render' => [self::class, 'adminNginxRender'],
            'telegram_relay_admin_nginx_test' => [self::class, 'adminNginxTest'],
            'telegram_relay_admin_nginx_reload' => [self::class, 'adminNginxReload'],
            'telegram_relay_admin_ssl_issue' => [self::class, 'adminSslIssue'],
            'telegram_relay_admin_ssl_renew' => [self::class, 'adminSslRenew'],
            'telegram_relay_admin_service_restart' => [self::class, 'adminServiceRestart'],
            'telegram_relay_admin_update' => [self::class, 'adminUpdate'],
            'telegram_relay_admin_job' => [self::class, 'adminJob'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function test(array $payload, ?Authenticatable $actor): array
    {
        $res = $this->relay->health();

        return [
            'ok' => ! empty($res['ok']),
            'message' => (string) ($res['message'] ?? ''),
            'data' => is_array($res['data'] ?? null) ? $res['data'] : [],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function sync(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->pushConfigToRelay();
    }

    /** @param  array<string, mixed>  $payload */
    public function setWebhook(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->setWebhookViaRelay('main', 0, true);
    }

    /** @param  array<string, mixed>  $payload */
    public function rotateSecret(array $payload, ?Authenticatable $actor): array
    {
        $sec = $this->relay->rotateRelaySecret();

        return svp_ok(['secret' => $sec]);
    }

    /** @param  array<string, mixed>  $payload */
    public function status(array $payload, ?Authenticatable $actor): array
    {
        $res = $this->relay->status();

        return [
            'ok' => ! empty($res['ok']),
            'message' => (string) ($res['message'] ?? ''),
            'data' => is_array($res['data'] ?? null) ? $res['data'] : [],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function domainsSync(array $payload, ?Authenticatable $actor): array
    {
        $sync = $this->relay->pushConfigToRelay();
        if (empty($sync['ok'])) {
            return $sync;
        }

        return $this->relay->domainsSync();
    }

    /** @param  array<string, mixed>  $payload */
    public function setWebhookReseller(array $payload, ?Authenticatable $actor): array
    {
        $rid = (int) ($payload['reseller_svp_user_id'] ?? $payload['bot_id'] ?? 0);
        if ($rid < 1) {
            return svp_err('invalid_reseller');
        }

        return $this->relay->setWebhookViaRelay('reseller', $rid, true);
    }

    /** @param  array<string, mixed>  $payload */
    public function autoSync(array $payload, ?Authenticatable $actor): array
    {
        $res = $this->relay->autoSyncAfterSave();

        return [
            'ok' => ! empty($res['ok']),
            'message' => (string) ($res['message'] ?? ''),
            'data' => isset($res['steps']) ? ['steps' => $res['steps']] : [],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function adminDashboard(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('GET', '/internal/admin/dashboard', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminDoctor(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('GET', '/internal/admin/doctor', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminLogs(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('GET', '/internal/admin/logs', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminSslStatus(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('GET', '/internal/admin/ssl/status', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminDomainAdd(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/domains/add', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminDomainRemove(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/domains/remove', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminNginxRender(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/nginx/render', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminNginxTest(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/nginx/test', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminNginxReload(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/nginx/reload', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminSslIssue(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/ssl/issue', $payload, 15);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminSslRenew(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/ssl/renew', $payload, 15);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminServiceRestart(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/service/restart', $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminUpdate(array $payload, ?Authenticatable $actor): array
    {
        return $this->relay->adminProxy('POST', '/internal/admin/update', $payload, 15);
    }

    /** @param  array<string, mixed>  $payload */
    public function adminJob(array $payload, ?Authenticatable $actor): array
    {
        $jobId = (string) ($payload['job_id'] ?? '');

        return $this->relay->adminProxy('GET', '/internal/admin/jobs/'.$jobId, $payload);
    }
}
