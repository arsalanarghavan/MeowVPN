<?php

namespace App\Services\AdminState;

class AdminStateResult
{
    /** @var array<string, int> */
    public array $totals = [];

    /** @param  array<string, mixed>  $data */
    public function __construct(public array $data = [])
    {
        $this->data = array_merge(PayloadDefaults::root(), $data);
    }

    /** @param  array<string, mixed>  $slice */
    public function merge(array $slice): void
    {
        $this->data = array_merge($this->data, $slice);
    }

    public function setTotal(string $listKey, int $total): void
    {
        $this->totals[$listKey] = $total;
    }
}
