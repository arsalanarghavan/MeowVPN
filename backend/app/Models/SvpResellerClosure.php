<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpResellerClosure extends Model
{
    use SvpTable;

    protected $table = 'svp_reseller_closure';

    protected $guarded = [];
}
