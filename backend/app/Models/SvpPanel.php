<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpPanel extends Model
{
    use SvpTable;

    protected $table = 'svp_panels';

    protected $guarded = [];
}
