<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpL2tpServer extends Model
{
    use SvpTable;

    protected $table = 'svp_l2tp_servers';

    protected $guarded = [];
}
