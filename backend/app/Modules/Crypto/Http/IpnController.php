<?php

namespace App\Modules\Crypto\Http;

use App\Http\Controllers\Controller;
use App\Modules\Crypto\Services\CryptoIpnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpnController extends Controller
{
    public function handle(Request $request, CryptoIpnService $ipn, string $secret): JsonResponse
    {
        if (! svp_modules()->isEnabled('crypto')) {
            return response()->json(svp_err('module_disabled'), 503);
        }

        $res = $ipn->handle($secret, $request->getContent(), $request->header('x-nowpayments-sig'));
        $status = (int) ($res['status'] ?? 200);
        if (! empty($res['error'])) {
            return response()->json(svp_err((string) $res['error']), $status > 0 ? $status : 403);
        }

        return response()->json(['ok' => true, 'message' => $res['message'] ?? null], 200);
    }
}
