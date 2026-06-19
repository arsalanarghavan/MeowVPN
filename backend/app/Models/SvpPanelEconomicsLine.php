<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpPanelEconomicsLine extends Model
{
    use SvpTable;

    protected $table = 'svp_panel_economics_lines';

    protected $guarded = [];
}
