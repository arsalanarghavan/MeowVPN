<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

trait CreatesSvpTestSchema
{
    protected function createSvpTestSchema(): void
    {
        if (! Schema::hasTable('dashboard_users')) {
            Schema::create('dashboard_users', function (Blueprint $table) {
                $table->id();
                $table->string('username')->unique();
                $table->string('password');
                $table->string('role', 20)->default('admin');
                $table->unsignedBigInteger('svp_user_id')->nullable();
                $table->json('permissions_json')->nullable();
                $table->string('ui_accent', 32)->nullable();
                $table->string('ui_theme', 32)->nullable();
                $table->string('ui_sidebar', 32)->nullable();
                $table->string('ui_lang', 8)->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('svp_settings')) {
            Schema::create('svp_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key_name')->unique();
                $table->text('value')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        Schema::dropIfExists('svp_users');
        Schema::create('svp_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tg_user_id')->nullable();
            $table->bigInteger('bale_user_id')->nullable();
            $table->string('bot_locale', 5)->nullable();
            $table->boolean('admin_mode')->default(false);
            $table->string('state')->nullable();
            $table->text('state_data')->nullable();
            $table->string('phone')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('role', 20)->default('user');
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status', 20)->default('approved');
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->unsignedBigInteger('signup_reseller_svp_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique('tg_user_id', 'svp_users_tg');
            $table->unique('bale_user_id', 'svp_users_bale');
        });

        Schema::dropIfExists('svp_panels');
        Schema::create('svp_panels', function (Blueprint $table) {
            $table->id();
            $table->string('label')->default('');
            $table->text('panel_url')->nullable();
            $table->string('panel_username')->default('');
            $table->text('panel_password')->nullable();
            $table->string('panel_api_base')->default('panel/api');
            $table->string('panel_login_secret')->default('');
            $table->text('panel_api_token')->nullable();
            $table->string('panel_api_flavor')->default('unknown');
            $table->text('subscription_public_base')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_plans');
        Schema::create('svp_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('normal');
            $table->unsignedBigInteger('panel_id')->default(1);
            $table->integer('inbound_id')->default(0);
            $table->string('service_type')->default('xray');
            $table->boolean('active')->default(true);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('renew_price', 15, 2)->default(0);
            $table->string('pricing_type', 20)->default('fixed');
            $table->decimal('price_per_gb', 15, 2)->default(0);
            $table->integer('traffic_gb_min')->default(0);
            $table->integer('traffic_gb_max')->default(0);
            $table->integer('clients_count')->default(1);
            $table->integer('duration_days')->default(30);
            $table->integer('traffic_gb')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_plan_categories');
        Schema::create('svp_plan_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id')->default(1);
            $table->string('slug');
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->unique(['panel_id', 'slug'], 'panel_slug');
        });
        Schema::create('svp_reseller_closure', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedSmallInteger('depth')->default(0);
            $table->primary(['ancestor_id', 'descendant_id']);
        });

        Schema::dropIfExists('svp_receipts');
        Schema::create('svp_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('transaction_id')->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_by')->nullable();
            $table->string('reject_reason')->nullable();
            $table->string('file_id')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_inbound_queue');
        Schema::create('svp_inbound_queue', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 8);
            $table->unsignedBigInteger('reseller_svp_user_id')->default(0);
            $table->longText('update_json');
            $table->string('status', 16)->default('pending');
            $table->integer('tries')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('processed_at')->nullable();
        });

        Schema::dropIfExists('svp_services');
        Schema::create('svp_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('panel_id')->default(1);
            $table->unsignedBigInteger('inbound_id')->default(0);
            $table->string('email')->nullable();
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('provision_type')->default('manual');
            $table->unsignedBigInteger('total_traffic')->default(0);
            $table->unsignedBigInteger('used_traffic')->default(0);
            $table->integer('client_slots')->default(1);
            $table->boolean('client_enabled')->default(true);
            $table->string('xui_client_id')->nullable();
            $table->string('xui_client_uuid')->nullable();
            $table->string('sub_id')->nullable();
            $table->integer('limit_ip')->default(0);
            $table->text('alerts_json')->nullable();
            $table->boolean('alerts_volume')->default(false);
            $table->boolean('alerts_expiry')->default(false);
            $table->boolean('alerts_users')->default(false);
            $table->integer('alert_low_pct')->nullable();
            $table->string('alert_expiry_days')->nullable();
            $table->integer('alert_ip_fill_pct')->nullable();
            $table->text('service_note')->nullable();
            $table->string('service_type')->nullable();
            $table->string('status')->default('active');
            $table->string('l2tp_username')->nullable();
            $table->string('l2tp_password')->nullable();
            $table->unsignedBigInteger('l2tp_server_id')->nullable();
            $table->timestamp('traffic_synced_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::dropIfExists('svp_transactions');
        Schema::create('svp_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('billing_reseller_svp_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('type', 40)->default('');
            $table->string('status', 20)->default('pending');
            $table->text('meta_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('billing_reseller_svp_id', 'billing_reseller_svp_id');
        });

        Schema::dropIfExists('svp_audit_log');
        Schema::create('svp_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 20)->default('admin');
            $table->string('event_type', 64);
            $table->string('actor_kind', 20)->default('admin');
            $table->unsignedBigInteger('actor_svp_user_id')->default(0);
            $table->string('target_type', 20)->default('user');
            $table->unsignedBigInteger('target_id')->default(0);
            $table->unsignedBigInteger('reseller_scope_id')->default(0);
            $table->text('payload_json')->nullable();
            $table->string('ip_hash')->default('');
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_user_activity');
        Schema::create('svp_user_activity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('channel', 20)->default('rest');
            $table->string('actor_kind', 20)->default('admin');
            $table->unsignedBigInteger('actor_svp_user_id')->default(0);
            $table->string('event_type', 64);
            $table->text('payload_json')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_users_bulk_job_items');
        Schema::create('svp_users_bulk_job_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('user_id')->default(0);
            $table->unsignedBigInteger('panel_id')->default(0);
            $table->unsignedBigInteger('inbound_id')->default(0);
            $table->string('client_email')->default('');
            $table->string('status', 20)->default('pending');
            $table->integer('tries')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::dropIfExists('svp_users_bulk_jobs');
        Schema::create('svp_users_bulk_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 32);
            $table->string('scope', 32)->default('all_approved');
            $table->text('payload_json')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('created_by_svp_user_id')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('finished_at')->nullable();
        });

        Schema::dropIfExists('svp_cards');
        Schema::create('svp_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_svp_user_id')->default(0);
            $table->string('card_number')->default('');
            $table->string('holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('method_key')->default('c2c');
            $table->decimal('daily_limit', 15, 2)->default(0);
            $table->integer('priority')->default(0);
            $table->integer('sort_order')->default(0);
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_marketing_offers');
        Schema::create('svp_marketing_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id')->default(0);
            $table->unsignedBigInteger('svp_user_id')->default(0);
            $table->unsignedBigInteger('discount_code_id')->default(0);
            $table->string('status', 16)->default('issued');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('converted_transaction_id')->default(0);
            $table->text('meta_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['rule_id', 'svp_user_id'], 'rule_user');
        });

        Schema::dropIfExists('svp_marketing_rules');
        Schema::create('svp_marketing_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_svp_user_id')->default(0);
            $table->string('segment_key', 32)->default('');
            $table->boolean('enabled')->default(false);
            $table->integer('priority')->default(100);
            $table->integer('cooldown_days')->default(90);
            $table->integer('after_days')->default(0);
            $table->integer('pending_hours')->default(0);
            $table->integer('funnel_idle_hours')->default(0);
            $table->integer('expires_within_days')->default(0);
            $table->string('discount_type', 16)->default('percent');
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('max_discount_toman', 15, 2)->nullable();
            $table->integer('code_valid_days')->default(7);
            $table->integer('max_uses_per_user')->default(1);
            $table->text('message_body')->nullable();
            $table->boolean('channel_telegram')->default(true);
            $table->boolean('channel_bale')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::dropIfExists('svp_discount_codes');
        Schema::create('svp_discount_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_svp_user_id')->default(0);
            $table->string('code')->default('');
            $table->boolean('active')->default(true);
            $table->string('discount_type', 16)->default('percent');
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->integer('max_uses')->nullable();
            $table->integer('uses_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedBigInteger('restricted_svp_user_id')->default(0);
            $table->decimal('max_discount_toman', 15, 2)->nullable();
            $table->boolean('allow_new_purchase')->default(true);
            $table->boolean('allow_renew_same')->default(true);
            $table->boolean('allow_add_volume')->default(true);
            $table->boolean('allow_add_user_slots')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->unique(['owner_svp_user_id', 'code'], 'owner_code');
        });

        Schema::dropIfExists('svp_discount_redemptions');
        Schema::create('svp_discount_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discount_id')->default(0);
            $table->unsignedBigInteger('user_id')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_broadcast_queue');
        Schema::create('svp_broadcast_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedBigInteger('user_id');
            $table->string('bot', 8);
            $table->bigInteger('chat_id');
            $table->text('payload_json');
            $table->string('status', 16)->default('pending');
            $table->integer('tries')->default(0);
            $table->text('last_error')->nullable();
            $table->string('failure_kind', 32)->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::dropIfExists('svp_broadcasts');
        Schema::create('svp_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_svp_user_id')->default(0);
            $table->string('type', 16);
            $table->text('content');
            $table->string('status', 20)->default('draft');
            $table->index('owner_svp_user_id', 'owner_svp_user');
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('total_targets')->default(0);
            $table->integer('blocked_count')->default(0);
            $table->text('meta_json')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_texts');
        Schema::create('svp_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key_name');
            $table->string('category', 64)->default('general');
            $table->string('locale', 8)->default('fa');
            $table->text('value')->nullable();
            $table->index('category');
            $table->timestamp('updated_at')->nullable();
            $table->unique(['key_name', 'locale'], 'svp_texts_key_locale');
        });

        Schema::dropIfExists('svp_l2tp_servers');
        Schema::create('svp_l2tp_servers', function (Blueprint $table) {
            $table->id();
            $table->string('label')->default('');
            $table->string('ssh_host')->default('');
            $table->integer('ssh_port')->default(22);
            $table->string('ssh_user')->default('');
            $table->string('ssh_auth', 16)->default('key');
            $table->text('ssh_password_enc')->nullable();
            $table->longText('ssh_private_key_enc')->nullable();
            $table->text('ssh_key_passphrase_enc')->nullable();
            $table->string('l2tp_host')->default('');
            $table->text('l2tp_psk_enc')->nullable();
            $table->string('chap_path')->default('/etc/ppp/chap-secrets');
            $table->string('reload_cmd')->default('sudo /bin/systemctl reload xl2tpd');
            $table->text('usage_cmd_template')->nullable();
            $table->text('apps_note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_reseller_wholesale_lines');
        Schema::create('svp_reseller_wholesale_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id')->default(1);
            $table->unsignedBigInteger('inbound_id')->default(0);
            $table->string('label')->nullable();
            $table->decimal('price_per_gb', 15, 4)->default(0);
            $table->decimal('price_per_day', 15, 4)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_reseller_wholesale_line_assignments');
        Schema::create('svp_reseller_wholesale_line_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_svp_user_id');
            $table->unsignedBigInteger('line_id');
            $table->timestamp('created_at')->nullable();
            $table->unique(['reseller_svp_user_id', 'line_id'], 'reseller_line');
        });

        Schema::dropIfExists('svp_reseller_panel_prices');
        Schema::create('svp_reseller_panel_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_svp_user_id');
            $table->unsignedBigInteger('panel_id');
            $table->decimal('price_per_gb', 15, 4)->default(0);
            $table->boolean('panel_access')->default(true);
            $table->string('default_service_type', 16)->default('xray');
            $table->integer('default_inbound_id')->default(0);
            $table->unsignedBigInteger('default_l2tp_server_id')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['reseller_svp_user_id', 'panel_id'], 'reseller_panel');
        });

