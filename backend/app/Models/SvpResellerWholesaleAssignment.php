<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpResellerWholesaleAssignment extends Model
{
    use SvpTable;

    protected $table = 'svp_reseller_wholesale_line_assignments';

    protected $guarded = [];
}
