<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\SubscriptionLink;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ServerSelectionService;
use App\Services\VpnPanelFactory;
use App\Services\MultiServerProvisioningService;
use App\Services\AffiliateCommissionService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Exception;

class SubscriptionController extends Controller
{
    public function __construct(
        private ServerSelectionService $serverSelectionService,
        private VpnPanelFactory $panelFactory,
        private MultiServerProvisioningService $multiServerService,
        private AffiliateCommissionService $affiliateCommissionService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Subscription::where('user_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Return direct array for bot compatibility (regular user flow expects array)
        // Reseller code that expects paginated response should be updated in bot
        return response()->json($query->with(['server', 'plan'])->get());
    }

    public function show(Subscription $subscription)
    {
        $this->authorize('view', $subscription);

        $data = $subscription->load(['server', 'subscriptionLinks', 'plan']);
        
        // Add multi-server stats if applicable
        if ($subscription->isMultiServer()) {
            $data->multi_server_stats = $this->multiServerService->getAggregatedStats($subscription);
        }

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'location_tag' => 'nullable|string',
            'server_category' => 'nullable|string|in:tunnel_entry,tunnel_exit,direct',
            'server_ids' => 'nullable|array',
            'server_ids.*' => 'exists:servers,id',
            'max_devices' => 'nullable|integer|min:1|max:10',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $user = $request->user();

        // Check user balance
        $price = $plan->price_base;
        if ($user->wallet_balance < $price) {
            return response()->json(['error' => 'موجودی کیف پول کافی نیست', 'required' => $price], 400);
        }

        $maxDevices = $data['max_devices'] ?? $plan->max_devices ?? 1;

        try {
            return DB::transaction(function () use ($user, $plan, $data, $maxDevices) {
                $price = $plan->price_base;
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                if (!$lockedUser || $lockedUser->wallet_balance < $price) {
                    throw new Exception('موجودی کیف پول کافی نیست');
                }
                $lockedUser->decrement('wallet_balance', $price);

                // Create transaction record
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'amount' => -$plan->price_base,
                    'type' => 'purchase',
                    'gateway' => 'wallet',
                    'status' => 'completed',
                    'description' => "خرید پلن {$plan->name}",
                ]);

                // Check if multi-server
                if (!empty($data['server_ids'])) {
                    $subscription = $this->multiServerService->createMultiServerSubscription(
                        $user->id,
                        $plan->id,
                        $data['server_ids'],
                        $maxDevices
                    );
                } else {
                    // Single server: select by location_tag and optionally server_category (direct, tunnel_entry, tunnel_exit)
                    $server = $this->serverSelectionService->selectBestServer(
                        $data['location_tag'] ?? 'DE',
                        $data['server_category'] ?? null
                    );

                    if (!$server) {
                        throw new Exception('سرور در دسترس نیست');
                    }

                    $subscription = $this->createSingleServerSubscription($user, $plan, $server, $maxDevices);
                }

                // Link transaction to subscription
                $transaction->update(['subscription_id' => $subscription->id]);

                // Add affiliate commission if user has a referrer
                if ($user->parent_id && $user->parent) {
                    $this->affiliateCommissionService->addCommission($user->parent, $plan->price_base);
                }

                return response()->json($subscription->load(['server', 'subscriptionLinks']), 201);
            });
        } catch (Exception $e) {
            Log::error('Subscription creation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function createSingleServerSubscription($user, Plan $plan, Server $server, int $maxDevices = 1)
    {
        $username = 'meow_' . Str::random(12);
        $uuid = (string) Str::uuid();

        $panelService = $this->panelFactory->make($server);

        $userData = [
            'username' => $username,
            'uuid' => $uuid,
            'traffic_limit' => $plan->traffic_bytes > 0 ? $plan->traffic_bytes : 0,
            'expire_timestamp' => $plan->duration_days > 0 ? now()->addDays($plan->duration_days)->timestamp : null,
            'max_devices' => $maxDevices,
        ];

        // For Marzban, add proxy configuration
        if ($server->isMarzban()) {
            $userData['proxies'] = [
                'vless' => [
                    'id' => $uuid,
                    'flow' => 'xtls-rprx-vision',
                ],
            ];
            $userData['inbounds'] = [
                'vless' => ['VLESS TCP REALITY', 'VLESS_TCP'],
            ];
            $userData['data_limit'] = $plan->traffic_bytes > 0 ? $plan->traffic_bytes : 0;
            $userData['expire'] = $plan->duration_days > 0 ? now()->addDays($plan->duration_days)->timestamp : null;
            $userData['data_limit_reset_strategy'] = 'no_reset';
        }

        $result = $panelService->createUser($server, $userData);

        if (!$result) {
            throw new Exception('خطا در ایجاد کاربر روی سرور');
        }

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'server_id' => $server->id,
            'uuid' => $uuid,
            'marzban_username' => $username,
            'panel_username' => $server->isHiddify() ? $uuid : $username,
            'status' => 'active',
            'total_traffic' => $plan->traffic_bytes,
            'used_traffic' => 0,
            'expire_date' => $plan->duration_days > 0 ? now()->addDays($plan->duration_days) : null,
            'max_devices' => $maxDevices,
        ]);

        // Store subscription links
        $this->storeSubscriptionLinks($subscription, $server, $result, $uuid);

        return $subscription;
    }

