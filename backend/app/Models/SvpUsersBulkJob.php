<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpUsersBulkJob extends Model
{
    use SvpTable;

    protected $table = 'svp_users_bulk_jobs';

    protected $guarded = [];
}
