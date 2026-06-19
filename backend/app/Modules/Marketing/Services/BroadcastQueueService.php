<?php

namespace App\Modules\Marketing\Services;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Support\Facades\DB;

class BroadcastQueueService
{
    public function __construct(
        protected BroadcastFormatService $format,
        protected ResellerScopeService $scope,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function createAndEnqueue(array $payload, ?DashboardUser $actor): array
    {
        $owner = (int) ($payload['owner_svp_user_id'] ?? $actor?->svp_user_id ?? 0);
        if ($actor?->role === 'reseller') {
            $owner = (int) ($actor->svp_user_id ?? 0);
        }

        $targets = (string) ($payload['bc_targets'] ?? 'both');
        if (! in_array($targets, ['both', 'telegram', 'bale'], true)) {
            $targets = 'both';
        }

        $urls = $this->collectMediaUrls($payload);
        $textRaw = str_replace("\0", '', (string) ($payload['bc_text'] ?? ''));
        $textTrim = trim($textRaw);
        if ($textTrim === '' && $urls === []) {
            return svp_err('empty');
        }

        $textSafe = $this->format->sanitizeComposeHtml($textTrim);
        $textSafe = mb_substr($textSafe, 0, $urls !== [] ? 1024 : 4096);

        $type = count($urls) >= 2 ? 'album' : ($urls !== [] ? 'photo' : 'text');
        $content = json_encode([
            'text' => $textSafe,
            'parse_mode' => 'HTML',
            'photo' => count($urls) === 1 ? $urls[0] : '',
            'media_urls' => $urls,
            'targets' => $targets,
        ], JSON_UNESCAPED_UNICODE);

        $meta = json_encode([
            'targets' => $targets,
            'parse_mode' => 'HTML',
            'has_photo' => $urls !== [],
            'media_count' => count($urls),
        ], JSON_UNESCAPED_UNICODE);

        $bid = (int) DB::table('svp_broadcasts')->insertGetId([
            'owner_svp_user_id' => $owner,
            'type' => $type,
            'content' => $content,
            'status' => 'sending',
            'meta_json' => $meta,
            'total_targets' => 0,
            'blocked_count' => 0,
            'created_at' => now(),
        ]);

        $includeTg = in_array($targets, ['both', 'telegram'], true);
        $includeBl = in_array($targets, ['both', 'bale'], true);
        if (! $includeTg && ! $includeBl) {
            return svp_err('platform_disabled');
        }

        $users = $this->resolveRecipients($owner);
        $basePayload = [
            'text' => $textSafe,
            'parse_mode' => 'HTML',
            'media_urls' => $urls,
        ];
        if (count($urls) === 1) {
            $basePayload['photo'] = $urls[0];
        }

        $rows = [];
        foreach ($users as $user) {
            if ($includeTg && (int) ($user->tg_user_id ?? 0) > 0) {
                $rows[] = [
                    'broadcast_id' => $bid,
                    'user_id' => $user->id,
                    'bot' => 'tg',
                    'chat_id' => (int) $user->tg_user_id,
                    'payload_json' => json_encode(array_merge($basePayload, [
                        'chat_id' => (int) $user->tg_user_id,
                    ]), JSON_UNESCAPED_UNICODE),
                    'status' => 'pending',
                    'tries' => 0,
                    'updated_at' => now(),
                ];
            }
            if ($includeBl && (int) ($user->bale_user_id ?? 0) > 0) {
                $rows[] = [
                    'broadcast_id' => $bid,
                    'user_id' => $user->id,
                    'bot' => 'bale',
                    'chat_id' => (int) $user->bale_user_id,
                    'payload_json' => json_encode(array_merge($basePayload, [
                        'chat_id' => (int) $user->bale_user_id,
                    ]), JSON_UNESCAPED_UNICODE),
                    'status' => 'pending',
                    'tries' => 0,
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('svp_broadcast_queue')->insert($chunk);
        }

        $distinctUsers = count(array_unique(array_column($rows, 'user_id')));
        DB::table('svp_broadcasts')->where('id', $bid)->update([
            'total_targets' => $distinctUsers,
            'status' => $rows === [] ? 'done' : 'sending',
        ]);

        return svp_ok(['broadcast_id' => $bid, 'queued' => count($rows), 'total_targets' => $distinctUsers]);
    }

    public function cancelBroadcast(int $broadcastId, int $actorOwner): array
    {
        $row = DB::table('svp_broadcasts')->where('id', $broadcastId)->first();
        if (! $row) {
            return svp_err('not_found');
        }
        if ($actorOwner > 0 && (int) $row->owner_svp_user_id !== $actorOwner) {
            return svp_err('forbidden');
        }
        $st = (string) $row->status;
        if (in_array($st, ['done', 'cancelled'], true)) {
            return svp_err('not_cancellable');
        }

        DB::table('svp_broadcasts')->where('id', $broadcastId)->update(['status' => 'cancelled']);
        DB::table('svp_broadcast_queue')
            ->where('broadcast_id', $broadcastId)
            ->whereIn('status', ['pending', 'sending'])
            ->update([
                'status' => 'failed',
                'failure_kind' => 'cancelled',
                'last_error' => 'admin_cancelled',
                'updated_at' => now(),
            ]);

        return svp_ok();
    }

    /** @return array<int, object> */
    public function popBatch(int $limit): array
    {
        $limit = max(1, $limit);
        $token = 'c_'.bin2hex(random_bytes(8));

        DB::table('svp_broadcast_queue')
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->update(['status' => $token]);

        $rows = DB::table('svp_broadcast_queue')
            ->where('status', $token)
            ->orderBy('id')
            ->get()
            ->all();

        if ($rows === []) {
            return [];
        }

        DB::table('svp_broadcast_queue')
            ->where('status', $token)
            ->update(['status' => 'sending', 'updated_at' => now()]);

        return $rows;
    }

    public function reclaimStuck(int $olderThanSeconds): int
    {
        $sec = max(60, $olderThanSeconds);
        $cutoff = now()->subSeconds($sec);

        return DB::table('svp_broadcast_queue')
            ->where('status', 'sending')
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => 'pending', 'updated_at' => now()]);
    }

    public function maybeMarkBroadcastDone(int $broadcastId): void
    {
        $brow = DB::table('svp_broadcasts')->where('id', $broadcastId)->first();
        if ($brow && (string) $brow->status === 'cancelled') {
            return;
        }

        $pending = DB::table('svp_broadcast_queue')
            ->where('broadcast_id', $broadcastId)
            ->whereIn('status', ['pending', 'sending'])
            ->count();

        if ($pending > 0) {
            return;
        }

        DB::table('svp_broadcasts')->where('id', $broadcastId)->update(['status' => 'done']);
    }

    /** @return array{total:int, page:int, perPage:int, users: array<int, array<string, mixed>>} */
    public function listQueueUsersPage(int $broadcastId, int $page, int $perPage): array
    {
        $per = max(1, min(50, $perPage));
        $pageN = max(1, $page);
        $offset = ($pageN - 1) * $per;

        $total = (int) DB::table('svp_broadcast_queue')
            ->where('broadcast_id', $broadcastId)
            ->distinct()
            ->count('user_id');

        $uids = DB::table('svp_broadcast_queue')
            ->where('broadcast_id', $broadcastId)
            ->distinct()
            ->orderBy('user_id')
            ->offset($offset)
            ->limit($per)
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($uids === []) {
            return ['total' => $total, 'page' => $pageN, 'perPage' => $per, 'users' => []];
        }

        $rows = DB::table('svp_broadcast_queue as q')
            ->leftJoin('svp_users as u', 'u.id', '=', 'q.user_id')
            ->where('q.broadcast_id', $broadcastId)
            ->whereIn('q.user_id', $uids)
            ->orderBy('q.user_id')
            ->orderBy('q.bot')
            ->get([
                'q.id as q_id',
                'q.user_id as uid',
                'q.bot',
                'q.status',
                'q.tries',
                'q.failure_kind',
                'q.last_error',
                'u.first_name',
                'u.last_name',
                'u.username',
            ]);

        $byUser = [];
        foreach ($rows as $r) {
            $uid = (int) $r->uid;
            if ($uid < 1) {
                continue;
            }
            if (! isset($byUser[$uid])) {
                $fn = (string) ($r->first_name ?? '');
                $ln = (string) ($r->last_name ?? '');
                $un = (string) ($r->username ?? '');
                $dn = trim("{$fn} {$ln}");
                if ($dn === '' && $un !== '') {
                    $dn = $un;
                }
                if ($dn === '') {
                    $dn = '#'.$uid;
                }
                $byUser[$uid] = [
                    'userId' => $uid,
                    'displayName' => $dn,
                    'username' => $un,
                    'rows' => [],
                ];
            }
            $byUser[$uid]['rows'][] = [
                'id' => (int) $r->q_id,
                'bot' => (string) $r->bot,
                'status' => (string) $r->status,
                'failureKind' => $r->failure_kind !== null ? (string) $r->failure_kind : '',
                'lastError' => $r->last_error !== null ? (string) $r->last_error : '',
                'tries' => (int) $r->tries,
            ];
        }

        return [
            'total' => $total,
            'page' => $pageN,
            'perPage' => $per,
            'users' => array_values($byUser),
        ];
    }

    /** @return array<int, SvpUser> */
    protected function resolveRecipients(int $owner): array
    {
        $q = SvpUser::query()->where('status', 'approved')->where('role', '!=', 'reseller');
        if ($owner > 0) {
            $ids = $this->scope->moderatableUserIds($owner);
            $q->whereIn('id', $ids);
        }

        return $q->get()->all();
    }

    /** @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function collectMediaUrls(array $payload): array
    {
        $urls = [];
        if (! empty($payload['bc_media_urls']) && is_array($payload['bc_media_urls'])) {
            foreach ($payload['bc_media_urls'] as $u) {
                if (count($urls) >= 10) {
                    break;
                }
                $u = trim((string) $u);
                if ($u !== '' && filter_var($u, FILTER_VALIDATE_URL)) {
                    $urls[] = $u;
                }
            }
        }
        $legacy = trim((string) ($payload['bc_photo_url'] ?? ''));
        if ($legacy !== '' && filter_var($legacy, FILTER_VALIDATE_URL) && ! in_array($legacy, $urls, true)) {
            $urls[] = $legacy;
        }

        return array_values(array_unique($urls));
    }
}
