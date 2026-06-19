<?php

namespace Database\Seeders;

use App\Models\DashboardUser;
use App\Services\SettingsStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('SVP_ADMIN_PASSWORD', '');
        if ($password === '') {
            if (app()->environment('production')) {
                throw new \RuntimeException('SVP_ADMIN_PASSWORD must be set when seeding admin in production');
            }
            $password = bin2hex(random_bytes(16));
        }

        DashboardUser::query()->updateOrCreate(
            ['username' => env('SVP_ADMIN_USERNAME', 'admin')],
            [
                'password' => Hash::make($password),
                'role' => 'admin',
            ]
        );

        app(SettingsStore::class)->merge([
            'site_name' => 'SimpleVPBot',
            'enabled' => true,
        ]);
    }
}
