<?php

namespace App\Modules\Relay\Http;

use App\Http\Controllers\Controller;
use App\Modules\Relay\Services\RelayAdminClient;
use App\Modules\Relay\Services\TelegramRelayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelayConfigController extends Controller
{
    public function __invoke(Request $request, TelegramRelayService $relay, RelayAdminClient $client): JsonResponse
    {
        $hdr = trim((string) $request->header('X-SVP-RELAY-SECRET', ''));
        $expected = $client->sharedSecret();
        if ($expected === '' || $hdr === '' || ! hash_equals($expected, $hdr)) {
            return response()->json(['ok' => false], 403);
        }

        return response()->json($relay->buildConfigSnapshot());
    }
}
