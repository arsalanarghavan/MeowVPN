<?php

namespace App\Modules\Core\Http;

use App\Http\Controllers\Controller;
use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Modules\Core\Services\Portal\PortalPermissionService;
use App\Modules\Core\Services\TelegramProfilePhotoService;
use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalTgAvatarController extends Controller
{
    public function __invoke(
        Request $request,
        PortalLinkService $portal,
        PortalPermissionService $perms,
        ResellerScopeService $scope,
        TelegramProfilePhotoService $photos,
    ): Response {
        $userId = (int) $request->query('svp_u', 0);
        $exp = (int) $request->query('svp_e', 0);
        $sig = (string) $request->query('svp_s', '');
        $targetUid = (int) $request->query('target_uid', 0);
        $avnonce = (string) $request->query('avnonce', '');

        $admin = $portal->verifyAdminSignature($userId, $exp, $sig);
        if (! $admin instanceof SvpUser || $targetUid < 1) {
            return response('', 403);
        }
        if (! $portal->verifyAvatarNonce($userId, $targetUid, $avnonce)) {
            return response('', 403);
        }
        $rid = $perms->resellerActorId($admin);
        if ($rid > 0 && ! in_array($targetUid, $scope->moderatableUserIds($rid), true)) {
            return response('', 403);
        }

        $row = SvpUser::query()->find($targetUid);
        if (! $row || (int) ($row->tg_user_id ?? 0) < 1) {
            return response('', 404);
        }

        $tmp = $photos->fetchJpegPath((int) $row->tg_user_id);
        if ($tmp === null || ! is_readable($tmp)) {
            return response('', 404);
        }

        $content = file_get_contents($tmp);
        @unlink($tmp);

        return response($content ?: '', 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
