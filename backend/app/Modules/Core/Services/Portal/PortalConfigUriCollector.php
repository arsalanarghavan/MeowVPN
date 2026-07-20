<?php

namespace App\Modules\Core\Services\Portal;

use App\Models\SvpUser;
use App\Services\Reseller\ResellerBrandingService;
use App\Modules\XrayCore\Services\SubscriptionUriBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortalConfigUriCollector
{
    public function __construct(
        protected PanelAdminService $panels,
        protected ResellerBrandingService $branding,
        protected ?SubscriptionUriBuilder $nativeUris = null,
    ) {
        if ($this->nativeUris === null && class_exists(SubscriptionUriBuilder::class)) {
            $this->nativeUris = app(SubscriptionUriBuilder::class);
        }
    }

    /**
     * @return array{uris: array<int, string>, userinfo?: string}
     */
    public function collect(SvpUser $user, int $serviceId = 0): array
    {
        if ((string) $user->status !== 'approved') {
            return ['uris' => []];
        }

        if ($serviceId > 0) {
            return $this->collectForService($user, $serviceId);
        }

        return $this->collectMerged($user);
    }

    /**
     * @return array{uris: array<int, string>, userinfo?: string}
     */
    protected function collectForService(SvpUser $user, int $serviceId): array
    {
        $svc = DB::table('svp_services')
            ->where('id', $serviceId)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();
        if (! $svc || $this->isL2tp($svc)) {
            return ['uris' => []];
        }

        $uris = $this->urisFromServiceRow($svc);
        if ($uris === []) {
            return ['uris' => []];
        }
        $remark = (string) ($svc->remark ?: $svc->email ?: '');
        $uris = $this->branding->rewriteSubscriptionUrisForUser($uris, (int) $user->id, $remark, $svc);

        return [
            'uris' => $uris,
            'userinfo' => $this->userinfoFromService($svc),
        ];
    }

    /**
     * @return array{uris: array<int, string>, userinfo?: string}
     */
    protected function collectMerged(SvpUser $user): array
    {
        if (! Schema::hasTable('svp_services')) {
            return ['uris' => []];
        }

        $all = [];
        $userinfo = '';
        $rows = DB::table('svp_services')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->where('service_type', '!=', 'l2tp')
            ->orderBy('id')
            ->get();

        foreach ($rows as $svc) {
            if ($this->isL2tp($svc)) {
                continue;
            }
            $chunk = $this->urisFromServiceRow($svc);
            foreach ($chunk as $u) {
                $all[] = $u;
            }
            if ($userinfo === '' && $chunk !== []) {
                $userinfo = $this->userinfoFromService($svc);
            }
        }

        $uris = array_values(array_unique(array_filter($all)));
        $out = ['uris' => $uris];
        if ($userinfo !== '') {
            $out['userinfo'] = $userinfo;
        }

        return $out;
    }

    /** @return array<int, string> */
    protected function urisFromServiceRow(object $svc): array
    {
        if ((string) ($svc->panel_driver ?? '') === 'native' && $this->nativeUris !== null) {
            $native = $this->nativeUris->buildForService($svc);
            if ($native !== []) {
                return $native;
            }
        }

        $uris = [];
        $payload = $this->panels->portalPayload(
            (int) $svc->id,
            (int) $svc->panel_id,
            (int) $svc->inbound_id,
            (string) $svc->email,
        );
        if (! empty($payload['ok'])) {
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $sub = trim((string) ($data['subscription_url'] ?? ''));
            if ($sub !== '') {
                $uris[] = $sub;
            }
            foreach ((array) ($data['lines'] ?? []) as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $uris[] = $line;
                }
            }
        }

        $uris = array_merge($uris, $this->urisFromClientCache($svc));

        return array_values(array_unique(array_filter($uris)));
    }

    /** @return array<int, string> */
    protected function urisFromClientCache(object $svc): array
    {
        if (! Schema::hasTable('svp_panel_inbound_clients')) {
            return [];
        }

        $row = DB::table('svp_panel_inbound_clients')
            ->where('panel_id', (int) $svc->panel_id)
            ->where('inbound_id', (int) $svc->inbound_id)
            ->where('email', (string) $svc->email)
            ->first();
        if (! $row || empty($row->client_json)) {
            return [];
        }

        $decoded = json_decode((string) $row->client_json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $uris = [];
        foreach ($decoded as $key => $val) {
            if (! is_string($val)) {
                continue;
            }
            $val = trim($val);
            if (preg_match('#^(vless|vmess|trojan|ss)://#i', $val)) {
                $uris[] = $val;
            }
        }

        return $uris;
    }

    protected function userinfoFromService(object $svc): string
    {
        $down = (int) ($svc->used_traffic ?? 0);
        $total = (int) ($svc->total_traffic ?? 0);
        $exp = 0;
        if (! empty($svc->expires_at)) {
            $exp = (int) strtotime((string) $svc->expires_at.' UTC');
        }

        return sprintf('upload=%d; download=%d; total=%d; expire=%d', 0, $down, $total, $exp);
    }

    protected function isL2tp(object $svc): bool
    {
        return (string) ($svc->service_type ?? 'xray') === 'l2tp';
    }
}
