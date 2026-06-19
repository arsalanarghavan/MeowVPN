<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpLog extends Model
{
    use SvpTable;

    protected $table = 'svp_logs';

    protected $guarded = [];
}
