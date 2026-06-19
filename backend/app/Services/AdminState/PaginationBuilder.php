<?php

namespace App\Services\AdminState;

class PaginationBuilder
{
    /** @return array<string, array{page: int, perPage: int, total: int}> */
    public function build(AdminStateContext $ctx, AdminStateResult $result): array
    {
        $out = [];
        foreach (ListPagination::listDefinitions() as $prefix => $def) {
            $p = $ctx->page($prefix);
            $key = $def['paginationKey'];
            $total = $result->totals[$prefix] ?? 0;
            $out[$key] = ListPagination::meta($p['page'], $p['per_page'], $total);
        }

        return $out;
    }
}
