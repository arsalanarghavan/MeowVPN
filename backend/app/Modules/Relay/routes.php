<?php

use App\Modules\Relay\Http\RelayConfigController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('relay.module')->group(function () {
    Route::get('relay/config', RelayConfigController::class);
});
