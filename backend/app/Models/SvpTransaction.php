<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SvpTransaction extends Model
{
    protected $table = 'svp_transactions';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'service_id', 'amount', 'type', 'status', 'meta_json',
        'billing_reseller_svp_id', 'referral_amount', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'referral_amount' => 'decimal:2',
            'meta_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
