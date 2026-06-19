<?php

use App\Http\Controllers\Api\V1\AdminStateController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\DashboardSessionController;
use App\Http\Controllers\Api\V1\ImpersonationController;
use App\Http\Controllers\Api\V1\InboundDisplayCatalogController;
use App\Http\Controllers\Api\V1\LogsController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MutateController;
use App\Http\Controllers\Api\V1\PurgeExpiredController;
use App\Http\Controllers\Api\V1\SetupWizardController;
use App\Http\Controllers\Api\V1\UserPortalController;
use App\Http\Controllers\Api\V1\UsersBulkController;
use App\Http\Middleware\AdminDashboardRateLimit;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminOrReseller;
use App\Http\Middleware\EnsureBackupModule;
use App\Modules\Backup\Http\BackupController;
use App\Modules\Marketing\Http\BroadcastController;
use App\Modules\XuiPanel\Http\ConfigsController;
use App\Modules\XuiPanel\Http\PanelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware(['web', 'install.wizard.complete']);
    Route::post('auth/token', [AuthController::class, 'token'])
        ->middleware('install.wizard.complete');
    Route::get('bootstrap', BootstrapController::class)->middleware('web');

    Route::get('setup/status', [SetupWizardController::class, 'status']);

    Route::middleware(['install.wizard.open'])->prefix('setup')->group(function () {
        Route::get('domains', [SetupWizardController::class, 'domains']);
        Route::post('domains', [SetupWizardController::class, 'updateDomains']);
        Route::post('domains/probe', [SetupWizardController::class, 'probeDomains']);
        Route::post('domains/register-webhooks', [SetupWizardController::class, 'registerWebhooks']);
        Route::post('backup/restore', [SetupWizardController::class, 'restoreBackup']);
        Route::post('backup/wordpress', [SetupWizardController::class, 'importWordpress']);
        Route::post('admin-credentials', [SetupWizardController::class, 'adminCredentials']);
        Route::post('complete', [SetupWizardController::class, 'complete']);
    });

    Route::middleware(['web', 'auth:sanctum', 'dashboard.enabled', 'reseller.scope'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me/state', [DashboardSessionController::class, 'meState']);
        Route::get('me/portal', UserPortalController::class);
        Route::post('dashboard/persona', [DashboardSessionController::class, 'setPersona']);
        Route::post('dashboard/ui-preferences', [DashboardSessionController::class, 'uiPreferences']);
        Route::post('dashboard/impersonate/start', [ImpersonationController::class, 'start'])->middleware(EnsureAdmin::class);
        Route::post('dashboard/impersonate/stop', [ImpersonationController::class, 'stop'])->middleware(EnsureAdmin::class);

        // Canonical /admin/* plus legacy /dashboard/admin/* alias (§7.2 v26 — mirrors nginx rewrite).
        foreach (['admin', 'dashboard/admin'] as $adminPrefix) {
            Route::get("{$adminPrefix}/state", AdminStateController::class)->middleware([
                EnsureAdminOrReseller::class,
                'admin.state.module',
                AdminDashboardRateLimit::class.':state',
            ]);
            Route::get("{$adminPrefix}/audit", [AuditController::class, 'index'])->middleware([EnsureAdminOrReseller::class, EnsureAdmin::class]);
            Route::get("{$adminPrefix}/logs", [LogsController::class, 'index'])->middleware([EnsureAdminOrReseller::class, EnsureAdmin::class]);
            Route::get("{$adminPrefix}/purge-expired", [PurgeExpiredController::class, 'index'])->middleware([EnsureAdminOrReseller::class, EnsureAdmin::class]);
            Route::get("{$adminPrefix}/users-bulk-jobs", [UsersBulkController::class, 'jobs'])
                ->middleware([EnsureAdminOrReseller::class, 'reseller.perm:users.bulk']);
            Route::get("{$adminPrefix}/users-bulk-job-items", [UsersBulkController::class, 'jobItems'])
                ->middleware([EnsureAdminOrReseller::class, 'reseller.perm:users.bulk']);
            Route::get("{$adminPrefix}/inbound-display-catalog", InboundDisplayCatalogController::class)->middleware(EnsureAdminOrReseller::class);
            Route::post("{$adminPrefix}/media", [MediaController::class, 'upload'])->middleware(EnsureAdminOrReseller::class);
            Route::get("{$adminPrefix}/user-search", [AdminUserController::class, 'search'])->middleware(EnsureAdminOrReseller::class);
            Route::get("{$adminPrefix}/user/{id}", [AdminUserController::class, 'show'])->middleware(EnsureAdminOrReseller::class)->whereNumber('id');
            Route::post("{$adminPrefix}/mutate", MutateController::class)->middleware([EnsureAdminOrReseller::class, AdminDashboardRateLimit::class.':mutate']);
            Route::post("{$adminPrefix}/impersonate/start", [ImpersonationController::class, 'start'])->middleware(EnsureAdmin::class);
            Route::post("{$adminPrefix}/impersonate/stop", [ImpersonationController::class, 'stop'])->middleware(EnsureAdmin::class);

            Route::middleware(['xui.module', EnsureAdminOrReseller::class, 'reseller.perm:services.manage'])->group(function () use ($adminPrefix) {
                Route::get("{$adminPrefix}/configs-snapshot", [ConfigsController::class, 'snapshot']);
                Route::get("{$adminPrefix}/configs-portal-payload", [ConfigsController::class, 'portalPayload']);
                Route::post("{$adminPrefix}/configs-sync", [ConfigsController::class, 'sync']);
                Route::get("{$adminPrefix}/panel-inbounds", [PanelController::class, 'inbounds']);
                Route::get("{$adminPrefix}/panel-inbound-clients", [PanelController::class, 'inboundClients']);
                Route::get("{$adminPrefix}/panel/inbound-map", [PanelController::class, 'inboundMapGet']);
                Route::post("{$adminPrefix}/panel/inbound-map", [PanelController::class, 'inboundMapSave']);
                Route::post("{$adminPrefix}/panel/rebuild-from-db", [PanelController::class, 'rebuildFromDb'])->middleware(EnsureAdmin::class);
                Route::post("{$adminPrefix}/panel/fix-51200-traffic", [PanelController::class, 'fix51200Traffic'])->middleware(EnsureAdmin::class);
            });

            Route::get("{$adminPrefix}/broadcast-queue", [BroadcastController::class, 'queue'])
                ->middleware(['marketing.module', EnsureAdminOrReseller::class, 'reseller.perm:broadcast.send']);

            Route::middleware([EnsureAdminOrReseller::class, EnsureAdmin::class, EnsureBackupModule::class])->group(function () use ($adminPrefix) {
                Route::get("{$adminPrefix}/backups", [BackupController::class, 'index']);
                Route::get("{$adminPrefix}/backup/status", [BackupController::class, 'status']);
                Route::post("{$adminPrefix}/backup/run", [BackupController::class, 'run']);
                Route::post("{$adminPrefix}/backup/reset-stuck", [BackupController::class, 'resetStuck']);
                Route::get("{$adminPrefix}/backup/download", [BackupController::class, 'download']);
                Route::post("{$adminPrefix}/backup/restore", [BackupController::class, 'restore']);
                Route::post("{$adminPrefix}/backup/restore-upload", [BackupController::class, 'restoreUpload']);
            });
        }
    });
});
