<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Services\BackupRestoreService;
use App\Services\InstallWizard\DomainProbeService;
use App\Services\InstallWizard\InstallWizardService;
use App\Services\Migration\WpImportService;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use ZipArchive;

class SetupWizardController extends Controller
{
    public function status(InstallWizardService $wizard): JsonResponse
    {
        return response()->json($wizard->statusPayload());
    }

    public function domains(Request $request, InstallWizardService $wizard, DomainProbeService $probe): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'urls' => $probe->currentDomainUrls(),
            'snapshot' => $wizard->hostsSnapshot(),
            'probes' => $probe->probeAll(),
        ]);
    }

    public function updateDomains(Request $request, InstallWizardService $wizard, DomainProbeService $probe, SettingsStore $settings): JsonResponse
    {
        $payload = $request->json()->all();
        $core = rtrim((string) ($payload['core_url'] ?? ''), '/');
        if ($core === '') {
            return response()->json(['ok' => false, 'message' => 'core_url_required'], 400);
        }

        $patch = [
            'public_site_url' => $core,
            'enabled' => true,
        ];
        $dashboard = rtrim((string) ($payload['dashboard_url'] ?? ''), '/');
        if ($dashboard !== '') {
            $patch['dashboard_public_url'] = $dashboard;
        }
        $telegram = rtrim((string) ($payload['telegram_url'] ?? ''), '/');
        if ($telegram !== '') {
            $patch['telegram_public_webhook_base'] = $telegram;
            $patch['telegram_relay_laravel_forward_url'] = $telegram;
        }
        $bale = rtrim((string) ($payload['bale_url'] ?? ''), '/');
        if ($bale !== '') {
            $patch['bale_public_webhook_base'] = $bale;
        }
        $relay = rtrim((string) ($payload['relay_url'] ?? ''), '/');
        if ($relay !== '') {
            $patch['telegram_relay_public_url'] = $relay;
            $patch['telegram_relay_admin_url'] = $relay;
        }
        if (! empty($payload['enable_relay'])) {
            $patch['telegram_relay_enabled'] = true;
        }

        $urls = [
            'core_url' => $core,
            'dashboard_url' => $dashboard,
            'telegram_url' => $telegram,
            'bale_url' => $bale,
            'relay_url' => $relay,
        ];

        $settings->merge($patch);
        $wizard->updateHostsSnapshot(array_merge($wizard->hostsSnapshot(), $urls));

        $hostReconfigure = $probe->hostReconfigureRequired($urls);
        $reinstallHint = null;
        if ($hostReconfigure) {
            $reinstallHint = $this->buildReinstallHint($urls);
        }

        return response()->json([
            'ok' => true,
            'urls' => $probe->currentDomainUrls(),
            'probes' => $probe->probeAll(),
            'host_reconfigure_required' => $hostReconfigure,
            'reinstall_hint' => $reinstallHint,
        ]);
    }

    public function probeDomains(DomainProbeService $probe): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'probes' => $probe->probeAll(),
        ]);
    }

    public function registerWebhooks(Request $request): JsonResponse
    {
        $platform = (string) ($request->input('platform') ?? 'both');
        if (! in_array($platform, ['telegram', 'bale', 'both'], true)) {
            $platform = 'both';
        }
        $code = Artisan::call('svp:register-webhooks', ['--platform' => $platform]);
        $output = trim(Artisan::output());

        return response()->json([
            'ok' => $code === 0,
            'output' => $output,
        ], $code === 0 ? 200 : 500);
    }

    public function restoreBackup(Request $request, BackupRestoreService $restore): JsonResponse
    {
        $confirm = $request->input('confirm');
        $confirmOk = ! empty($confirm) && ($confirm === true || $confirm === 1 || $confirm === '1');
        if (! $confirmOk) {
            return response()->json(['ok' => false, 'message' => 'confirm_required'], 400);
        }

        $restorePanelParam = $request->input('restore_panel_db');
        $restorePanelDb = ! empty($restorePanelParam)
            && ($restorePanelParam === true || $restorePanelParam === 1 || $restorePanelParam === '1');

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response()->json(['ok' => false, 'message' => 'file_missing'], 400);
        }
        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return response()->json(['ok' => false, 'message' => 'zip_only'], 400);
        }

        $dir = storage_path('app/setup-uploads');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/restore-'.time().'.zip';
        $file->move($dir, basename($path));
        $res = $restore->restoreFromZip($path, $restorePanelDb);
        @unlink($path);

        if (! empty($res['ok'])) {
            Artisan::call('svp:rebuild-reseller-closure');
        }

        $code = ! empty($res['ok']) ? 200 : 400;

        return response()->json($res, $code);
    }

    public function importWordpress(Request $request, WpImportService $import): JsonResponse
    {
        if ($this->rateLimited($request, 'setup-wp-import')) {
            return response()->json(svp_err('rate_limited'), 429);
        }

        $dryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);
        $force = filter_var($request->input('force', false), FILTER_VALIDATE_BOOLEAN);
        $verifyOnly = filter_var($request->input('verify_only', false), FILTER_VALIDATE_BOOLEAN);
        $prefix = (string) ($request->input('prefix') ?: 'wp_');

        $dir = storage_path('app/setup-uploads');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $sqlPath = null;
        $backupsFrom = null;

        $file = $request->file('file');
        if ($file && $file->isValid()) {
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'sql') {
                $sqlPath = $dir.'/wp-'.time().'.sql';
                $file->move($dir, basename($sqlPath));
            } elseif ($ext === 'zip') {
                $zipPath = $dir.'/wp-bundle-'.time().'.zip';
                $file->move($dir, basename($zipPath));
                $extracted = $this->extractWordpressBundle($zipPath, $dir);
                @unlink($zipPath);
                if ($extracted === null) {
                    return response()->json(['ok' => false, 'message' => 'invalid_wp_zip'], 400);
                }
                $sqlPath = $extracted['sql'];
                $backupsFrom = $extracted['backups_dir'];
            } else {
                return response()->json(['ok' => false, 'message' => 'sql_or_zip_only'], 400);
            }
        } else {
            $usePath = (string) $request->input('path', '');
            if ($usePath !== '' && is_file($usePath)) {
                $sqlPath = $usePath;
            }
        }

        if ($sqlPath === null || ! is_file($sqlPath)) {
            return response()->json(['ok' => false, 'message' => 'file_missing'], 400);
        }

        $defaultPassword = (string) ($request->input('default_password') ?: Str::password(16));

        try {
            $res = $import->run($sqlPath, [
                'prefix' => $prefix,
                'dry_run' => $dryRun,
                'force' => $force,
                'verify_only' => $verifyOnly,
                'default_password' => $defaultPassword,
                'backups_from' => $backupsFrom,
            ]);
        } finally {
            if (str_starts_with($sqlPath, $dir)) {
                @unlink($sqlPath);
            }
            if ($backupsFrom !== null && is_dir($backupsFrom)) {
                File::deleteDirectory($backupsFrom);
            }
        }

        $code = ! empty($res['ok']) ? 200 : 400;

        return response()->json($res, $code);
    }

    public function adminCredentials(Request $request, InstallWizardService $wizard): JsonResponse
    {
        $username = (string) ($request->input('username') ?? '');
        $password = (string) ($request->input('password') ?? '');
        $confirm = (string) ($request->input('password_confirm') ?? '');

        if ($password !== $confirm) {
            return response()->json(['ok' => false, 'message' => 'password_mismatch'], 400);
        }

        try {
            $wizard->setAdminCredentials($username, $password);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }

        return response()->json(['ok' => true]);
    }

    public function complete(InstallWizardService $wizard): JsonResponse
    {
        $loginUrl = $wizard->complete();

        return response()->json([
            'ok' => true,
            'dashboard_login_url' => $loginUrl,
        ]);
    }

    /** @param  array<string, string>  $urls */
    protected function buildReinstallHint(array $urls): string
    {
        $parts = ['sudo bash backend/scripts/ops/install.sh --mode all --non-interactive'];
        if ($urls['core_url'] !== '') {
            $host = parse_url($urls['core_url'], PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $parts[] = '--core-domain='.$host;
            }
        }
        if ($urls['dashboard_url'] !== '') {
            $host = parse_url($urls['dashboard_url'], PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $parts[] = '--dashboard-domain='.$host;
            }
        }
        if ($urls['telegram_url'] !== '') {
            $host = parse_url($urls['telegram_url'], PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $parts[] = '--telegram-domain='.$host;
            }
        }
        if ($urls['bale_url'] !== '') {
            $host = parse_url($urls['bale_url'], PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $parts[] = '--bale-domain='.$host;
            }
        }
        if ($urls['relay_url'] !== '') {
            $host = parse_url($urls['relay_url'], PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $parts[] = '--relay-domain='.$host;
            }
        }

        return implode(' ', $parts).' --ssl certbot --email you@example.com';
    }

    /** @return array{sql: string, backups_dir: string|null}|null */
    protected function extractWordpressBundle(string $zipPath, string $baseDir): ?array
    {
        $extractDir = $baseDir.'/wp-extract-'.time();
        mkdir($extractDir, 0755, true);
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            File::deleteDirectory($extractDir);

            return null;
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $sqlPath = null;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'sql') {
                $sqlPath = $file->getPathname();
                break;
            }
        }
        if ($sqlPath === null) {
            File::deleteDirectory($extractDir);

            return null;
        }

        $backupsDir = null;
        foreach (['simplevpbot-backups', 'backups', 'uploads/simplevpbot-backups'] as $candidate) {
            $path = $extractDir.'/'.$candidate;
            if (is_dir($path)) {
                $backupsDir = $path;
                break;
            }
        }

        return ['sql' => $sqlPath, 'backups_dir' => $backupsDir];
    }

    protected function rateLimited(Request $request, string $bucket): bool
    {
        $key = $bucket.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            return true;
        }
        RateLimiter::hit($key, 60);

        return false;
    }
}
