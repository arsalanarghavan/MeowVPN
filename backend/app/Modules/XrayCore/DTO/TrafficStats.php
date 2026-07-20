<?php

namespace App\Modules\XrayCore\DTO;

class TrafficStats
{
    public function __construct(
        public int $upload = 0,
        public int $download = 0,
        public int $total = 0,
    ) {}

    public function usedBytes(): int
    {
        return $this->upload + $this->download;
    }
}
