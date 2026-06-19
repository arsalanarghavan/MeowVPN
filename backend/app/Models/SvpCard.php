<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SvpCard extends Model
{
    use HasFactory;
    use SvpTable;

    protected $table = 'svp_cards';

    protected $guarded = [];
}
