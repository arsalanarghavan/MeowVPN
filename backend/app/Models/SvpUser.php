<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SvpUser extends Model
{
    /** @use HasFactory<\Database\Factories\SvpUserFactory> */
    use HasFactory;
    protected $table = 'svp_users';

    public $timestamps = false;

    protected $fillable = [
        'tg_user_id',
        'bale_user_id',
        'first_name',
        'last_name',
        'username',
        'phone',
        'role',
        'balance',
        'status',
        'approved_by',
        'approved_at',
        'admin_mode',
        'state',
        'state_data',
        'bot_locale',
        'invited_by',
        'signup_reseller_svp_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'admin_mode' => 'boolean',
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
            'state_data' => 'array',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(SvpService::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SvpTransaction::class, 'user_id');
    }
}
