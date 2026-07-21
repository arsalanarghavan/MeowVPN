<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\Services\TextService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class TextServiceLocaleTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_get_respects_locale_column(): void
    {
        DB::table('svp_texts')->insert([
            [
                'key_name' => 'msg.locale.test',
                'category' => 'messages',
                'locale' => 'fa',
                'value' => 'سلام',
                'updated_at' => now(),
            ],
            [
                'key_name' => 'msg.locale.test',
                'category' => 'messages',
                'locale' => 'en',
                'value' => 'Hello',
                'updated_at' => now(),
            ],
        ]);

        $texts = app(TextService::class);
        $this->assertSame('سلام', $texts->get('msg.locale.test', '', 'fa'));
        $this->assertSame('Hello', $texts->get('msg.locale.test', '', 'en'));
    }

    public function test_get_for_user_uses_bot_locale(): void
    {
        DB::table('svp_texts')->insert([
            [
                'key_name' => 'msg.user.locale',
                'category' => 'messages',
                'locale' => 'en',
                'value' => 'EN-VAL',
                'updated_at' => now(),
            ],
            [
                'key_name' => 'msg.user.locale',
                'category' => 'messages',
                'locale' => 'fa',
                'value' => 'FA-VAL',
                'updated_at' => now(),
            ],
        ]);
        $user = SvpUser::query()->create([
            'username' => 'locuser',
            'bot_locale' => 'en',
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $this->assertSame('EN-VAL', app(TextService::class)->getForUser('msg.user.locale', $user));
    }

    public function test_falls_back_to_defaults_pack(): void
    {
        app(SettingsStore::class)->set('default_bot_locale', 'en');
        $val = app(TextService::class)->get('btn.main.buy', '', 'en');
        $this->assertStringContainsString('Buy', $val);
    }
}
