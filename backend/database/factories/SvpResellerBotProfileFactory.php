<?php

namespace Database\Factories;

use App\Models\SvpResellerBotProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SvpResellerBotProfile> */
class SvpResellerBotProfileFactory extends Factory
{
    protected $model = SvpResellerBotProfile::class;

    public function definition(): array
    {
        return [
            'reseller_svp_user_id' => 1,
            'platform' => 'telegram',
            'bot_token_enc' => 'enc',
            'webhook_secret' => fake()->uuid(),
            'enabled' => true,
            'created_at' => now(),
        ];
    }
}
