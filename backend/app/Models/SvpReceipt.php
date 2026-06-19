<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SvpReceipt extends Model
{
    use HasFactory;
    use SvpTable;

    protected $table = 'svp_receipts';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(SvpUser::class, 'user_id');
    }
}
