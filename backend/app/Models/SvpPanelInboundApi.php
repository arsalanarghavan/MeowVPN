<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpPanelInboundApi extends Model
{
    use SvpTable;

    protected $table = 'svp_panel_inbound_api';

    protected $guarded = [];
}
