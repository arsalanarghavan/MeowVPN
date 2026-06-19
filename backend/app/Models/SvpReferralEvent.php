<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpReferralEvent extends Model
{
    use SvpTable;

    protected $table = 'svp_referral_events';

    protected $guarded = [];
}
