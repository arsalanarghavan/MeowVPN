<?php

namespace App\Modules\Tunnel\Contracts;

interface TunnelProviderInterface
{
    public function providerKey(): string;

    /** @param  array<string, mixed>  $endpoint  */
    public function renderConfig(array $endpoint): string;

    /** @param  array<string, mixed>  $endpoint  */
    public function deployRemote(array $endpoint): array;
}
