<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpText;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;

class TextsLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsTexts();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if (! $this->tableExists('svp_texts')) {
            return;
        }

        $p = $ctx->page('texts');
        $q = SvpText::query()->orderBy('text_key');
        $total = (clone $q)->count();
        $result->setTotal('texts', $total);

        $result->merge([
            'texts' => $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page'])),
        ]);
    }
}
