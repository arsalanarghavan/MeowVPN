<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpPanelOnlineDaily extends Model
{
    use SvpTable;

    protected $table = 'svp_panel_online_daily';

    protected $guarded = [];
}
