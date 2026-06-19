<?php

namespace App\Services\AdminState;

use Illuminate\Database\Eloquent\Model;
use stdClass;

class AdminRowFormatter
{
    /** @return array<string, mixed> */
    public static function rowArray(mixed $row): array
    {
        if ($row instanceof Model) {
            return $row->toArray();
        }
        if ($row instanceof stdClass) {
            return (array) $row;
        }
        if (is_array($row)) {
            return $row;
        }

        return [];
    }

    /** @param  array<string, mixed>  $row */
    public static function sanitizeUserRow(array $row, bool $unrestrictedAdmin = true): array
    {
        if ($unrestrictedAdmin) {
            return $row;
        }

        unset(
            $row['state_data'],
            $row['admin_mode'],
            $row['password_hash'],
            $row['dashboard_password'],
            $row['signup_reseller_svp_id'],
        );

        return $row;
    }

    /**
     * @param  array<int, mixed>  $usersList
     * @return array<int, array<string, mixed>>
     */
    public static function usersListRows(array $usersList, bool $unrestrictedAdmin = true): array
    {
        $out = [];
        foreach ($usersList as $u) {
            $row = self::sanitizeUserRow(self::rowArray($u), $unrestrictedAdmin);
            if ($row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function formatReceipt(mixed $receipt): array
    {
        $row = self::rowArray($receipt);
        $rid = (int) ($row['id'] ?? 0);
        if ($rid > 0 && empty($row['image_url'])) {
            $row['image_url'] = url("/api/v1/admin/receipts/{$rid}/image");
        }

        return $row;
    }
}
