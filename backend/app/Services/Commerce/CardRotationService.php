<?php

namespace App\Services\Commerce;

use App\Services\Reseller\ResellerBrandingService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CardRotationService
{
    /** @return array<int, string> */
    public function allowedDisplayModes(): array
    {
        return ['list', 'sequential', 'random'];
    }

    public function sanitizeDisplayMode(mixed $raw): string
    {
        $mode = Str::slug((string) $raw);

        return in_array($mode, $this->allowedDisplayModes(), true) ? $mode : 'list';
    }

    public function resolveOwnerScopeKey(int $transactionId): string
    {
        if ($transactionId < 1) {
            return '0';
        }
        $tx = DB::table('svp_transactions')->where('id', $transactionId)->first();
        if (! $tx) {
            return '0';
        }
        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        if (is_array($meta) && ! empty($meta['invoice_card_owner_scope_svp_id'])) {
            $scopeRid = (int) $meta['invoice_card_owner_scope_svp_id'];
            if ($scopeRid > 0) {
                return 'scope:'.$scopeRid;
            }
        }
        $uid = (int) ($tx->user_id ?? 0);
        $rid = $uid > 0 ? app(ResellerBrandingService::class)->nearestResellerIdForUser($uid) : 0;
        if ($rid > 0) {
            return 'reseller:'.$rid;
        }

        return '0';
    }

    public function fitsDailyLimit(float $used, float $limit, float $amount): bool
    {
        if ($limit <= 0) {
            return true;
        }

        return $used + $amount <= $limit + 0.000001;
    }

    public function isCardEligible(object $card, int $transactionId = 0, float $amountToman = 0.0): bool
    {
        $cid = (int) ($card->id ?? 0);
        $limit = (float) ($card->daily_limit ?? 0);
        if ($cid < 1) {
            return false;
        }
        if ($limit <= 0) {
            return true;
        }
        $amount = $amountToman;
        if ($amount <= 0 && $transactionId > 0) {
            $tx = DB::table('svp_transactions')->where('id', $transactionId)->first();
            if ($tx) {
                $amount = (float) ($tx->amount ?? 0);
            }
        }
        $used = (float) DB::table('svp_receipts')
            ->where('card_id', $cid)
            ->where('status', 'approved')
            ->whereDate('created_at', gmdate('Y-m-d'))
            ->when($transactionId > 0, fn ($q) => $q->where('transaction_id', '!=', $transactionId))
            ->sum('amount');

        return $this->fitsDailyLimit($used, $limit, $amount);
    }

    /**
     * @param  array<int, object>  $cards
     * @return array<int, object>
     */
    public function pickForCheckout(array $cards, string $mode, string $scopeKey, int $transactionId = 0): array
    {
        $mode = $this->sanitizeDisplayMode($mode);
        if ($cards === []) {
            return [];
        }
        if ($mode === 'list') {
            return $cards;
        }
        if ($mode === 'random') {
            return $this->pickRandom($cards, $transactionId);
        }

        return $this->pickRoundRobin($cards, $scopeKey, $transactionId);
    }

    /**
     * @param  array<int, object>  $cards
     * @return array<int, object>
     */
    protected function pickRoundRobin(array $cards, string $scopeKey, int $transactionId): array
    {
        $n = count($cards);
        if ($n < 1) {
            return [];
        }
        if ($n === 1) {
            return [$cards[0]];
        }
        $start = $this->getCursor($scopeKey) % $n;
        for ($i = 0; $i < $n; $i++) {
            $idx = ($start + $i) % $n;
            $card = $cards[$idx];
            if ($this->isCardEligible($card, $transactionId)) {
                $this->advanceCursor($scopeKey, ($idx + 1) % $n);

                return [$card];
            }
        }
        $card = $cards[$start];
        $this->advanceCursor($scopeKey, ($start + 1) % $n);

        return [$card];
    }

    /**
     * @param  array<int, object>  $cards
     * @return array<int, object>
     */
    protected function pickRandom(array $cards, int $transactionId): array
    {
        $eligible = [];
        foreach ($cards as $c) {
            if ($this->isCardEligible($c, $transactionId)) {
                $eligible[] = $c;
            }
        }
        $pool = $eligible !== [] ? $eligible : $cards;
        $idx = $this->randomIndex(count($pool));

        return [$pool[$idx]];
    }

    public function getCursor(string $scopeKey): int
    {
        $key = Str::slug(str_replace([':', '-'], '_', $scopeKey));
        $cursors = $this->settings()->get('cards_rotation_cursors', []);
        if (! is_array($cursors)) {
            return 0;
        }

        return max(0, (int) ($cursors[$key] ?? 0));
    }

    public function advanceCursor(string $scopeKey, int $nextIndex): void
    {
        $key = Str::slug(str_replace([':', '-'], '_', $scopeKey));
        $cursors = $this->settings()->get('cards_rotation_cursors', []);
        if (! is_array($cursors)) {
            $cursors = [];
        }
        $cursors[$key] = max(0, $nextIndex);
        $this->settings()->set('cards_rotation_cursors', $cursors);
    }

    protected function randomIndex(int $count): int
    {
        if ($count < 2) {
            return 0;
        }

        return random_int(0, $count - 1);
    }

    protected function settings(): SettingsStore
    {
        return app(SettingsStore::class);
    }
}
