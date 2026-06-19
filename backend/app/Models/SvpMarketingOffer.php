<?php

namespace App\Models;

use App\Models\Concerns\SvpTable;
use Illuminate\Database\Eloquent\Model;

class SvpMarketingOffer extends Model
{
    use SvpTable;

    protected $table = 'svp_marketing_offers';

    protected $guarded = [];
}
