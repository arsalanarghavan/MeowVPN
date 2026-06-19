<?php

namespace Database\Factories;

use App\Models\SvpCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SvpCard> */
class SvpCardFactory extends Factory
{
    protected $model = SvpCard::class;

    public function definition(): array
    {
        return [
            'owner_svp_user_id' => 0,
            'card_number' => fake()->numerify('####-####-####-####'),
            'holder_name' => fake()->name(),
            'bank_name' => 'Test Bank',
            'active' => 1,
            'priority' => 0,
            'created_at' => now(),
        ];
    }
}
