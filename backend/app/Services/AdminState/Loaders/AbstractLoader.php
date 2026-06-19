<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminRowFormatter;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

abstract class AbstractLoader
{
    public function loadIfNeeded(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if ($this->shouldLoad($ctx)) {
            $this->load($ctx, $result);
        }
    }

    abstract protected function shouldLoad(AdminStateContext $ctx): bool;

    abstract protected function load(AdminStateContext $ctx, AdminStateResult $result): void;

    protected function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function fetchRows(Builder $query): array
    {
        return $query->get()
            ->map(fn ($row) => AdminRowFormatter::rowArray($row))
            ->all();
    }
}
