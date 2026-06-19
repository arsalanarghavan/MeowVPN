<?php

namespace Tests\Feature\L2tp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class L2tpCrudTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_l2tp_add_encrypts_secrets(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_add',
            'label' => 'Edge L2TP',
            'ssh_host' => '10.1.1.1',
            'l2tp_host' => 'l2tp.edge.test',
            'ssh_private_key' => 'PRIVATE-KEY-DATA',
            'l2tp_psk' => 'psk-secret',
        ])->assertOk()->assertJsonPath('ok', true);

        $row = DB::table('svp_l2tp_servers')->where('label', 'Edge L2TP')->first();
        $this->assertNotNull($row);
        $this->assertNotSame('PRIVATE-KEY-DATA', $row->ssh_private_key_enc);
        $this->assertSame('PRIVATE-KEY-DATA', Crypt::decryptString((string) $row->ssh_private_key_enc));
        $this->assertSame('psk-secret', Crypt::decryptString((string) $row->l2tp_psk_enc));
    }

    public function test_bootstrap_shows_l2tp_feature_when_module_enabled(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('features.l2tp', true);
    }
}
