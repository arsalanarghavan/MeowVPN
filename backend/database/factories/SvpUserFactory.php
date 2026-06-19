<?php

namespace Database\Factories;

use App\Models\SvpUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SvpUser> */
class SvpUserFactory extends Factory
{
    protected $model = SvpUser::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'role' => 'user',
            'balance' => 0,
            'status' => 'approved',
            'created_at' => now(),
        ];
    }

    public function reseller(): static
    {
        return $this->state(fn () => ['role' => 'reseller']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}
