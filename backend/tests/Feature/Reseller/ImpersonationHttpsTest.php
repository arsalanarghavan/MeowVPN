<?php

namespace Tests\Feature\Reseller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ImpersonationHttpsTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_impersonate_start_requires_https_outside_local(): void
    {
        $this->app['env'] = 'staging';
        $csrf = $this->get('/sanctum/csrf-cookie');
        $token = $this->extractXsrfToken($csrf);

        $this->withHeaders(['X-XSRF-TOKEN' => $token])
            ->actingAsAdmin()
            ->postJson('/api/v1/dashboard/impersonate/start', [
                'targetSvpUserId' => 100,
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'https_required');
    }

    protected function extractXsrfToken(\Illuminate\Testing\TestResponse $response): string
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'XSRF-TOKEN');

        if (! $cookie) {
            return '';
        }

        return urldecode($cookie->getValue());
    }
}
