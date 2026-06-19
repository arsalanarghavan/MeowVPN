<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpSyncCode extends Model
{
    use SvpTable;

    protected $table = 'svp_sync_codes';

    protected $guarded = [];
}
