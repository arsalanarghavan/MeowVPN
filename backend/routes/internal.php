<?php

use App\Http\Controllers\Internal\InternalSessionKeeperController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    Route::post('internal/session-keeper', InternalSessionKeeperController::class)
        ->middleware('internal.cron.secret');
});
