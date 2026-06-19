<?php

namespace Tests\Concerns;

use App\Modules\ModuleManager;

trait TogglesModules
{
    protected function setModuleEnabled(string $key, bool $enabled): void
    {
        config(["modules.modules.{$key}.enabled" => $enabled]);
        $this->app->forgetInstance(ModuleManager::class);
    }
}
