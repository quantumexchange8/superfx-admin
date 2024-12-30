<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillboardProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'sales_calculation_mode',
        'sales_category',
        'target_amount',
        'bonus_rate',
        'bonus_calculation_threshold',
        'calculation_period',
        'next_payout_at',
        'edited_by',
    ];

    protected function casts(): array
    {
        return [
            'next_payout_at' => 'datetime',
        ];
    }

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
