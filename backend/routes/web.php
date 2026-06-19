<?php

use App\Http\Controllers\DashboardShellController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/health/deep', [HealthController::class, 'deep'])->middleware('health.metrics.auth');
Route::get('/metrics', MetricsController::class)->middleware('health.metrics.auth');

Route::redirect('/', '/dashboard/');

Route::get('/dashboard/login', function () {
    return app(DashboardShellController::class)(request(), app(\App\Services\DashboardBootBuilder::class), 'login');
});

Route::get('/dashboard/{path?}', DashboardShellController::class)
    ->where('path', '.*');
