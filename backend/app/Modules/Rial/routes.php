<?php

use App\Modules\Rial\Http\CallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    Route::get('zarinpal-callback/{secret}', [CallbackController::class, 'zarinpal']);
    Route::get('zibal-callback/{secret}', [CallbackController::class, 'zibal']);
    Route::get('aqayepardakht-callback/{secret}', [CallbackController::class, 'aqayepardakht']);
});
