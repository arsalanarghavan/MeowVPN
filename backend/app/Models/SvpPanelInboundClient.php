<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpPanelInboundClient extends Model
{
    use SvpTable;

    protected $table = 'svp_panel_inbound_clients';

    protected $guarded = [];
}
