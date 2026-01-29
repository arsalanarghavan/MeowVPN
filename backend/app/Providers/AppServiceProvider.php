<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\Server;
use App\Models\Plan;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\PayoutRequest;
use App\Policies\SubscriptionPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\ServerPolicy;
use App\Policies\PlanPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\TicketPolicy;
use App\Policies\PayoutRequestPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Server::class, ServerPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(PayoutRequest::class, PayoutRequestPolicy::class);

        // Register role gate
        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('reseller', function ($user) {
            return $user->isReseller() || $user->isAdmin();
        });

        Gate::define('affiliate', function ($user) {
            return $user->isAffiliate() || $user->isAdmin();
        });
    }
}
