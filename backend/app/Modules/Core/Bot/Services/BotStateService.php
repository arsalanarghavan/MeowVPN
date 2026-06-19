<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;

class BotStateService
{
    public function get(SvpUser $user): string
    {
        return (string) ($user->state ?? '');
    }

    /** @return array<string, mixed> */
    public function data(SvpUser $user): array
    {
        $raw = $user->state_data;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /** @param  array<string, mixed>  $data */
    public function set(SvpUser $user, ?string $state, array $data = []): void
    {
        $user->state = $state;
        $user->state_data = $data;
        $user->save();
    }

    public function clear(SvpUser $user): void
    {
        $this->set($user, null, []);
    }

    /** @param  array<string, mixed>  $data */
    public function mergeData(SvpUser $user, array $data): void
    {
        $this->set($user, $this->get($user), array_merge($this->data($user), $data));
    }
}
