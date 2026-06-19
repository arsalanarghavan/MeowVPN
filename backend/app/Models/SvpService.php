<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SvpService extends Model
{
    /** @use HasFactory<\Database\Factories\SvpServiceFactory> */
    use HasFactory;
    protected $table = 'svp_services';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'panel_id', 'inbound_id', 'xui_client_id', 'xui_client_uuid', 'email',
        'remark', 'display_label', 'service_note', 'plan_id', 'expires_at', 'total_traffic',
        'used_traffic', 'autorenew', 'provision_type', 'service_type', 'created_at', 'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
            'autorenew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(SvpUser::class, 'user_id');
    }
}
