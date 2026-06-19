<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpText extends Model
{
    use SvpTable;

    protected $table = 'svp_texts';

    protected $guarded = [];
}
