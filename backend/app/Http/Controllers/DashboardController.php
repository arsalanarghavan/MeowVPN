<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'today_sales' => Transaction::where('type', 'purchase')
                ->where('status', 'completed')
                ->whereDate('created_at', today())
                ->sum('amount'),
            'monthly_sales' => Transaction::where('type', 'purchase')
                ->where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->sum('amount'),
        ];

        return response()->json($stats);
    }

    public function sales(Request $request)
    {
        $period = $request->get('period', 'month'); // day, week, month

        $query = Transaction::where('type', 'purchase')
            ->where('status', 'completed');

        if ($period === 'day') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } else {
            $query->whereMonth('created_at', now()->month);
        }

        $sales = $query->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($sales);
    }

    public function recentTransactions()
    {
        $transactions = Transaction::with('user')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json($transactions);
    }
}

