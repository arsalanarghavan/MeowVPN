<?php

namespace Database\Factories;

use App\Models\DashboardUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<DashboardUser> */
class DashboardUserFactory extends Factory
{
    protected $model = DashboardUser::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'svp_user_id' => null,
            'permissions_json' => null,
            'ui_accent' => null,
        ];
    }

    public function reseller(): static
    {
        return $this->state(fn () => ['role' => 'reseller']);
    }
}
