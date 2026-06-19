<?php

namespace App\Services;

use App\Services\Mutations\MutationPipeline;
use Illuminate\Contracts\Auth\Authenticatable;

class MutationDispatcher
{
    public function __construct(protected MutationPipeline $pipeline) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result: array<string, mixed>, http_status: int}
     */
    public function dispatch(string $op, array $payload, ?Authenticatable $actor): array
    {
        return $this->pipeline->dispatch($op, $payload, $actor);
    }
}
