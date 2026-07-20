<?php

namespace App\Services\Commerce;

use App\Models\SvpUser;
use App\Modules\Core\Services\UserBotNotifyService;
use Illuminate\Support\Facades\DB;

class TransactionFulfillService
{
    public function __construct(
        protected ServiceProvisioner $provisioner,
        protected ServiceProvisionService $provision,
        protected UserBotNotifyService $notify,
    ) {}

    public function claimPendingTransaction(int $txId): bool
    {
        return DB::table('svp_transactions')
            ->where('id', $txId)
            ->where('status', 'pending')
            ->update(['status' => 'processing']) === 1;
    }

    /** @return array{ok:bool, reason?:string, service_id?:int, topup?:bool} */
    public function fulfillByTransaction(int $txId, string $source = 'wallet'): array
    {
        return DB::transaction(function () use ($txId, $source) {
            $tx = DB::table('svp_transactions')->where('id', $txId)->lockForUpdate()->first();
            if (! $tx) {
                return ['ok' => false, 'reason' => 'bad_tx'];
            }
            if ((string) $tx->status === 'approved') {
                return ['ok' => true, 'reason' => 'already_approved'];
            }
            if (! in_array((string) $tx->status, ['pending', 'processing'], true)) {
                return ['ok' => false, 'reason' => 'bad_tx'];
            }

            if ((string) $tx->status === 'pending') {
                DB::table('svp_transactions')->where('id', $txId)->update(['status' => 'processing']);
            }

            $tx = DB::table('svp_transactions')->where('id', $txId)->first();
            if (! $tx) {
                return ['ok' => false, 'reason' => 'bad_tx'];
            }

            $type = (string) $tx->type;
            $userId = (int) $tx->user_id;
            $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
            $meta = is_array($meta) ? $meta : [];
            $serviceId = (int) ($tx->service_id ?? 0);

            try {
                if ($type === 'purchase') {
                    if (empty($meta['plan_id'])) {
                        $this->revertToPending($txId);

                        return ['ok' => false, 'reason' => 'no_plan_id'];
                    }
                    $volumeGb = isset($meta['volume_gb']) ? (int) $meta['volume_gb'] : null;
                    $platform = isset($meta['platform']) ? (string) $meta['platform'] : null;
                    $result = $this->provisioner->createFromPlan($userId, (int) $meta['plan_id'], $volumeGb, $platform);
                    if (empty($result['ok'])) {
                        $this->revertToPending($txId);

                        return ['ok' => false, 'reason' => (string) ($result['reason'] ?? 'provision_failed')];
                    }
                    $serviceId = (int) ($result['service_id'] ?? 0);
                } elseif ($type === 'service_renew') {
                    $svcId = (int) ($meta['service_id'] ?? 0);
                    if ($svcId < 1) {
                        $this->revertToPending($txId);

                        return ['ok' => false, 'reason' => 'no_service_id'];
                    }
                    $result = $this->provision->renew($svcId, 'free');
                    if (empty($result['ok'])) {
                        $this->revertToPending($txId);

                        return ['ok' => false, 'reason' => (string) ($result['message'] ?? 'renew_failed')];
                    }
                    $serviceId = $svcId;
                } elseif ($type === 'service_add_volume') {
                    $svcId = (int) ($meta['service_id'] ?? 0);
                    $gb = (int) ($meta['gb'] ?? 0);
                    if ($svcId < 1 || $gb < 1) {
                        $this->revertToPending($txId);

                        return ['ok' => false, 'reason' => 'invalid_meta'];
                    }
                    $result = $this->provision->addVolume($svcId, $gb, 'free');
                    if (empty($result['ok'])) {
                        $this->revertToPending($txId);

                        return ['ok' => false, 'reason' => (string) ($result['message'] ?? 'add_volume_failed')];
                    }
                    $serviceId = $svcId;
                } elseif ($type === 'service_add_slots') {
                    $svcId = (int) ($meta['service_id'] ?? 0);
                    $slots = (int) ($meta['slots'] ?? 0);
                    if ($svcId < 1 || $slots < 1) {
                        $this->revertToPending($txId);

                        return ['ok' => false, 'reason' => 'invalid_meta'];
                    }
                    $result = $this->provision->addSlots($svcId, $slots, 'free');
                    if (empty($result['ok'])) {
                        $this->revertToPending($txId);

                        return ['ok' => false, 'reason' => (string) ($result['message'] ?? 'add_slots_failed')];
                    }
                    $serviceId = $svcId;
                } elseif ($type === 'wallet_topup') {
                    $this->creditWallet($userId, (float) $tx->amount);
                    DB::table('svp_transactions')->where('id', $txId)->update(['status' => 'approved']);
                    $this->notifyUser($userId, 'کیف پول شما شارژ شد.');

                    return ['ok' => true, 'topup' => true];
                } else {
                    $this->revertToPending($txId);

                    return ['ok' => false, 'reason' => 'unsupported_type'];
                }

                DB::table('svp_transactions')->where('id', $txId)->update([
                    'status' => 'approved',
                    'service_id' => $serviceId > 0 ? $serviceId : null,
                ]);

                $msg = match ($type) {
                    'service_renew' => 'تمدید سرویس با موفقیت انجام شد.',
                    'service_add_volume' => 'حجم سرویس افزایش یافت.',
                    'service_add_slots' => 'اسلات‌های سرویس افزایش یافت.',
                    default => 'پرداخت شما تایید شد. ممنون!',
                };
                if ($serviceId > 0 && $type === 'purchase') {
                    $msg .= "\n".'Service #'.$serviceId;
                }
                $this->notifyUser($userId, $msg);

                return ['ok' => true, 'service_id' => $serviceId];
            } catch (\Throwable $e) {
                $this->revertToPending($txId);
                throw $e;
            }
        });
    }

    public function creditWallet(int $userId, float $amount): void
    {
        if ($amount <= 0 || $userId < 1) {
            return;
        }
        $user = SvpUser::query()->lockForUpdate()->find($userId);
        if (! $user) {
            return;
        }
        $user->balance = bcadd((string) $user->balance, (string) $amount, 2);
        $user->save();
    }

    public function debitWallet(int $userId, float $amount): bool
    {
        if ($amount <= 0 || $userId < 1) {
            return false;
        }
        $user = SvpUser::query()->lockForUpdate()->find($userId);
        if (! $user || (float) $user->balance < $amount) {
            return false;
        }
        $user->balance = bcsub((string) $user->balance, (string) $amount, 2);
        $user->save();

        return true;
    }

    public function refundWallet(int $userId, float $amount): void
    {
        $this->creditWallet($userId, $amount);
    }

    protected function revertToPending(int $txId): void
    {
        DB::table('svp_transactions')
            ->where('id', $txId)
            ->where('status', 'processing')
            ->update(['status' => 'pending']);
    }

    protected function notifyUser(int $userId, string $msg): void
    {
        $user = SvpUser::query()->find($userId);
        if ($user) {
            $this->notify->sendToUser($user, $msg);
        }
    }
}
