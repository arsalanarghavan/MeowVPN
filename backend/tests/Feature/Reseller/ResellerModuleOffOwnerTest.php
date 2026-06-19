<?php

namespace Tests\Feature\Reseller;

use App\Services\Mutations\MutateContext;
use App\Services\Mutations\MutateScopeGuard;
use App\Services\ResellerModuleGuard;
use Tests\TestCase;

class ResellerModuleOffOwnerTest extends TestCase
{
    protected function disableResellerModule(): void
    {
        config(['modules.modules.reseller.enabled' => false]);
        $this->app->forgetInstance(\App\Modules\ModuleManager::class);
    }

    public function test_owner_normalized_to_zero_when_reseller_module_disabled(): void
    {
        $this->disableResellerModule();

        $guard = app(ResellerModuleGuard::class);
        $this->assertSame(0, $guard->normalizeOwnerId(42));
    }

    public function test_owner_preserved_when_reseller_module_enabled(): void
    {
        $guard = app(ResellerModuleGuard::class);
        $this->assertSame(5, $guard->normalizeOwnerId(5));
    }

    public function test_mutate_scope_guard_strips_owner_when_module_off(): void
    {
        $this->disableResellerModule();

        $guard = app(MutateScopeGuard::class);
        $ctx = new MutateContext(isAdmin: true, isReseller: false, actorSvpUserId: 0, resellerContextId: 0, op: 'discount_save');
        $out = $guard->enrichPayload(['owner_svp_user_id' => 99], $ctx);

        $this->assertSame(0, $out['owner_svp_user_id']);
    }
}
