<?php

use App\Http\Middleware\PortalSignatureMiddleware;
use App\Http\Middleware\WebhookRateLimit;
use App\Modules\Core\Http\PortalAdminController;
use App\Modules\Core\Http\PortalSubscriptionController;
use App\Modules\Core\Http\PortalTgAvatarController;
use App\Modules\Core\Http\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/sub/{token?}', PortalSubscriptionController::class)->name('svp.sub.portal');
Route::get('/info', PortalSubscriptionController::class);

Route::prefix('api/v1')->group(function () {
    Route::post('portal/admin', PortalAdminController::class)->middleware(PortalSignatureMiddleware::class);
    Route::get('portal/tg-avatar', PortalTgAvatarController::class);
    Route::post('webhook/{platform}/{secret}', [WebhookController::class, 'platform'])
        ->middleware(WebhookRateLimit::class.':ip');
    Route::post('webhook/{platform}/reseller/{resellerId}/{secret}', [WebhookController::class, 'reseller'])
        ->whereNumber('resellerId')
        ->middleware([WebhookRateLimit::class.':ip', WebhookRateLimit::class.':reseller']);
    Route::post('webhook-queue/drain', [WebhookController::class, 'drain'])
        ->middleware('webhook.drain.internal');
});
