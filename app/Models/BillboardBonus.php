<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillboardBonus extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'billboard_profile_id',
        'target_amount',
        'achieved_amount',
        'achieved_percentage',
        'bonus_rate',
        'bonus_amount',
        'bonus_month',
    ];
}
