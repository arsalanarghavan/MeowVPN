<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AezaOrder extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'aeza_server_id',
        'ip_address',
        'root_password',
        'meta',
        'error_message',
    ];

    protected $hidden = [
        'root_password',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