        Schema::dropIfExists('svp_reseller_inbound_display_names');
        Schema::create('svp_reseller_inbound_display_names', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_svp_user_id')->default(0);
            $table->unsignedBigInteger('panel_id')->default(0);
            $table->integer('inbound_id')->default(0);
            $table->string('label')->default('');
            $table->unique(['reseller_svp_user_id', 'panel_id', 'inbound_id'], 'reseller_panel_inbound');
        });

        Schema::dropIfExists('svp_reseller_parent_panel_floors');
        Schema::create('svp_reseller_parent_panel_floors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_svp_user_id');
            $table->unsignedBigInteger('child_svp_user_id');
            $table->unsignedBigInteger('panel_id');
            $table->decimal('min_price_per_gb', 15, 4)->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['parent_svp_user_id', 'child_svp_user_id', 'panel_id'], 'parent_child_panel');
        });

        Schema::dropIfExists('svp_reseller_bot_profiles');
        Schema::create('svp_reseller_bot_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_svp_user_id');
            $table->unique('reseller_svp_user_id', 'reseller_one');
            $table->text('telegram_token')->nullable();
            $table->text('bale_token')->nullable();
            $table->string('webhook_secret', 128)->default('');
            $table->string('brand_name')->default('');
            $table->string('telegram_secret_token')->default('');
            $table->boolean('enabled')->default(true);
            $table->boolean('telegram_enabled')->default(true);
            $table->boolean('bale_enabled')->default(true);
            $table->string('telegram_bot_username')->default('');
            $table->string('bale_bot_username')->default('');
            $table->string('telegram_relay_public_url')->default('');
            $table->text('admin_telegram_ids')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::dropIfExists('svp_panel_economics');
        Schema::create('svp_panel_economics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::dropIfExists('svp_panel_economics_lines');
        Schema::create('svp_panel_economics_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id');
            $table->string('label')->default('');
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->date('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_inbound_display_catalog');
        Schema::create('svp_inbound_display_catalog', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id')->default(0);
            $table->integer('inbound_id')->default(0);
            $table->string('label')->default('');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::dropIfExists('svp_panel_inbound_clients');
        Schema::create('svp_panel_inbound_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id');
            $table->integer('inbound_id');
            $table->string('inbound_remark')->default('');
            $table->string('protocol')->default('');
            $table->integer('port')->default(0);
            $table->string('email');
            $table->string('xui_client_id')->default('');
            $table->string('remark')->default('');
            $table->string('comment')->default('');
            $table->string('tg_id')->default('');
            $table->string('sub_id')->default('');
            $table->boolean('enable')->default(true);
            $table->integer('total_gb')->default(0);
            $table->bigInteger('expiry_ms')->default(0);
            $table->bigInteger('used_bytes')->default(0);
            $table->bigInteger('limit_bytes')->default(0);
            $table->boolean('is_online')->default(false);
            $table->longText('client_ips_json')->nullable();
            $table->longText('client_json')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['panel_id', 'inbound_id', 'email'], 'p_i_e');
        });

        Schema::dropIfExists('svp_panel_inbound_api');
        Schema::create('svp_panel_inbound_api', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id');
            $table->integer('inbound_id');
            $table->longText('inbound_json');
            $table->timestamp('synced_at')->nullable();
            $table->unique(['panel_id', 'inbound_id'], 'p_in');
        });

        Schema::dropIfExists('svp_panel_online_daily');
        Schema::create('svp_panel_online_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id');
            $table->date('stat_date');
            $table->integer('max_online')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['panel_id', 'stat_date'], 'panel_stat');
        });

        Schema::dropIfExists('svp_pending_approvals');
        Schema::create('svp_pending_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('platform', 20)->default('bale');
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at')->nullable();
        });

        if (DB::table('dashboard_users')->where('role', 'admin')->count() < 1) {
            DB::table('dashboard_users')->insert([
                'username' => 'admin',
                'password' => Hash::make('secret'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (class_exists(\App\Services\SettingsStore::class)) {
            $settings = app(\App\Services\SettingsStore::class);
            if (trim((string) $settings->get('telegram_bot_token', '')) === '') {
                $settings->set('telegram_bot_token', '1:test-bot-token');
            }
            if (trim((string) $settings->get('bale_token', '')) === '') {
                $settings->set('bale_token', '1:test-bale-token');
            }
        }

        $mainMenuTexts = [
            'btn.main.buy' => 'Buy',
            'btn.main.manage' => 'Manage',
            'btn.main.wallet' => 'Wallet',
            'btn.main.account' => 'Account',
            'btn.main.support' => 'Support',
            'btn.main.apps' => 'Apps',
            'btn.main.referral' => 'Referral',
        ];
        foreach ($mainMenuTexts as $key => $value) {
            if (! DB::table('svp_texts')->where('key_name', $key)->exists()) {
                DB::table('svp_texts')->insert([
                    'key_name' => $key,
                    'value' => $value,
                    'locale' => 'en',
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
