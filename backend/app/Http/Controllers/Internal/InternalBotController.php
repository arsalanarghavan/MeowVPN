<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Jobs\ProcessInboundUpdateJob;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotCommerceCheckoutService;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Bot\Services\UserResolver;
use App\Modules\Reseller\Services\ResellerBotProfileService;
use App\Services\Bot\InboundQueueService;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalBotController extends Controller
{
    public function __construct(
        protected UserResolver $users,
        protected BotAdminMutateService $mutate,
        protected BotCommerceCheckoutService $commerce,
        protected TextService $texts,
        protected SettingsStore $settings,
        protected ResellerBotProfileService $profiles,
        protected InboundQueueService $inboundQueue,
    ) {}

    public function processUpdate(Request $request): JsonResponse
    {
        $platform = $this->platform($request);
        if ($platform === null) {
            return response()->json(['ok' => false, 'message' => 'invalid_platform'], 400);
        }

        $update = $request->json()->all();
        if ($update === []) {
            $update = json_decode($request->getContent(), true);
        }
        if (! is_array($update)) {
            $update = [];
        }

        $resellerId = max(0, (int) ($update['reseller_svp_user_id'] ?? $request->input('reseller_svp_user_id', 0)));
        $async = filter_var($update['async'] ?? $request->input('async', true), FILTER_VALIDATE_BOOL);
        unset($update['reseller_svp_user_id'], $update['async'], $update['platform']);

        if ($update === []) {
            return response()->json(['ok' => true]);
        }

        if ($async) {
            $this->inboundQueue->enqueue($platform, $update, $resellerId);
            $this->inboundQueue->kickAsyncDrain();

            return response()->json(['ok' => true, 'queued' => true]);
        }

        ProcessInboundUpdateJob::dispatchSync($platform, $update, $resellerId);

        return response()->json(['ok' => true, 'processed' => true]);
    }

    public function resolveUser(Request $request): JsonResponse
    {
        $platform = $this->platform($request);
        if ($platform === null) {
            return response()->json(['ok' => false, 'message' => 'invalid_platform'], 400);
        }

        $from = $request->input('from', []);
        if (! is_array($from)) {
            return response()->json(['ok' => false, 'message' => 'invalid_request'], 400);
        }

        $ctx = $this->context($request, $platform);
        $create = filter_var($request->input('create', false), FILTER_VALIDATE_BOOL);
        $startText = (string) $request->input('start_text', '');

        if ($create) {
            $user = $this->users->findOrCreateFromStart($ctx, $from, $startText);
        } else {
            $user = $this->users->resolve($ctx, $from);
            if (! $user) {
                return response()->json(['ok' => false, 'message' => 'not_found'], 404);
            }
        }

        return response()->json(['ok' => true, 'user' => $this->userPayload($user)]);
    }

    public function userState(Request $request, int $userId): JsonResponse
    {
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'not_found'], 404);
        }

        if ($request->isMethod('get')) {
            return response()->json([
                'ok' => true,
                'state' => $user->state,
                'state_data' => $user->state_data,
                'status' => $user->status,
            ]);
        }

        $data = [];
        if ($request->has('state')) {
            $data['state'] = $request->input('state');
        }
        if ($request->has('state_data')) {
            $data['state_data'] = $request->input('state_data');
        }
        if ($data !== []) {
            $user->update($data);
        }

        return response()->json(['ok' => true]);
    }

    public function mutate(Request $request): JsonResponse
    {
        $actorId = (int) $request->input('actor_svp_user_id', 0);
        $op = (string) $request->input('op', '');
        $params = $request->input('params', []);
        if (! is_array($params)) {
            $params = [];
        }

        $actor = SvpUser::query()->find($actorId);
        if (! $actor) {
            return response()->json(['ok' => false, 'message' => 'actor_not_found'], 404);
        }

        $result = $this->mutate->applyForUser($actor, $op, $params);

        return response()->json($result);
    }

    public function texts(Request $request): JsonResponse
    {
        $keys = $request->input('keys', []);
        if (! is_array($keys) || $keys === []) {
            return response()->json(['ok' => true, 'texts' => []]);
        }

        $locale = (string) $request->input('locale', 'fa');
        $out = [];
        foreach ($keys as $key) {
            $out[(string) $key] = $this->texts->get((string) $key, $locale);
        }

        return response()->json(['ok' => true, 'texts' => $out]);
    }

    public function settings(Request $request): JsonResponse
    {
        $keys = $request->input('keys', []);
        if (! is_array($keys) || $keys === []) {
            return response()->json(['ok' => true, 'settings' => []]);
        }

        $out = [];
        foreach ($keys as $key) {
            $out[(string) $key] = $this->settings->get((string) $key);
        }

        return response()->json(['ok' => true, 'settings' => $out]);
    }

    public function resellerProfile(Request $request, int $resellerId): JsonResponse
    {
        if ($resellerId < 1) {
            return response()->json(['ok' => false, 'message' => 'invalid_request'], 400);
        }

        $profile = $this->profiles->profileArrayForRuntime($resellerId);

        return response()->json(['ok' => true, 'profile' => $profile]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['ok' => true, 'service' => 'internal-bot']);
    }

    protected function platform(Request $request): ?string
    {
        $platform = strtolower((string) $request->header('X-SVP-Platform', $request->input('platform', '')));

        return in_array($platform, ['telegram', 'bale'], true) ? $platform : null;
    }

    protected function context(Request $request, string $platform): BotContext
    {
        $resellerId = max(0, (int) $request->input('reseller_svp_user_id', 0));
        $profile = $resellerId > 0 ? $this->profiles->profileArrayForRuntime($resellerId) : null;

        return new BotContext(
            platform: $platform,
            resellerSvpUserId: $resellerId,
            resellerProfile: $profile,
        );
    }

    /** @return array<string, mixed> */
    protected function userPayload(SvpUser $user): array
    {
        return [
            'id' => (int) $user->id,
            'tg_user_id' => $user->tg_user_id,
            'bale_user_id' => $user->bale_user_id,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->role,
            'status' => $user->status,
            'balance' => (float) $user->balance,
            'state' => $user->state,
            'state_data' => $user->state_data,
            'admin_mode' => (bool) $user->admin_mode,
            'bot_locale' => $user->bot_locale,
        ];
    }
}
