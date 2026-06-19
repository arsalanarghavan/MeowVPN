<?php

namespace Tests\Feature\Auth;

use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
        config(['svp.login_rate_limit_per_min' => 2]);
        Cache::flush();
        RateLimiter::clear('login:127.0.0.1');
    }

    public function test_login_rate_limited_after_threshold(): void
    {
        $this->postJson('/api/v1/auth/login', ['log' => 'admin', 'pwd' => 'wrong']);
        $this->postJson('/api/v1/auth/login', ['log' => 'admin', 'pwd' => 'wrong']);
        $this->postJson('/api/v1/auth/login', ['log' => 'admin', 'pwd' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'rate_limited');
    }
}
