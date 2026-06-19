<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpBroadcastQueue extends Model
{
    use SvpTable;

    protected $table = 'svp_broadcast_queue';

    protected $guarded = [];
}
