<?php

namespace App\Modules\Marketing\Http;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use App\Modules\Marketing\Services\BroadcastQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BroadcastController extends Controller
{
    public function queue(Request $request, BroadcastQueueService $queue): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof DashboardUser) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $bid = (int) $request->query('broadcast_id', 0);
        if ($bid < 1) {
            return response()->json(['ok' => false, 'message' => 'invalid_broadcast'], 400);
        }

        $brow = DB::table('svp_broadcasts')->where('id', $bid)->first();
        if (! $brow) {
            return response()->json(['ok' => false, 'message' => 'not_found'], 404);
        }

        if ($actor->role === 'reseller') {
            $perms = is_array($actor->permissions_json) ? $actor->permissions_json : [];
            if (empty($perms['broadcast.send'])) {
                return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
            }
            $owner = (int) ($brow->owner_svp_user_id ?? 0);
            if ($owner !== (int) ($actor->svp_user_id ?? 0)) {
                return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
            }
        }

        $page = max(1, (int) $request->query('page', 1));
        $per = (int) $request->query('per_page', 25);
        $data = $queue->listQueueUsersPage($bid, $page, $per);

        return response()->json([
            'ok' => true,
            'pagination' => [
                'page' => $data['page'],
                'perPage' => $data['perPage'],
                'total' => $data['total'],
            ],
            'users' => $data['users'],
        ]);
    }
}
