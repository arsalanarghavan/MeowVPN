<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class DashboardUser extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $fillable = [
        'username',
        'password',
        'role',
        'svp_user_id',
        'permissions_json',
        'ui_accent',
        'ui_theme',
        'ui_sidebar',
        'ui_lang',
    ];

    protected $hidden = [
        'password',
    ];

    protected static function newFactory(): \Database\Factories\DashboardUserFactory
    {
        return \Database\Factories\DashboardUserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'permissions_json' => 'array',
        ];
    }
}
