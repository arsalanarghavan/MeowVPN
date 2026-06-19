<?php

namespace App\Models\Concerns;

trait SvpTable
{
    public function initializeSvpTable(): void
    {
        $this->timestamps = false;
    }
}
