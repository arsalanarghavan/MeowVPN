<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpInboundQueue extends Model
{
    use SvpTable;

    protected $table = 'svp_inbound_queue';

    protected $guarded = [];
}
