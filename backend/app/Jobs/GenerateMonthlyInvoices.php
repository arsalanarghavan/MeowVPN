<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateMonthlyInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string Run on central node; use queue:work --queue=sync,default on central. */
    public string $queue = 'sync';

    public function handle(): void
    {
        try {
            $resellers = User::where('role', 'reseller')
                ->where('current_debt', '>', 0)
                ->get();

            $startDate = Carbon::now()->startOfMonth()->subMonth();
            $endDate = Carbon::now()->startOfMonth()->subDay();

            foreach ($resellers as $reseller) {
                // Check if invoice already exists for this period
                $existingInvoice = Invoice::where('reseller_id', $reseller->id)
                    ->where('start_date', $startDate->toDateString())
                    ->where('end_date', $endDate->toDateString())
                    ->first();

                if ($existingInvoice) {
                    continue;
                }

                Invoice::create([
                    'reseller_id' => $reseller->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_amount' => $reseller->current_debt,
                    'status' => 'unpaid',
                ]);

                Log::info("Monthly invoice generated for reseller {$reseller->id}");
            }

            Log::info('Monthly invoice generation completed successfully');
        } catch (\Exception $e) {
            Log::error('Monthly invoice generation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

