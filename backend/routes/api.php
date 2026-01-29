<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/health', [HealthCheckController::class, 'check']);
Route::get('/setup/status', [SetupController::class, 'status']);

// Telegram webhook (with security middleware)
Route::post('/telegram/webhook', function (Request $request) {
    // Telegram webhook handler will be implemented in TelegramBotController
    return response()->json(['ok' => true]);
})->middleware('telegram.webhook');

// Setup routes (only available before setup is complete)
Route::prefix('setup')->middleware('setup.incomplete')->group(function () {
    Route::post('/database', [SetupController::class, 'testDatabase']);
    Route::post('/redis', [SetupController::class, 'testRedis']);
    Route::post('/database/save', [SetupController::class, 'saveDatabase']);
    Route::post('/redis/save', [SetupController::class, 'saveRedis']);
    Route::post('/domains', [SetupController::class, 'saveDomains']);
    Route::post('/ssl', [SetupController::class, 'installSSL']);
    Route::post('/admin', [SetupController::class, 'createAdmin']);
    Route::post('/bot', [SetupController::class, 'saveBotConfig']);
    Route::post('/complete', [SetupController::class, 'complete']);
});

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
});

// Subscription link endpoint (public, for v2ray clients)
Route::get('/sub/{uuid}', [SubscriptionController::class, 'getSubscriptionLink']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Users
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('role:admin');
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('role:admin');
    });

    // Servers
    Route::prefix('servers')->group(function () {
        Route::get('/', [ServerController::class, 'index']);
        Route::get('/available', [ServerController::class, 'available']);
        Route::get('/monitoring', [ServerController::class, 'monitoring'])->middleware('role:admin');
        Route::get('/panel-types', [ServerController::class, 'panelTypes']);
        Route::get('/{server}', [ServerController::class, 'show']);
        Route::post('/', [ServerController::class, 'store'])->middleware('role:admin');
        Route::put('/{server}', [ServerController::class, 'update'])->middleware('role:admin');
        Route::delete('/{server}', [ServerController::class, 'destroy'])->middleware('role:admin');
        Route::get('/{server}/health', [ServerController::class, 'health']);
        Route::post('/{server}/test-connection', [ServerController::class, 'testConnection'])->middleware('role:admin');
        Route::get('/{server}/inbounds', [ServerController::class, 'inbounds'])->middleware('role:admin');
        Route::get('/{server}/users', [ServerController::class, 'users'])->middleware('role:admin');
        Route::post('/{server}/sync-user-count', [ServerController::class, 'syncUserCount'])->middleware('role:admin');
    });

    // Plans
    Route::prefix('plans')->group(function () {
        Route::get('/', [PlanController::class, 'index']);
        Route::get('/{plan}', [PlanController::class, 'show']);
        Route::post('/', [PlanController::class, 'store'])->middleware('role:admin');
        Route::put('/{plan}', [PlanController::class, 'update'])->middleware('role:admin');
        Route::delete('/{plan}', [PlanController::class, 'destroy'])->middleware('role:admin');
    });

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('/{subscription}', [SubscriptionController::class, 'show']);
        Route::post('/', [SubscriptionController::class, 'store']);
        Route::post('/{subscription}/renew', [SubscriptionController::class, 'renew']);
        Route::post('/{subscription}/change-location', [SubscriptionController::class, 'changeLocation']);
        Route::post('/{subscription}/sync', [SubscriptionController::class, 'sync']);
        Route::get('/{subscription}/qr', [SubscriptionController::class, 'getQR']);
        Route::delete('/{subscription}', [SubscriptionController::class, 'destroy']);
        Route::post('/{subscription}/enable', [SubscriptionController::class, 'enable']);
        Route::post('/{subscription}/disable', [SubscriptionController::class, 'disable']);
        Route::put('/{subscription}/max-devices', [SubscriptionController::class, 'updateMaxDevices']);
    });

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/pending', [TransactionController::class, 'pending'])->middleware('role:admin');
        Route::get('/stats', [TransactionController::class, 'stats'])->middleware('role:admin');
        Route::get('/{transaction}', [TransactionController::class, 'show']);
        Route::post('/deposit', [TransactionController::class, 'deposit']);
        Route::post('/{transaction}/approve', [TransactionController::class, 'approve'])->middleware('role:admin');
        Route::post('/{transaction}/reject', [TransactionController::class, 'reject'])->middleware('role:admin');
    });

    // Resellers
    Route::prefix('resellers')->group(function () {
        Route::get('/', [ResellerController::class, 'index'])->middleware('role:admin');
        Route::get('/{reseller}', [ResellerController::class, 'show']);
        Route::post('/', [ResellerController::class, 'store'])->middleware('role:admin');
        Route::put('/{reseller}', [ResellerController::class, 'update'])->middleware('role:admin');
        Route::get('/{reseller}/users', [ResellerController::class, 'users']);
        Route::get('/{reseller}/invoices', [ResellerController::class, 'invoices']);
        Route::post('/{reseller}/pay-debt', [ResellerController::class, 'payDebt']);
    });

    // Affiliates
    Route::prefix('affiliates')->group(function () {
        Route::get('/stats', [AffiliateController::class, 'stats']);
        Route::get('/link', [AffiliateController::class, 'getLink']);
        Route::post('/payout-request', [AffiliateController::class, 'requestPayout']);
        Route::get('/my-payouts', [AffiliateController::class, 'myPayouts']);
        Route::get('/payouts', [AffiliateController::class, 'payouts'])->middleware('role:admin');
        Route::get('/payouts/pending', [AffiliateController::class, 'pendingPayouts'])->middleware('role:admin');
        Route::post('/payouts/{payoutRequest}/approve', [AffiliateController::class, 'approvePayout'])->middleware('role:admin');
        Route::post('/payouts/{payoutRequest}/reject', [AffiliateController::class, 'rejectPayout'])->middleware('role:admin');
    });

    // Dashboard stats (admin only)
    Route::prefix('dashboard')->middleware('role:admin')->group(function () {
        Route::get('/stats', [\App\Http\Controllers\DashboardController::class, 'stats']);
        Route::get('/sales', [\App\Http\Controllers\DashboardController::class, 'sales']);
        Route::get('/recent-transactions', [\App\Http\Controllers\DashboardController::class, 'recentTransactions']);
    });

    // Tickets (support system)
    Route::prefix('tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index']);
        Route::get('/stats', [TicketController::class, 'stats'])->middleware('role:admin');
        Route::get('/{ticket}', [TicketController::class, 'show']);
        Route::post('/', [TicketController::class, 'store']);
        Route::post('/{ticket}/reply', [TicketController::class, 'reply']);
        Route::post('/{ticket}/close', [TicketController::class, 'close']);
        Route::post('/{ticket}/reopen', [TicketController::class, 'reopen'])->middleware('role:admin');
        Route::post('/{ticket}/assign', [TicketController::class, 'assign'])->middleware('role:admin');
        Route::post('/{ticket}/priority', [TicketController::class, 'updatePriority'])->middleware('role:admin');
    });

    // Invoices
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/stats', [InvoiceController::class, 'stats'])->middleware('role:admin');
        Route::get('/{invoice}', [InvoiceController::class, 'show']);
        Route::post('/{invoice}/generate', [InvoiceController::class, 'generatePdf']);
        Route::get('/{invoice}/download', [InvoiceController::class, 'download']);
        Route::post('/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->middleware('role:admin');
    });

    // Transaction receipts
    Route::get('/transactions/{transaction}/receipt', [InvoiceController::class, 'receipt']);
});

// Payment callbacks (public) - supports both GET and POST for different gateways
Route::prefix('payments')->group(function () {
    Route::match(['get', 'post'], '/callback', [TransactionController::class, 'callback']);
});

