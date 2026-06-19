<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpBroadcast extends Model
{
    use SvpTable;

    protected $table = 'svp_broadcasts';

    protected $guarded = [];
}
