<?php

namespace App\Modules\Crypto\Http;

use App\Http\Controllers\Controller;
use App\Modules\Crypto\Services\TetraPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TetraCallbackController extends Controller
{
    public function handle(Request $request, TetraPaymentService $tetra, string $secret): JsonResponse
    {
        if (! svp_modules()->isEnabled('crypto')) {
            return response()->json(svp_err('module_disabled'), 503);
        }

        return $tetra->handleCallback($request, $secret);
    }
}
