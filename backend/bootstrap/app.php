<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        then: function () {
            require __DIR__.'/../routes/internal-bot.php';
            require __DIR__.'/../routes/internal.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn () => '/fa/login');
        $middleware->alias([
            'dashboard.enabled' => \App\Http\Middleware\EnsureDashboardEnabled::class,
            'reseller.scope' => \App\Http\Middleware\ResellerScopeMiddleware::class,
            'relay.module' => \App\Http\Middleware\EnsureRelayModule::class,
            'xui.module' => \App\Http\Middleware\EnsureXuiPanelModule::class,
            'marketing.module' => \App\Http\Middleware\EnsureMarketingModule::class,
            'reseller.perm' => \App\Http\Middleware\EnsureResellerPermission::class,
            'webhook.drain.internal' => \App\Http\Middleware\EnsureInternalWebhookDrain::class,
            'l2tp.module' => \App\Http\Middleware\EnsureL2tpModule::class,
            'bot.module' => \App\Http\Middleware\EnsureTelegramOrBaleModule::class,
            'admin.state.module' => \App\Http\Middleware\EnsureAdminStateModule::class,
            'health.metrics.auth' => \App\Http\Middleware\HealthMetricsAuth::class,
            'bot.service.auth' => \App\Http\Middleware\BotServiceAuth::class,
            'internal.cron.secret' => \App\Http\Middleware\EnsureInternalCronSecret::class,
            'install.wizard.open' => \App\Http\Middleware\EnsureInstallWizardOpen::class,
            'install.wizard.complete' => \App\Http\Middleware\EnsureInstallWizardComplete::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\RedactSecretsInLogs::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
