<?php

namespace App\Modules\Backup\Http;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Jobs\ManualBackupJob;
use App\Modules\Backup\Services\BackupExportService;
use App\Modules\Backup\Services\BackupRestoreService;
use App\Modules\Backup\Services\BackupScopeService;
use App\Modules\Backup\Services\BackupStatusService;
use App\Services\BackupIntervalResolver;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(Request $request, BackupExportService $export, SettingsStore $settings, BackupScopeService $scope): JsonResponse
    {
        $panels = [];
        if (DB::getSchemaBuilder()->hasTable('svp_panels')) {
            $panels = DB::table('svp_panels')
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(['id', 'label'])
                ->map(fn ($p) => ['id' => (int) $p->id, 'label' => (string) $p->label])
                ->all();
        }

        $lastRun = $settings->get('backup_last_run', []);
        if (! is_array($lastRun)) {
            $lastRun = [];
        }

        $interval = app(BackupIntervalResolver::class)->minutes();

        return response()->json([
            'ok' => true,
            'rows' => $export->listFiles(),
            'panels' => $panels,
            'last_backup_at' => (int) ($lastRun['at'] ?? 0),
            'last_built_at' => (int) $settings->get('backup_last_built_at', 0),
            'store_on_site' => (bool) $settings->get('backup_store_on_site', true),
            'backup_interval_minutes' => $interval,
            'cron_registered' => true,
            'last_run' => $lastRun,
            'backup_display_timezone' => config('app.timezone', 'UTC'),
            'site_timezone' => config('app.timezone', 'UTC'),
            'backup_scope' => $scope->forApi($panels),
        ]);
    }

    public function status(Request $request, BackupStatusService $status): JsonResponse
    {
        return response()->json($status->getStatus());
    }

    public function run(Request $request, BackupStatusService $status, BackupScopeService $scope): JsonResponse
    {
        $panels = [];
        if (DB::getSchemaBuilder()->hasTable('svp_panels')) {
            $panels = DB::table('svp_panels')
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(['id', 'label'])
                ->map(fn ($p) => ['id' => (int) $p->id, 'label' => (string) $p->label])
                ->all();
        }
        if (! $scope->hasAnyEnabled($panels)) {
            return response()->json([
                'ok' => false,
                'code' => 'backup_scope_empty',
                'status' => 'error',
                'message' => 'هیچ بخشی برای بکاپ انتخاب نشده است.',
            ]);
        }

        $status->resetStale();
        if ($status->isRunning()) {
            return response()->json([
                'ok' => false,
                'code' => 'already_running',
                'status' => 'running',
                'message' => 'بکاپ دیگری در حال اجراست. چند لحظه صبر کنید.',
            ]);
        }

        $status->startManual();
        ManualBackupJob::dispatch();

        return response()->json([
            'ok' => true,
            'async' => true,
            'status' => 'running',
            'message' => 'بکاپ روی سرور شروع شد. چند لحظه صبر کنید…',
        ]);
    }

    public function resetStuck(Request $request, BackupStatusService $status): JsonResponse
    {
        return response()->json($status->resetStuck());
    }

    public function download(Request $request, BackupExportService $export): BinaryFileResponse|JsonResponse
    {
        $filename = basename((string) $request->query('filename', ''));
        if ($filename === '') {
            return response()->json(['ok' => false, 'message' => 'missing_filename'], 400);
        }

        $path = $export->resolvePath($filename);
        if ($path === null) {
            return response()->json(['ok' => false, 'message' => 'not_found'], 404);
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function restore(Request $request, BackupExportService $export, BackupRestoreService $restore): JsonResponse
    {
        $params = $request->json()->all();
        $filename = basename((string) ($params['filename'] ?? ''));
        $confirm = ! empty($params['confirm']);
        $restorePanelDb = ! empty($params['restore_panel_db']);

        if (! $confirm) {
            return response()->json(['ok' => false, 'message' => 'برای ریستور باید تایید شود.'], 400);
        }

        $path = $export->resolvePath($filename);
        if ($path === null) {
            return response()->json(['ok' => false, 'message' => 'فایل بکاپ یافت نشد.'], 400);
        }

        $res = $restore->restoreFromZip($path, $restorePanelDb);
        $code = ! empty($res['ok']) ? 200 : 400;

        return response()->json($res, $code);
    }

    public function restoreUpload(Request $request, BackupRestoreService $restore): JsonResponse
    {
        $confirm = $request->input('confirm');
        $confirmOk = ! empty($confirm) && ($confirm === true || $confirm === 1 || $confirm === '1');
        if (! $confirmOk) {
            return response()->json(['ok' => false, 'message' => 'برای ریستور باید تایید شود.'], 400);
        }

        $restorePanelParam = $request->input('restore_panel_db');
        $restorePanelDb = ! empty($restorePanelParam)
            && ($restorePanelParam === true || $restorePanelParam === 1 || $restorePanelParam === '1');

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response()->json(['ok' => false, 'message' => 'فایلی ارسال نشده است.'], 400);
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return response()->json(['ok' => false, 'message' => 'فقط فایل .zip مجاز است.'], 400);
        }

        $dest = $file->storeAs('backup-uploads', 'restore-'.time().'.zip');
        $path = storage_path('app/'.$dest);
        $res = $restore->restoreFromZip($path, $restorePanelDb);
        @unlink($path);

        $code = ! empty($res['ok']) ? 200 : 400;

        return response()->json($res, $code);
    }

}
