<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpResellerPanelPrice extends Model
{
    use SvpTable;

    protected $table = 'svp_reseller_panel_prices';

    protected $guarded = [];
}
