<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpUnitEconomicsServer extends Model
{
    use SvpTable;

    protected $table = 'svp_unit_economics_servers';

    protected $guarded = [];
}
