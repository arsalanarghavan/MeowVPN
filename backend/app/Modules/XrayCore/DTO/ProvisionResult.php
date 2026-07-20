<?php

namespace App\Modules\XrayCore\DTO;

class ProvisionResult
{
    /** @param  array<string, mixed>  $clientData */
    public function __construct(
        public bool $ok,
        public string $reason = '',
        public ?string $detail = null,
        public ?int $serviceId = null,
        public array $clientData = [],
    ) {}

    /** @return array{ok:bool, service_id?:int, reason:string, detail?:string} */
    public function toArray(): array
    {
        $out = ['ok' => $this->ok, 'reason' => $this->reason];
        if ($this->detail !== null) {
            $out['detail'] = $this->detail;
        }
        if ($this->serviceId !== null) {
            $out['service_id'] = $this->serviceId;
        }

        return $out;
    }
}
