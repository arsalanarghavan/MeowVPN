<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpResellerInboundDisplayName extends Model
{
    use SvpTable;

    protected $table = 'svp_reseller_inbound_display_names';

    protected $guarded = [];
}
