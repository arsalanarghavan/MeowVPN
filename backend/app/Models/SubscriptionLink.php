<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'server_id',
        'vless_link',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}

