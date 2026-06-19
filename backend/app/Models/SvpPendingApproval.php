<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpPendingApproval extends Model
{
    use SvpTable;

    protected $table = 'svp_pending_approvals';

    protected $guarded = [];
}
