<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SvpSetting extends Model
{
    protected $table = 'svp_settings';

    public const CREATED_AT = null;

    protected $fillable = [
        'key_name',
        'value',
    ];
}
