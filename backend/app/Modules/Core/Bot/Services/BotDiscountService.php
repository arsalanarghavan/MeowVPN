<?php

namespace App\Modules\Core\Bot\Services;

use Illuminate\Support\Facades\DB;

class BotDiscountService
{
    /** @return array{ok:bool, message?:string, row?:object, amount?:float} */
    public function validateAndApply(int $txId, string $code, int $userId): array
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        if (! $tx) {
            return ['ok' => false, 'message' => 'tx_not_found'];
        }

        $row = DB::table('svp_discount_codes')
            ->where('code', strtoupper(trim($code)))
            ->where('active', 1)
            ->first();
        if (! $row) {
            return ['ok' => false, 'message' => 'invalid_code'];
        }

        if (! empty($row->valid_from) && now()->lt($row->valid_from)) {
            return ['ok' => false, 'message' => 'not_yet_valid'];
        }
        if (! empty($row->valid_until) && now()->gt($row->valid_until)) {
            return ['ok' => false, 'message' => 'expired'];
        }
        if (! empty($row->max_uses) && (int) ($row->uses_count ?? 0) >= (int) $row->max_uses) {
            return ['ok' => false, 'message' => 'max_uses'];
        }

        $redemptions = (int) DB::table('svp_discount_redemptions')
            ->where('code_id', (int) $row->id)
            ->where('user_id', $userId)
            ->count();
        if ($redemptions > 0 && ! empty($row->restricted_svp_user_id) && (int) $row->restricted_svp_user_id !== $userId) {
            return ['ok' => false, 'message' => 'restricted'];
        }

        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $base = (float) ($meta['base_amount'] ?? $tx->amount);
        if (! isset($meta['base_amount'])) {
            $meta['base_amount'] = $base;
        }

        $amount = $base;
        $val = (float) ($row->discount_value ?? 0);
        if ((string) ($row->discount_type ?? '') === 'percent') {
            $amount = max(0, $base * (1 - $val / 100));
        } else {
            $amount = max(0, $base - $val);
        }
        if (! empty($row->max_discount_toman)) {
            $amount = max($amount, $base - (float) $row->max_discount_toman);
        }

        $meta['discount_code'] = strtoupper(trim($code));
        $meta['discount_code_id'] = (int) $row->id;

        DB::table('svp_transactions')->where('id', $txId)->update([
            'amount' => $amount,
            'meta_json' => json_encode($meta),
        ]);

        return ['ok' => true, 'amount' => $amount, 'row' => $row];
    }

    /** @return array{ok:bool, amount?:float} */
    public function clearFromTransaction(int $txId): array
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        if (! $tx) {
            return ['ok' => false];
        }

        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $base = (float) ($meta['base_amount'] ?? $tx->amount);
        unset($meta['discount_code'], $meta['discount_code_id']);

        DB::table('svp_transactions')->where('id', $txId)->update([
            'amount' => $base,
            'meta_json' => json_encode($meta),
        ]);

        return ['ok' => true, 'amount' => $base];
    }
}
