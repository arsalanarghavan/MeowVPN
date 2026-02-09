<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        // For admin users, return all plans; for others, only active plans
        if ($request->user() && $request->user()->role === 'admin') {
            return response()->json(Plan::all());
        }
        
        return response()->json(Plan::where('is_active', true)->get());
    }

    public function show(Request $request, Plan $plan)
    {
        // Non-admin may not view inactive plans
        if (!$request->user()->isAdmin() && !$plan->is_active) {
            abort(404);
        }
        return response()->json($plan);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Plan::class);
        $data = $request->validate([
            'name' => 'required|string',
            'price_base' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'traffic_bytes' => 'required|integer|min:0',
            'max_concurrent_users' => 'required|integer|min:1',
            'max_devices' => 'nullable|integer|min:1|max:10',
            'description' => 'nullable|string',
        ]);

        // Set default max_devices if not provided
        if (!isset($data['max_devices'])) {
            $data['max_devices'] = 1;
        }

        return response()->json(Plan::create($data), 201);
    }

    public function update(Request $request, Plan $plan)
    {
        $this->authorize('update', $plan);
        $data = $request->validate([
            'name' => 'sometimes|string',
            'price_base' => 'sometimes|numeric|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'traffic_bytes' => 'sometimes|integer|min:0',
            'max_concurrent_users' => 'sometimes|integer|min:1',
            'max_devices' => 'sometimes|integer|min:1|max:10',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $plan->update($data);
        return response()->json($plan);
    }

    public function destroy(Plan $plan)
    {
        $this->authorize('delete', $plan);
        $plan->delete();
        return response()->json(['message' => 'Plan deleted']);
    }
}

