<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpServiceIpLog extends Model
{
    use SvpTable;

    protected $table = 'svp_service_ip_log';

    protected $guarded = [];
}
