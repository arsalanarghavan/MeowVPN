<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 Group C — Users */
class GroupCUsersAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_user_search(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/user-search?q=user101')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_user_detail(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/user/101')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_users_bulk_jobs_list(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/users-bulk-jobs')
            ->assertOk()
            ->assertJsonStructure(['jobs']);
    }

    public function test_manual_create_user(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_manual_create',
            'username' => 'acc_new_user',
            'first_name' => 'Acc',
        ])->assertOk();
    }

    public function test_users_pagination_in_state(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/state?tab=users&users_offset=0&users_limit=10')
            ->assertOk()
            ->assertJsonStructure(['users', 'pagination']);
    }

    public function test_reseller_users_scoped(): void
    {
        $this->actingAsReseller();

        $this->getJson('/api/v1/admin/state?tab=users')
            ->assertOk()
            ->assertJsonStructure(['users']);
    }
}
