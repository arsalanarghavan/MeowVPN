<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminXrayHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected BotAdminMutateService $mutate,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        if ($tabKey !== 'vpn_server') {
            return;
        }

        $overview = $this->mutate->applyForUser($user, 'vpn_server_overview', []);
        $node = is_array($overview['data']['node'] ?? null) ? $overview['data']['node'] : [];
        $health = is_array($overview['data']['health'] ?? null) ? $overview['data']['health'] : [];
        $status = (string) ($health['status'] ?? $node['last_health_status'] ?? 'unknown');
        $publicIp = (string) ($node['public_ip'] ?? '—');
        $inboundCount = (int) ($overview['data']['inbound_count'] ?? 0);
        $clientCount = (int) ($overview['data']['client_count'] ?? 0);

        $body = "VPN Server (local Xray)\n";
        $body .= "Public IP: {$publicIp}\n";
        $body .= "Health: {$status}\n";
        $body .= "Inbounds: {$inboundCount} · Native clients: {$clientCount}\n";

        if (Schema::hasTable('svp_xray_inbounds')) {
            $rows = DB::table('svp_xray_inbounds')->where('active', 1)->orderBy('sort_order')->orderBy('id')->limit(12)->get();
            if ($rows->isNotEmpty()) {
                $body .= "\nInbounds:\n";
                foreach ($rows as $r) {
                    $body .= '• '.(string) ($r->tag ?? '').' '.(string) ($r->protocol ?? '').':'.(int) ($r->port ?? 0)."\n";
                }
            }
        }

        if (Schema::hasTable('svp_tunnel_endpoints')) {
            $tunnels = (int) DB::table('svp_tunnel_endpoints')->where('active', 1)->count();
            $body .= "\nEdge tunnels: {$tunnels}";
        }

        $ik = [[
            ['text' => '🩺 Health', 'callback_data' => 'pnl:xray:h'],
            ['text' => '🔄 Restart', 'callback_data' => 'pnl:xray:r'],
            ['text' => '📤 Apply', 'callback_data' => 'pnl:xray:a'],
        ]];

        $this->send($ctx, $chatId, $body, ['reply_markup' => ['inline_keyboard' => $ik]]);
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $action = (string) ($parts[2] ?? '');
        $op = match ($action) {
            'h' => 'vpn_server_health',
            'r' => 'vpn_server_restart',
            'a' => 'vpn_server_apply',
            default => '',
        };
        if ($op === '') {
            return;
        }
        $result = $this->mutate->applyForUser($user, $op, []);
        $this->send($ctx, $chatId, $this->mutateResult($user, $result));
    }

    /** @param  array<string, mixed>  $result */
    protected function mutateResult(SvpUser $user, array $result): string
    {
        $msg = $this->mutate->resultMessage($user, $result);
        if (! empty($result['ok']) && ! empty($result['data']) && is_array($result['data'])) {
            $snippet = mb_substr(json_encode($result['data'], JSON_UNESCAPED_UNICODE) ?: '', 0, 500);
            if ($snippet !== '') {
                $msg .= "\n".$snippet;
            }
        }

        return $msg;
    }
}
