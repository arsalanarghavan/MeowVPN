<?php

namespace Database\Factories;

use App\Models\SvpPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SvpPlan> */
class SvpPlanFactory extends Factory
{
    protected $model = SvpPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'category' => 'normal',
            'panel_id' => 1,
            'inbound_id' => 0,
            'price' => fake()->numberBetween(10000, 500000),
            'traffic_gb' => fake()->numberBetween(10, 500),
            'duration_days' => 30,
            'active' => 1,
            'created_at' => now(),
        ];
    }
}
