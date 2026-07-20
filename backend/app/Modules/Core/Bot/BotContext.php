<?php

namespace App\Modules\Core\Bot;

use App\Models\SvpUser;

class BotContext
{
    public ?SvpUser $user = null;

    /**
     * @param  array<string, mixed>|null  $resellerProfile
     * @param  array<string, mixed>|null  $mirrorProfile
     */
    public function __construct(
        public string $platform,
        public int $resellerSvpUserId = 0,
        public ?array $resellerProfile = null,
        public int $mirrorBotId = 0,
        public ?array $mirrorProfile = null,
    ) {}

    public function isResellerBot(): bool
    {
        return $this->resellerSvpUserId > 0 && ! $this->isMirrorBot();
    }

    public function isMirrorBot(): bool
    {
        return $this->mirrorBotId > 0;
    }

    public function brandName(): string
    {
        if (! $this->isResellerBot()) {
            return '';
        }

        return trim((string) ($this->resellerProfile['brand_name'] ?? ''));
    }

    public function reset(): void
    {
        $this->user = null;
    }
}
