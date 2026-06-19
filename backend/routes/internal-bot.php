<?php

use App\Http\Controllers\Internal\InternalBotController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/internal/bot')
    ->middleware('bot.service.auth')
    ->group(function () {
        Route::get('health', [InternalBotController::class, 'health']);
        Route::post('process-update', [InternalBotController::class, 'processUpdate']);
        Route::post('user/resolve', [InternalBotController::class, 'resolveUser']);
        Route::match(['get', 'post'], 'user/{userId}/state', [InternalBotController::class, 'userState'])->whereNumber('userId');
        Route::post('mutate', [InternalBotController::class, 'mutate']);
        Route::get('texts', [InternalBotController::class, 'texts']);
        Route::get('settings', [InternalBotController::class, 'settings']);
        Route::get('reseller/{resellerId}/profile', [InternalBotController::class, 'resellerProfile'])->whereNumber('resellerId');
    });
