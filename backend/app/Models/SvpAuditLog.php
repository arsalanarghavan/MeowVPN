<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpAuditLog extends Model
{
    use SvpTable;

    protected $table = 'svp_audit_log';

    protected $guarded = [];
}
