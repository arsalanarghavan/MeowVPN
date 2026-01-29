<?php

namespace App\Services;

use App\Models\Server;
use InvalidArgumentException;

/**
 * Factory for creating VPN Panel service instances based on server configuration
 */
class VpnPanelFactory
{
    public function __construct(
        private MarzbanService $marzbanService,
        private HiddifyService $hiddifyService
    ) {}

    /**
     * Get the appropriate VPN panel service for a server
     *
     * @param Server $server The server to get the service for
     * @return VpnPanelInterface
     * @throws InvalidArgumentException If panel type is not supported
     */
    public function make(Server $server): VpnPanelInterface
    {
        return match ($server->panel_type) {
            'hiddify' => $this->hiddifyService,
            'marzban' => $this->marzbanService,
            default => throw new InvalidArgumentException("Unsupported panel type: {$server->panel_type}"),
        };
    }

    /**
     * Get the Marzban service directly
     *
     * @return MarzbanService
     */
    public function marzban(): MarzbanService
    {
        return $this->marzbanService;
    }

    /**
     * Get the Hiddify service directly
     *
     * @return HiddifyService
     */
    public function hiddify(): HiddifyService
    {
        return $this->hiddifyService;
    }

    /**
     * Check if a panel type is supported
     *
     * @param string $panelType
     * @return bool
     */
    public function isSupported(string $panelType): bool
    {
        return in_array($panelType, ['marzban', 'hiddify']);
    }

    /**
     * Get list of supported panel types
     *
     * @return array
     */
    public function getSupportedTypes(): array
    {
        return [
            'marzban' => 'Marzban',
            'hiddify' => 'Hiddify',
        ];
    }
}

