<?php

namespace App\Modules\Core\Http;

use App\Http\Controllers\Controller;
use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalAdminController extends Controller
{
    public function __invoke(Request $request, PortalAdminService $portal): JsonResponse
    {
        /** @var SvpUser|null $admin */
        $admin = $request->attributes->get('portal_admin_user');
        if (! $admin instanceof SvpUser) {
            return $this->portalError(['message' => 'forbidden'], 403);
        }

        $op = (string) $request->input('op', '');
        $result = $portal->handle($op, $request->all(), $admin);

        return $this->portalJson($result);
    }

    /** @param  array<string, mixed>  $result */
    protected function portalJson(array $result): JsonResponse
    {
        $ok = ! empty($result['ok']);
        if ($ok) {
            unset($result['ok']);

            return response()->json(['success' => true, 'data' => $result]);
        }

        $message = (string) ($result['message'] ?? 'error');
        unset($result['ok'], $result['message']);
        $data = array_merge(['message' => $message], $result);
        $status = match ($message) {
            'forbidden', 'forbidden_perm', 'forbidden_scope' => 403,
            'no_user', 'not_found' => 404,
            default => 400,
        };

        return response()->json(['success' => false, 'data' => $data], $status);
    }

    /** @param  array<string, mixed>  $data */
    protected function portalError(array $data, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'data' => $data], $status);
    }
}
