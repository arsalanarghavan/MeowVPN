<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SvpPlan extends Model
{
    use HasFactory;
    protected $table = 'svp_plans';

    public $timestamps = false;

    protected $fillable = [
        'name', 'category', 'duration_days', 'traffic_gb', 'price', 'pricing_type',
        'quota_display_mode', 'price_per_gb', 'traffic_gb_min', 'traffic_gb_max', 'clients_count',
        'inbound_id', 'inbound_ids', 'panel_template_id', 'panel_id', 'wholesale_line_id',
        'owner_svp_user_id', 'service_type', 'l2tp_server_id', 'active', 'sort_order', 'created_at',
        'panel_driver', 'xray_inbound_ref',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_per_gb' => 'decimal:2',
            'active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
