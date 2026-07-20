<?php

namespace App\Services\AdminState\Loaders;

use App\Modules\Core\Bot\Services\UiActionRegistryService;
use App\Modules\Core\Bot\Services\UiLayoutStudioService;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;

class BotUiLoader extends AbstractLoader
{
    public function __construct(protected UiLayoutStudioService $layoutStudio) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->activeTab === 'bot_ui';
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if ($ctx->isReseller) {
            $result->merge([
                'uiLayout' => ['version' => 0, 'surfaces' => []],
                'uiRegistry' => ['version' => 0, 'surfaces' => []],
            ]);

            return;
        }

        $result->merge([
            'uiLayout' => $this->layoutStudio->exportMergedForDashboard(),
            'uiRegistry' => UiActionRegistryService::export_for_dashboard(),
        ]);
    }
}
