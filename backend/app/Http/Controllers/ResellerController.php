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

    /**
     * Ensure the target is a reseller and the current user is allowed to access it.
     * Admin: any reseller; Reseller: only self. Otherwise 404 or 403.
     */
    private function ensureResellerAccess(Request $request, User $reseller): void
    {
        if ($reseller->role !== 'reseller') {
            abort(404);
        }

        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }
        if ($user->isReseller() && $reseller->id === $user->id) {
            return;
        }

        abort(403, 'Unauthorized. You may only access your own reseller profile.');
    }

    public function index()
    {
        return response()->json(User::where('role', 'reseller')->with('resellerProfile')->get());
    }

    public function show(Request $request, User $reseller)
    {
        $this->ensureResellerAccess($request, $reseller);
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
        $this->ensureResellerAccess($request, $reseller);

        $data = $request->validate([
            'credit_limit' => 'sometimes|numeric|min:0',
            'brand_name' => 'nullable|string',
            'contact_number' => 'nullable|string',
        ]);

        // Only admin may change credit_limit; resellers cannot set their own limit
        if (!$request->user()->isAdmin()) {
            $data = collect($data)->except('credit_limit')->all();
        }

        $reseller->update($data);
        if ($reseller->resellerProfile) {
            $reseller->resellerProfile->update(collect($data)->only(['brand_name', 'contact_number'])->all());
        }

        return response()->json($reseller);
    }

    public function users(Request $request, User $reseller)
    {
        $this->ensureResellerAccess($request, $reseller);

        // Return paginated response for consistency with bot expectations
        $users = $reseller->children()->with('subscriptions')->get();
        return response()->json([
            'data' => $users,
            'total' => $users->count(),
        ]);
    }

    public function invoices(Request $request, User $reseller)
    {
        $this->ensureResellerAccess($request, $reseller);
        return response()->json($reseller->invoices()->latest()->get());
    }

    public function payDebt(Request $request, User $reseller)
    {
        $this->ensureResellerAccess($request, $reseller);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $this->creditService->payDebt($reseller, $data['amount']);

        return response()->json(['message' => 'Debt paid successfully']);
    }
}

