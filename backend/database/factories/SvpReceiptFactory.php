<?php

namespace Database\Factories;

use App\Models\SvpReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SvpReceipt> */
class SvpReceiptFactory extends Factory
{
    protected $model = SvpReceipt::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'plan_id' => 1,
            'amount' => fake()->numberBetween(10000, 200000),
            'status' => 'pending',
            'image_path' => '',
            'created_at' => now(),
        ];
    }
}
