<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Clients\BaleApiClient;
use App\Modules\Core\Bot\Clients\TelegramApiClient;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BroadcastWorkerService
{
    public function __construct(
        protected BroadcastQueueService $queue,
        protected BroadcastFormatService $format,
        protected BotRuntime $runtime,
        protected SettingsStore $settings,
    ) {}

    public function runBatch(): void
    {
        $reclaim = max(120, (int) $this->settings->get('broadcast_sending_timeout_sec', 600));
        $this->queue->reclaimStuck($reclaim);

        $batch = max(5, min(80, (int) $this->settings->get('broadcast_batch_size', 20)));
        $rows = $this->queue->popBatch($batch);
        if ($rows === []) {
            return;
        }

        $usleep = max(0, (int) $this->settings->get('broadcast_usleep_us', 280000));
        $maxtry = max(1, min(20, (int) $this->settings->get('broadcast_max_retries', 8)));
        $owners = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload_json, true);
            if (! is_array($payload)) {
                $this->failRow($row, 'bad_request', 'invalid_payload_json', $maxtry);
                continue;
            }

            $qid = (int) $row->id;
            $bid = (int) $row->broadcast_id;
            $fresh = DB::table('svp_broadcast_queue')->where('id', $qid)->value('status');
            if ($fresh !== 'sending') {
                $this->queue->maybeMarkBroadcastDone($bid);
                continue;
            }

            if (! isset($owners[$bid])) {
                $bcast = DB::table('svp_broadcasts')->where('id', $bid)->first();
                $owners[$bid] = $bcast ? (int) ($bcast->owner_svp_user_id ?? 0) : 0;
            }

            $bot = (string) $row->bot;
            $canonicalHtml = (string) ($payload['text'] ?? '');
            $payload = $this->normalizeForPlatform($payload, $bot);
            [$method, $apiParams] = $this->buildSendParams($payload);

            $client = $this->clientForBot($bot, $owners[$bid]);
            $r = ['ok' => false, 'description' => 'no_token', 'error_code' => 400];
            $ok = false;

            if ($client instanceof TelegramApiClient || $client instanceof BaleApiClient) {
                $r = $this->sendWithMethod($client, $method, $apiParams);
                $ok = ! empty($r['ok']);

                if (! $ok && $bot === 'tg' && $this->shouldRetryTelegramHtmlAsPlain($r, $payload)) {
                    $fb = $payload;
                    $fb['text'] = $this->format->htmlToPlain($canonicalHtml);
                    unset($fb['parse_mode']);
                    [$methodFb, $apiFb] = $this->buildSendParams($fb);
                    $r = $this->sendWithMethod($client, $methodFb, $apiFb);
                    $ok = ! empty($r['ok']);
                } elseif (! $ok && $bot === 'bale' && $this->shouldRetryBaleAsPlain($r)) {
                    $fb = $payload;
                    $fb['text'] = $this->format->htmlToPlain($canonicalHtml);
                    unset($fb['parse_mode']);
                    [$methodFb, $apiFb] = $this->buildSendParams($fb);
                    $r = $this->sendWithMethod($client, $methodFb, $apiFb);
                    $ok = ! empty($r['ok']);
                }
            }

            if ($ok) {
                DB::table('svp_broadcast_queue')->where('id', $qid)->update([
                    'status' => 'sent',
                    'tries' => (int) $row->tries + 1,
                    'last_error' => null,
                    'failure_kind' => null,
                    'updated_at' => now(),
                ]);
                DB::table('svp_broadcasts')->where('id', $bid)->increment('sent_count');
            } else {
                $this->handleFailure($row, $r, $maxtry, $usleep);
            }

            $this->queue->maybeMarkBroadcastDone($bid);
            if ($usleep > 0) {
                usleep($usleep);
            }
        }
    }

    /** @param  array<string, mixed>  $r */
    protected function handleFailure(object $row, array $r, int $maxtry, int $usleep): void
    {
        $kind = $this->classifyError($r);
        $err = $this->errorSummary($r);
        $tries = (int) $row->tries + 1;
        $qid = (int) $row->id;
        $bid = (int) $row->broadcast_id;

        if ($kind === 'blocked') {
            DB::table('svp_broadcast_queue')->where('id', $qid)->update([
                'status' => 'failed',
                'tries' => $tries,
                'failure_kind' => 'blocked',
                'last_error' => $err,
                'updated_at' => now(),
            ]);
            DB::table('svp_broadcasts')->where('id', $bid)->increment('blocked_count');
        } elseif ($kind === 'rate_limit') {
            DB::table('svp_broadcast_queue')->where('id', $qid)->update([
                'status' => 'pending',
                'tries' => $tries,
                'failure_kind' => 'rate_limit',
                'last_error' => $err,
                'updated_at' => now(),
            ]);
            if ($usleep > 0) {
                usleep(min(2000000, $usleep * 2));
            }
        } elseif ($kind === 'bad_request') {
            DB::table('svp_broadcast_queue')->where('id', $qid)->update([
                'status' => 'failed',
                'tries' => $tries,
                'failure_kind' => 'bad_request',
                'last_error' => $err,
                'updated_at' => now(),
            ]);
            DB::table('svp_broadcasts')->where('id', $bid)->increment('failed_count');
        } else {
            if ($tries >= $maxtry) {
                DB::table('svp_broadcast_queue')->where('id', $qid)->update([
                    'status' => 'failed',
                    'tries' => $tries,
                    'failure_kind' => $kind,
                    'last_error' => $err,
                    'updated_at' => now(),
                ]);
                DB::table('svp_broadcasts')->where('id', $bid)->increment('failed_count');
            } else {
                DB::table('svp_broadcast_queue')->where('id', $qid)->update([
                    'status' => 'pending',
                    'tries' => $tries,
                    'failure_kind' => $kind,
                    'last_error' => $err,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function failRow(object $row, string $kind, string $err, int $maxtry): void
    {
        DB::table('svp_broadcast_queue')->where('id', (int) $row->id)->update([
            'status' => 'failed',
            'tries' => (int) $row->tries + 1,
            'failure_kind' => $kind,
            'last_error' => $err,
            'updated_at' => now(),
        ]);
        DB::table('svp_broadcasts')->where('id', (int) $row->broadcast_id)->increment('failed_count');
        $this->queue->maybeMarkBroadcastDone((int) $row->broadcast_id);
    }

    /** @param  array<string, mixed>  $payload
     * @return array{0:string,1:array<string,mixed>}
     */
    protected function buildSendParams(array $payload): array
    {
        $chatId = (int) ($payload['chat_id'] ?? 0);
        $text = (string) ($payload['text'] ?? '');
        $pm = trim((string) ($payload['parse_mode'] ?? ''));

        $urls = [];
        if (! empty($payload['media_urls']) && is_array($payload['media_urls'])) {
            foreach ($payload['media_urls'] as $u) {
                $u = trim((string) $u);
                if ($u !== '' && filter_var($u, FILTER_VALIDATE_URL)) {
                    $urls[] = $u;
                }
            }
        }
        $legacy = trim((string) ($payload['photo'] ?? ''));
        if ($legacy !== '' && filter_var($legacy, FILTER_VALIDATE_URL) && $urls === []) {
            $urls[] = $legacy;
        }

        $n = count($urls);
        if ($n >= 2) {
            $media = [];
            foreach ($urls as $i => $u) {
                $item = ['type' => 'photo', 'media' => $u];
                if ($i === 0) {
                    if ($text !== '') {
                        $item['caption'] = $text;
                    }
                    if ($pm !== '' && $pm !== 'None') {
                        $item['parse_mode'] = $pm;
                    }
                }
                $media[] = $item;
            }

            return ['sendMediaGroup', ['chat_id' => $chatId, 'media' => json_encode($media)]];
        }
        if ($n === 1) {
            $params = ['chat_id' => $chatId, 'photo' => $urls[0], 'caption' => $text];
            if ($pm !== '' && $pm !== 'None') {
                $params['parse_mode'] = $pm;
            }

            return ['sendPhoto', $params];
        }

        $params = ['chat_id' => $chatId, 'text' => $text];
        if ($pm !== '' && $pm !== 'None') {
            $params['parse_mode'] = $pm;
        }

        return ['sendMessage', $params];
    }

    /** @param  array<string, mixed>  $payload */
    protected function normalizeForPlatform(array $payload, string $bot): array
    {
        if ($bot !== 'bale') {
            return $payload;
        }
        $text = (string) ($payload['text'] ?? '');
        if ($text === '') {
            unset($payload['parse_mode']);

            return $payload;
        }
        $payload['text'] = $this->format->formatForBaleMarkdown($text);
        unset($payload['parse_mode']);

        return $payload;
    }

  /** @param  array<string, mixed>  $r */
    protected function classifyError(array $r): string
    {
        if (! empty($r['ok'])) {
            return 'unknown';
        }
        $code = (int) ($r['error_code'] ?? 0);
        $desc = strtolower((string) ($r['description'] ?? ''));
        if ($code === 429) {
            return 'rate_limit';
        }
        if (in_array($code, [502, 503, 504], true)) {
            return 'network';
        }
        if ($code === 400) {
            return 'bad_request';
        }
        if ($code === 403) {
            if (str_contains($desc, 'blocked') || str_contains($desc, 'deactivated')
                || str_contains($desc, 'forbidden') || str_contains($desc, 'kicked')) {
                return 'blocked';
            }

            return 'bad_request';
        }
        if (str_contains($desc, 'timeout') || str_contains($desc, 'timed out')) {
            return 'network';
        }

        return 'unknown';
    }

    /** @param  array<string, mixed>  $r */
    protected function errorSummary(array $r): string
    {
        $code = isset($r['error_code']) ? (string) $r['error_code'] : '';
        $desc = mb_substr((string) ($r['description'] ?? ''), 0, 500);

        return trim("{$code}: {$desc}");
    }

    /** @param  array<string, mixed>  $r
     * @param  array<string, mixed>  $payload
     */
    protected function shouldRetryTelegramHtmlAsPlain(array $r, array $payload): bool
    {
        if (! empty($r['ok']) || (int) ($r['error_code'] ?? 0) !== 400) {
            return false;
        }
        $pm = strtoupper(trim((string) ($payload['parse_mode'] ?? '')));
        if ($pm !== 'HTML') {
            return false;
        }
        $desc = strtolower((string) ($r['description'] ?? ''));
        foreach (['parse', 'entity', 'entities', 'formatted', 'unsupported', "can't parse", 'cannot parse'] as $needle) {
            if (str_contains($desc, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $r */
    protected function shouldRetryBaleAsPlain(array $r): bool
    {
        if (! empty($r['ok']) || (int) ($r['error_code'] ?? 0) !== 400) {
            return false;
        }
        $desc = strtolower((string) ($r['description'] ?? ''));
        foreach (['parse', 'entity', 'markdown', "can't parse"] as $needle) {
            if (str_contains($desc, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $params */
    protected function sendWithMethod(TelegramApiClient|BaleApiClient $client, string $method, array $params): array
    {
        $r = match ($method) {
            'sendMediaGroup' => $client->sendMediaGroup($params),
            'sendPhoto' => $client->sendPhoto($params),
            default => $client->sendMessage($params),
        };

        return is_array($r) ? $r : ['ok' => false, 'description' => 'no_response'];
    }

    protected function clientForBot(string $bot, int $ownerRid): TelegramApiClient|BaleApiClient|null
    {
        $platform = $bot === 'bale' ? 'bale' : 'telegram';
        $profile = null;
        if ($ownerRid > 0 && Schema::hasTable('svp_reseller_bot_profiles')) {
            $row = DB::table('svp_reseller_bot_profiles')->where('reseller_svp_user_id', $ownerRid)->first();
            $profile = $row ? (array) $row : null;
        }

        $ctx = new BotContext($platform, $ownerRid, $profile);
        $client = $this->runtime->client($ctx);

        return $client instanceof TelegramApiClient || $client instanceof BaleApiClient ? $client : null;
    }
}