    /**
     * Store subscription links from panel response
     */
    private function storeSubscriptionLinks(Subscription $subscription, Server $server, array $result, string $uuid): void
    {
        $linksStored = false;

        if (isset($result['links'])) {
            if (is_array($result['links'])) {
                foreach ($result['links'] as $protocol => $links) {
                    if (is_array($links)) {
                        foreach ($links as $link) {
                            if (is_string($link)) {
                                SubscriptionLink::create([
                                    'subscription_id' => $subscription->id,
                                    'server_id' => $server->id,
                                    'vless_link' => $link,
                                ]);
                                $linksStored = true;
                            }
                        }
                    } elseif (is_string($links)) {
                        SubscriptionLink::create([
                            'subscription_id' => $subscription->id,
                            'server_id' => $server->id,
                            'vless_link' => $links,
                        ]);
                        $linksStored = true;
                    }
                }
            }
        }

        if (!$linksStored) {
            // Generate link manually
            $link = $this->generateSubscriptionLinkForServer($server, $uuid, $subscription->marzban_username);
            SubscriptionLink::create([
                'subscription_id' => $subscription->id,
                'server_id' => $server->id,
                'vless_link' => $link,
            ]);
        }
    }

    /**
     * Generate subscription link for a specific server
     */
    private function generateSubscriptionLinkForServer(Server $server, string $uuid, string $username): string
    {
        if ($server->isHiddify()) {
            return "https://{$server->api_domain}/{$uuid}/all.txt";
        }

        // Marzban VLESS link format
        $domain = $server->api_domain;
        $serverName = $server->name;
        
        return sprintf(
            "vless://%s@%s:443?type=tcp&security=reality&pbk=%s&fp=chrome&sni=%s&sid=%s&spx=%%2F#%s",
            $uuid,
            $domain,
            config('services.marzban.reality_public_key', ''),
            config('services.marzban.reality_sni', $domain),
            config('services.marzban.reality_short_id', ''),
            urlencode($serverName . ' - MeowVPN')
        );
    }

    public function getSubscriptionLink(string $uuid)
    {
        $subscription = Subscription::where('uuid', $uuid)->firstOrFail();

        // Check if subscription is active
        if ($subscription->status !== 'active') {
            return response("# Subscription is {$subscription->status}", 200, ['Content-Type' => 'text/plain']);
        }

        if ($subscription->server_id) {
            // Single server
            $storedLink = $subscription->subscriptionLinks()->first();
            
            if ($storedLink && $storedLink->vless_link) {
                return response($storedLink->vless_link, 200, ['Content-Type' => 'text/plain']);
            }

            // Try to get from panel
            $server = $subscription->server;
            $link = $this->generateSubscriptionLinkForServer($server, $uuid, $subscription->marzban_username);
            return response($link, 200, ['Content-Type' => 'text/plain']);
        } else {
            // Multi-server - return all links
            $links = $subscription->subscriptionLinks()->pluck('vless_link')->filter()->join("\n");
            
            if (empty($links)) {
                return response("# No links available", 200, ['Content-Type' => 'text/plain']);
            }
            
            return response($links, 200, ['Content-Type' => 'text/plain']);
        }
    }

