<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpTelegramMirrorBot extends Model
{
    use SvpTable;

    protected $table = 'svp_telegram_mirror_bots';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];
}
