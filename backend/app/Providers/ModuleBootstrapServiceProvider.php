<?php

namespace App\Providers;

use App\Modules\ModuleManager;
use Illuminate\Support\ServiceProvider;

class ModuleBootstrapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class);

        $manager = $this->app->make(ModuleManager::class);
        $modules = config('modules.modules', []);

        foreach ($manager->bootOrder() as $key) {
            $module = $modules[$key] ?? null;
            if (! is_array($module)) {
                continue;
            }
            $provider = $module['provider'] ?? null;
            if (is_string($provider) && class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
