<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpPlanCategory extends Model
{
    use SvpTable;

    protected $table = 'svp_plan_categories';

    protected $guarded = [];
}
