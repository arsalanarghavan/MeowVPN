<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MutationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MutateController extends Controller
{
    public function __invoke(Request $request, MutationDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'op' => ['required', 'string', 'max:64'],
        ]);

        $payload = $request->except(['op']);
        $out = $dispatcher->dispatch($data['op'], $payload, $request->user());

        return response()->json($out['result'], $out['http_status']);
    }
}
