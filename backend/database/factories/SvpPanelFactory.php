<?php

namespace Database\Factories;

use App\Models\SvpPanel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SvpPanel> */
class SvpPanelFactory extends Factory
{
    protected $model = SvpPanel::class;

    public function definition(): array
    {
        return [
            'label' => fake()->words(2, true),
            'panel_url' => 'https://panel.example.com',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'panel_api_base' => 'panel/api',
            'panel_login_secret' => '',
            'sort_order' => 0,
            'active' => true,
            'created_at' => now(),
        ];
    }
}
