<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\ResellerCreditService;

class ResellerController extends Controller
{
    public function __construct(
        private ResellerCreditService $creditService
    ) {}

    public function index()
    {
        return response()->json(User::where('role', 'reseller')->with('resellerProfile')->get());
    }

    public function show(User $reseller)
    {
        return response()->json($reseller->load(['resellerProfile', 'children', 'invoices']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'credit_limit' => 'required|numeric|min:0',
            'brand_name' => 'nullable|string',
            'contact_number' => 'nullable|string',
        ]);

        $reseller = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'role' => 'reseller',
            'credit_limit' => $data['credit_limit'],
        ]);

        $reseller->resellerProfile()->create([
            'brand_name' => $data['brand_name'] ?? null,
            'contact_number' => $data['contact_number'] ?? null,
        ]);

        return response()->json($reseller, 201);
    }

    public function update(Request $request, User $reseller)
    {
        $data = $request->validate([
            'credit_limit' => 'sometimes|numeric|min:0',
            'brand_name' => 'nullable|string',
            'contact_number' => 'nullable|string',
        ]);

        $reseller->update($data);
        if ($reseller->resellerProfile) {
            $reseller->resellerProfile->update($data);
        }

        return response()->json($reseller);
    }

    public function users(User $reseller)
    {
        // Return paginated response for consistency with bot expectations
        $users = $reseller->children()->with('subscriptions')->get();
        return response()->json([
            'data' => $users,
            'total' => $users->count(),
        ]);
    }

    public function invoices(User $reseller)
    {
        return response()->json($reseller->invoices()->latest()->get());
    }

    public function payDebt(Request $request, User $reseller)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $this->creditService->payDebt($reseller, $data['amount']);

        return response()->json(['message' => 'Debt paid successfully']);
    }
}

