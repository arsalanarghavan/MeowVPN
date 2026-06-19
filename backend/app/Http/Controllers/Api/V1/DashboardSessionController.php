<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use App\Services\DashboardBootBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSessionController extends Controller
{
    public function meState(Request $request, DashboardBootBuilder $boot): JsonResponse
    {
        /** @var DashboardUser|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(svp_ok(['loggedIn' => false]));
        }

        $payload = $boot->bootstrapApiPayload($user);
        if (($payload['activePersona'] ?? '') === 'user') {
            $payload = array_intersect_key($payload, array_flip([
                'restUrl', 'locale', 'lang', 'isRtl', 'isLoggedIn', 'user', 'activePersona',
                'availablePersonas', 'dashboardUrl', 'siteName', 'siteIconUrl', 'features', 'branding',
            ]));
        }

        return response()->json(svp_ok($payload));
    }

    public function setPersona(Request $request): JsonResponse
    {
        /** @var DashboardUser|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(svp_err('forbidden'), 403);
        }

        $persona = (string) $request->input('persona', '');
        if (! in_array($persona, ['admin', 'reseller', 'user'], true)) {
            return response()->json(svp_err('invalid'), 400);
        }

        $request->session()->put('svp_active_persona', $persona);

        return response()->json(svp_ok(['persona' => $persona]));
    }

    public function uiPreferences(Request $request): JsonResponse
    {
        /** @var DashboardUser|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(svp_err('forbidden'), 403);
        }

        foreach (['ui_accent', 'ui_theme', 'ui_sidebar', 'ui_lang'] as $key) {
            if ($request->has($key)) {
                $user->{$key} = (string) $request->input($key);
            }
        }
        $user->save();

        return response()->json(svp_ok());
    }
}
