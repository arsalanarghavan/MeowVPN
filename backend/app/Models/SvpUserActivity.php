<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpUserActivity extends Model
{
    use SvpTable;

    protected $table = 'svp_user_activity';

    protected $guarded = [];
}
