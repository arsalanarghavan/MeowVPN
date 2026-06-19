<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpMonitorHost extends Model
{
    use SvpTable;

    protected $table = 'svp_monitor_hosts';

    protected $guarded = [];
}
