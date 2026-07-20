<?php

namespace App\Modules\PasarGuard\Contracts;

/**
 * Shared surface used by PanelClientFactory callers (XUI + PasarGuard).
 * Both clients expose these methods; factory returns the bound driver instance.
 */
interface PanelClientInterface
{
    /** @param  array<string, mixed>  $panel */
    public function runWithPanel(int $panelId, callable $fn, array $panel = []): mixed;

    public function loginWithRetries(int $maxAttempts = 6, int $delayUs = 350000): bool;

    public function clearSession(): void;

    public function isV3ClientsApi(): bool;

    /** @param  array<string, mixed>  $panel */
    public function testConnection(array $panel): array;

    /** @return array<int, array<string, mixed>>|null */
    public function inboundsList(): ?array;

    /** @return array<string, mixed>|null */
    public function inboundGet(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function clientGetV3(string $email): ?array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool, code:int, json:array|null, body:string}
     */
    public function addClientRequest(array $payload): array;

    public function addClientRequestOk(array $requestResult): bool;

    public function panelJsonMsg(mixed $json): string;

    public function delClient(int $inboundId, string $clientId, string $emailFallback = ''): mixed;

    /** @param  array<string, mixed>  $panel */
    public function syncService(array $panel, int $serviceId): array;

    /** @param  array<string, mixed>  $svc */
    public function syncServiceRowToPanel(array $svc): array;

    /** @param  array<string, mixed>  $panel */
    public function deleteClient(array $panel, int $serviceId): array;
}
