<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpDiscountCode extends Model
{
    use SvpTable;

    protected $table = 'svp_discount_codes';

    protected $guarded = [];
}
