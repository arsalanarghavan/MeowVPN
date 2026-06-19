<?php

namespace Database\Factories;

use App\Models\SvpTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SvpTransaction> */
class SvpTransactionFactory extends Factory
{
    protected $model = SvpTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'amount' => fake()->randomFloat(2, 1000, 500000),
            'type' => 'purchase',
            'status' => 'completed',
            'billing_reseller_svp_id' => 0,
            'created_at' => now(),
        ];
    }

    public function forReseller(int $resellerSvpId): static
    {
        return $this->state(fn () => ['billing_reseller_svp_id' => $resellerSvpId]);
    }
}
