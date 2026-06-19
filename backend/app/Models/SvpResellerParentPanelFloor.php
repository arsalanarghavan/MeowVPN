<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpResellerParentPanelFloor extends Model
{
    use SvpTable;

    protected $table = 'svp_reseller_parent_panel_floors';

    protected $guarded = [];
}
