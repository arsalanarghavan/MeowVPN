<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpUsersBulkJobItem extends Model
{
    use SvpTable;

    protected $table = 'svp_users_bulk_job_items';

    protected $guarded = [];
}
