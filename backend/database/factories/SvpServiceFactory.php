<?php

namespace Database\Factories;

use App\Models\SvpService;
use App\Models\SvpUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SvpService> */
class SvpServiceFactory extends Factory
{
    protected $model = SvpService::class;

    public function definition(): array
    {
        return [
            'user_id' => SvpUser::factory(),
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => fake()->unique()->safeEmail(),
            'remark' => fake()->word(),
            'service_type' => 'xray',
            'total_traffic' => 10 * 1073741824,
            'used_traffic' => 0,
            'client_enabled' => 1,
            'created_at' => now(),
        ];
    }
}
