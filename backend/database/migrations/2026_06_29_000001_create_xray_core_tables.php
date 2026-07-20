<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('svp_xray_nodes')) {
            Schema::create('svp_xray_nodes', function (Blueprint $table) {
                $table->id();
                $table->string('label', 191)->default('');
                $table->string('public_ip', 64)->default('');
                $table->text('agent_url');
                $table->text('agent_ca_cert')->nullable();
                $table->text('agent_client_cert')->nullable();
                $table->text('agent_client_key')->nullable();
                $table->string('xray_config_path', 512)->default('/etc/xray/config.json');
                $table->string('xray_binary', 512)->default('/usr/local/bin/xray');
                $table->text('base_config_json')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('last_health_at')->nullable();
                $table->string('last_health_status', 32)->default('');
                $table->timestamps();
                $table->index(['active', 'is_primary']);
            });
        }

        if (! Schema::hasTable('svp_xray_inbounds')) {
            Schema::create('svp_xray_inbounds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('node_id');
                $table->string('tag', 64);
                $table->string('remark', 255)->default('');
                $table->string('protocol', 32);
                $table->unsignedInteger('port');
                $table->longText('settings_json')->nullable();
                $table->longText('stream_settings_json')->nullable();
                $table->longText('sniffing_json')->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['node_id', 'tag']);
                $table->index(['node_id', 'active']);
            });
        }

        if (! Schema::hasTable('svp_xray_hosts')) {
            Schema::create('svp_xray_hosts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inbound_id');
                $table->string('remark', 255)->default('');
                $table->string('address', 255);
                $table->unsignedInteger('port')->default(0);
                $table->string('sni', 255)->default('');
                $table->string('host', 255)->default('');
                $table->string('path', 512)->default('');
                $table->string('fingerprint', 64)->default('');
                $table->boolean('allow_insecure')->default(false);
                $table->unsignedInteger('priority')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->index(['inbound_id', 'active']);
            });
        }

        if (! Schema::hasTable('svp_tunnel_endpoints')) {
            Schema::create('svp_tunnel_endpoints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('node_id');
                $table->string('label', 191)->default('');
                $table->string('provider', 32);
                $table->string('public_ip', 64)->default('');
                $table->string('ssh_host', 255)->default('');
                $table->unsignedInteger('ssh_port')->default(22);
                $table->string('ssh_user', 64)->default('root');
                $table->string('ssh_auth', 16)->default('key');
                $table->text('ssh_password')->nullable();
                $table->text('ssh_private_key')->nullable();
                $table->text('ssh_key_passphrase')->nullable();
                $table->longText('config_json')->nullable();
                $table->longText('deployed_config')->nullable();
                $table->string('health_status', 32)->default('');
                $table->timestamp('last_deploy_at')->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['node_id', 'active']);
            });
        }

        if (Schema::hasTable('svp_plans')) {
            Schema::table('svp_plans', function (Blueprint $table) {
                if (! Schema::hasColumn('svp_plans', 'panel_driver')) {
                    $table->string('panel_driver', 16)->default('xui')->after('panel_id');
                }
                if (! Schema::hasColumn('svp_plans', 'xray_inbound_ref')) {
                    $table->unsignedBigInteger('xray_inbound_ref')->nullable()->after('inbound_id');
                }
            });
        }

        if (Schema::hasTable('svp_services')) {
            Schema::table('svp_services', function (Blueprint $table) {
                if (! Schema::hasColumn('svp_services', 'panel_driver')) {
                    $table->string('panel_driver', 16)->default('xui')->after('panel_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('svp_services') && Schema::hasColumn('svp_services', 'panel_driver')) {
            Schema::table('svp_services', fn (Blueprint $t) => $t->dropColumn('panel_driver'));
        }
        if (Schema::hasTable('svp_plans')) {
            Schema::table('svp_plans', function (Blueprint $table) {
                if (Schema::hasColumn('svp_plans', 'panel_driver')) {
                    $table->dropColumn('panel_driver');
                }
                if (Schema::hasColumn('svp_plans', 'xray_inbound_ref')) {
                    $table->dropColumn('xray_inbound_ref');
                }
            });
        }
        Schema::dropIfExists('svp_tunnel_endpoints');
        Schema::dropIfExists('svp_xray_hosts');
        Schema::dropIfExists('svp_xray_inbounds');
        Schema::dropIfExists('svp_xray_nodes');
    }
};
