<?php

namespace App\Services\Migration;

use App\Models\DashboardUser;
use Illuminate\Support\Facades\DB;

class WpResellerPermsImporter
{
    public function __construct(protected WpOptionsDecoder $decoder) {}

    /** @param  array<string, mixed>  $options */
    public function import(array $options, bool $dryRun = false): int
    {
        $count = 0;
        foreach ($options as $name => $raw) {
            if (! preg_match('/^simplevpbot_reseller_perms_(\d+)$/', (string) $name, $m)) {
                continue;
            }
            $svpUserId = (int) $m[1];
            $perms = $this->decoder->decode($raw);
            if (! is_array($perms)) {
                continue;
            }
            if ($dryRun) {
                $count++;
                continue;
            }
            $updated = DashboardUser::query()
                ->where('svp_user_id', $svpUserId)
                ->update(['permissions_json' => $perms]);
            if ($updated < 1) {
                DB::table('svp_settings')->updateOrInsert(
                    ['key_name' => 'reseller_perms.'.$svpUserId],
                    ['value' => json_encode($perms, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
                );
            }
            $count++;
        }

        return $count;
    }
}
