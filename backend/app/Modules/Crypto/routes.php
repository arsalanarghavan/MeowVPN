<?php

use App\Modules\Crypto\Http\IpnController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    // Spec §13.4 param name `path_secret`; Laravel binds as `{secret}` (same URI).
    Route::post('crypto-ipn/{secret}', [IpnController::class, 'handle']);
});