    public function getQR(Subscription $subscription)
    {
        $this->authorize('view', $subscription);

        $storedLink = $subscription->subscriptionLinks()->first()?->vless_link;
        if ($storedLink) {
            $link = $storedLink;
        } elseif ($subscription->server) {
            $link = $this->generateSubscriptionLinkForServer(
                $subscription->server,
                $subscription->uuid,
                $subscription->marzban_username
            );
        } else {
            return response()->json(['error' => 'لینک اشتراک در دسترس نیست'], 404);
        }

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );
        
        $writer = new Writer($renderer);
        $qrCode = $writer->writeString($link);

        return response($qrCode, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function renew(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        $data = $request->validate([
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        $plan = isset($data['plan_id']) 
            ? Plan::findOrFail($data['plan_id']) 
            : $subscription->plan;

        if (!$plan) {
            return response()->json(['error' => 'پلن مشخص نشده است'], 400);
        }

        $user = $request->user();

        if ($user->wallet_balance < $plan->price_base) {
            return response()->json([
                'error' => 'موجودی کیف پول کافی نیست',
                'required' => $plan->price_base,
                'balance' => $user->wallet_balance,
            ], 400);
        }

        try {
            return DB::transaction(function () use ($user, $plan, $subscription) {
                $price = $plan->price_base;
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                if (!$lockedUser || $lockedUser->wallet_balance < $price) {
                    throw new Exception('موجودی کیف پول کافی نیست');
                }
                $lockedUser->decrement('wallet_balance', $price);

                Transaction::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'amount' => -$plan->price_base,
                    'type' => 'renewal',
                    'gateway' => 'wallet',
                    'status' => 'completed',
                    'description' => "تمدید سرویس #{$subscription->id}",
                ]);

                // Calculate new expiry - add days to existing expiry if still active
                $newExpireDate = $subscription->expire_date && $subscription->expire_date->isFuture()
                    ? $subscription->expire_date->copy()->addDays($plan->duration_days)
                    : now()->addDays($plan->duration_days);

                // Calculate new traffic
                $newTraffic = $subscription->total_traffic + $plan->traffic_bytes;

                // Handle multi-server vs single server renewal
                if ($subscription->isMultiServer()) {
                    // Renew on all servers
                    $this->multiServerService->renewMultiServerSubscription(
                        $subscription,
                        $plan->duration_days,
                        $plan->traffic_bytes
                    );
                } elseif ($subscription->server) {
                    // Single server renewal
                    $panelService = $this->panelFactory->make($subscription->server);
                    $identifier = $subscription->server->isHiddify() 
                        ? ($subscription->panel_username ?? $subscription->uuid)
                        : $subscription->marzban_username;

                    $updateData = [
                        'data_limit' => $plan->traffic_bytes > 0 ? $newTraffic : 0,
                        'expire' => $plan->duration_days > 0 ? $newExpireDate->timestamp : null,
                        'status' => 'active',
                        'traffic_limit' => $plan->traffic_bytes > 0 ? $newTraffic : 0,
                    ];

                    $result = $panelService->updateUser($subscription->server, $identifier, $updateData);

                    if (!$result) {
                        throw new Exception('خطا در به‌روزرسانی سرویس روی سرور');
                    }
                }

                $subscription->update([
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'total_traffic' => $newTraffic,
                    'expire_date' => $newExpireDate,
                ]);

                if ($user->parent_id && $user->parent) {
                    $this->affiliateCommissionService->addCommission($user->parent, $plan->price_base);
                }

                return response()->json([
                    'message' => 'سرویس با موفقیت تمدید شد',
                    'subscription' => $subscription->fresh()->load(['server', 'plan']),
                ]);
            });
        } catch (Exception $e) {
            Log::error('Subscription renewal failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function changeLocation(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        // Multi-server subscriptions can't change location
        if ($subscription->isMultiServer()) {
            return response()->json(['error' => 'امکان تغییر لوکیشن برای سرویس مولتی سرور وجود ندارد'], 400);
        }

        $data = $request->validate([
            'location_tag' => 'required|string',
        ]);

        try {
            return DB::transaction(function () use ($subscription, $data) {
                $oldServer = $subscription->server;
                $serverCategory = $oldServer?->server_category;
                $newServer = $this->serverSelectionService->selectBestServer($data['location_tag'], $serverCategory);

                if (!$newServer) {
                    return response()->json(['error' => 'سرور در لوکیشن مورد نظر در دسترس نیست'], 400);
                }

                if ($newServer->id === $subscription->server_id) {
                    return response()->json(['error' => 'لوکیشن انتخاب شده همان لوکیشن فعلی است'], 400);
                }

                // Delete from old server
                if ($oldServer) {
                    $oldPanelService = $this->panelFactory->make($oldServer);
                    $oldIdentifier = $oldServer->isHiddify() 
                        ? ($subscription->panel_username ?? $subscription->uuid)
                        : $subscription->marzban_username;
                    $oldPanelService->deleteUser($oldServer, $oldIdentifier);
                }

                // Create on new server
                $newPanelService = $this->panelFactory->make($newServer);
                
                $userData = [
                    'username' => $subscription->marzban_username,
                    'uuid' => $subscription->uuid,
                    'traffic_limit' => $subscription->total_traffic - $subscription->used_traffic,
                    'expire_timestamp' => $subscription->expire_date?->timestamp,
                    'max_devices' => $subscription->max_devices ?? 1,
                ];

                if ($newServer->isMarzban()) {
                    $userData['proxies'] = [
                        'vless' => [
                            'id' => $subscription->uuid,
                            'flow' => 'xtls-rprx-vision',
                        ],
                    ];
                    $userData['inbounds'] = [
                        'vless' => ['VLESS TCP REALITY', 'VLESS_TCP'],
                    ];
                    $userData['data_limit'] = $subscription->total_traffic - $subscription->used_traffic;
                    $userData['expire'] = $subscription->expire_date?->timestamp;
                    $userData['data_limit_reset_strategy'] = 'no_reset';
                }

                $result = $newPanelService->createUser($newServer, $userData);

                if (!$result) {
                    // Try to restore on old server
                    if ($oldServer) {
                        $oldPanelService->createUser($oldServer, $userData);
                    }
                    throw new Exception('خطا در انتقال به سرور جدید');
                }

                // Update subscription
                $subscription->update([
                    'server_id' => $newServer->id,
                    'panel_username' => $newServer->isHiddify() ? $subscription->uuid : $subscription->marzban_username,
                ]);

                // Update subscription links
                $subscription->subscriptionLinks()->delete();
                $this->storeSubscriptionLinks($subscription, $newServer, $result, $subscription->uuid);

                return response()->json([
                    'message' => 'لوکیشن با موفقیت تغییر کرد',
                    'subscription' => $subscription->fresh()->load(['server', 'subscriptionLinks']),
                ]);
            });
        } catch (Exception $e) {
            Log::error('Location change failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Subscription $subscription)
    {
        $this->authorize('delete', $subscription);

        try {
            if ($subscription->isMultiServer()) {
                // Delete from all servers
                $this->multiServerService->deleteFromAllServers($subscription);
            } elseif ($subscription->server) {
                // Delete from single server
                $panelService = $this->panelFactory->make($subscription->server);
                $identifier = $subscription->server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;
                $panelService->deleteUser($subscription->server, $identifier);
            }

            $subscription->subscriptionLinks()->delete();
            $subscription->delete();

            return response()->json(['message' => 'سرویس با موفقیت حذف شد']);
        } catch (Exception $e) {
            Log::error('Subscription deletion failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync subscription with panel server(s)
     */
    public function sync(Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        try {
            if ($subscription->isMultiServer()) {
                // Sync traffic across all servers
                $this->multiServerService->syncTrafficAcrossServers($subscription);
                $stats = $this->multiServerService->getAggregatedStats($subscription);

                return response()->json([
                    'message' => 'اطلاعات همگام‌سازی شد',
                    'subscription' => $subscription->fresh()->load(['server', 'plan']),
                    'multi_server_stats' => $stats,
                ]);
            }

            if (!$subscription->server) {
                return response()->json(['error' => 'سرویس به سروری متصل نیست'], 400);
            }

            $panelService = $this->panelFactory->make($subscription->server);
            $identifier = $subscription->server->isHiddify() 
                ? ($subscription->panel_username ?? $subscription->uuid)
                : $subscription->marzban_username;

            $stats = $panelService->getUserStats($subscription->server, $identifier);

            if (!$stats) {
                return response()->json(['error' => 'خطا در دریافت اطلاعات از سرور'], 500);
            }

            $subscription->update([
                'used_traffic' => $stats['used_traffic'] ?? $subscription->used_traffic,
                'status' => $stats['status'] ?? $subscription->status,
            ]);

            return response()->json([
                'message' => 'اطلاعات همگام‌سازی شد',
                'subscription' => $subscription->fresh()->load(['server', 'plan']),
                'server_stats' => $stats,
            ]);
        } catch (Exception $e) {
            Log::error('Subscription sync failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update max devices for a subscription
     */
    public function updateMaxDevices(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        $data = $request->validate([
            'max_devices' => 'required|integer|min:1|max:10',
        ]);

        try {
            if ($subscription->isMultiServer()) {
                $success = $this->multiServerService->updateMaxDevicesOnAllServers(
                    $subscription, 
                    $data['max_devices']
                );
            } elseif ($subscription->server) {
                $panelService = $this->panelFactory->make($subscription->server);
                $identifier = $subscription->server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                $result = $panelService->updateUser($subscription->server, $identifier, [
                    'max_devices' => $data['max_devices'],
                ]);
                $success = $result !== null;

                if ($success) {
                    $subscription->update(['max_devices' => $data['max_devices']]);
                }
            } else {
                $subscription->update(['max_devices' => $data['max_devices']]);
                $success = true;
            }

            if (!$success) {
                return response()->json(['error' => 'خطا در به‌روزرسانی تعداد دستگاه'], 500);
            }

            return response()->json([
                'message' => 'تعداد دستگاه با موفقیت به‌روزرسانی شد',
                'subscription' => $subscription->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Max devices update failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enable subscription on all servers
     */
    public function enable(Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        try {
            if ($subscription->isMultiServer()) {
                $this->multiServerService->enableOnAllServers($subscription);
            } elseif ($subscription->server) {
                $panelService = $this->panelFactory->make($subscription->server);
                $identifier = $subscription->server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;
                $panelService->enableUser($subscription->server, $identifier);
            }

            $subscription->update(['status' => 'active']);

            return response()->json([
                'message' => 'سرویس فعال شد',
                'subscription' => $subscription->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Subscription enable failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Disable subscription on all servers
     */
    public function disable(Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        try {
            if ($subscription->isMultiServer()) {
                $this->multiServerService->disableOnAllServers($subscription);
            } elseif ($subscription->server) {
                $panelService = $this->panelFactory->make($subscription->server);
                $identifier = $subscription->server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;
                $panelService->disableUser($subscription->server, $identifier);
            }

            $subscription->update(['status' => 'disabled']);

            return response()->json([
                'message' => 'سرویس غیرفعال شد',
                'subscription' => $subscription->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Subscription disable failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
