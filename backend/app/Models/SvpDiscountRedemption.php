<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpDiscountRedemption extends Model
{
    use SvpTable;

    protected $table = 'svp_discount_redemptions';

    protected $guarded = [];
}
