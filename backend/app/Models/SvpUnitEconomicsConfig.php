<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpUnitEconomicsConfig extends Model
{
    use SvpTable;

    protected $table = 'svp_unit_economics_config';

    protected $guarded = [];
}
