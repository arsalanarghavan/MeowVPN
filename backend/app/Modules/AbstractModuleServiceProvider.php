<?php

namespace App\Modules;

use App\Services\MutationRegistry;
use Illuminate\Support\ServiceProvider;

abstract class AbstractModuleServiceProvider extends ServiceProvider
{
    abstract public function moduleKey(): string;

    /** @return array<string, callable|array{0: class-string, 1: string}> */
    public function mutationHandlers(): array
    {
        return [];
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $key = $this->moduleKey();
        if (! svp_modules()->isEnabled($key)) {
            return;
        }

        try {
            $handlers = $this->mutationHandlers();
            if ($handlers !== []) {
                $this->app->make(MutationRegistry::class)->registerMany($handlers);
            }

            $this->bootEnabled();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Module boot failed [{$key}]: ".$e->getMessage(), 0, $e);
        }
    }

    protected function bootEnabled(): void
    {
        //
    }
}
