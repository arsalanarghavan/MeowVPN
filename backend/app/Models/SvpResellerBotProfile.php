<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpResellerBotProfile extends Model
{
    use SvpTable;

    protected $table = 'svp_reseller_bot_profiles';

    protected $guarded = [];
}
