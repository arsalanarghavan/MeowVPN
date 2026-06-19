<?php

namespace App\Services\Commerce;

class ReceiptActionService
{
    public function __construct(protected ReceiptProcessorService $processor) {}

    /** @param  array<string, mixed>  $payload */
    public function apply(array $payload): array
    {
        $id = (int) ($payload['receipt_id'] ?? $payload['id'] ?? 0);
        $action = (string) ($payload['action'] ?? $payload['status'] ?? '');
        if ($id < 1 || $action === '') {
            return svp_err('invalid');
        }

        $label = (string) ($payload['admin_label'] ?? 'dashboard');

        if (in_array($action, ['approve', 'approved'], true)) {
            $result = $this->processor->approve($id, $label);
            if (empty($result['ok'])) {
                return svp_err((string) ($result['reason'] ?? 'failed'));
            }

            return svp_ok(array_merge(['receipt_id' => $id, 'status' => 'approved'], $result));
        }

        if (in_array($action, ['reject', 'rejected'], true)) {
            return $this->processor->reject($id, $label, (string) ($payload['reject_reason'] ?? ''));
        }

        return svp_err('invalid_action');
    }
}
