<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupSettlement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'group_id',
        'transaction_start_at',
        'transaction_end_at',
        'group_deposit',
        'group_withdrawal',
        'group_fee_percentage',
        'group_fee',
        'group_balance',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_start_at' => 'datetime',
            'transaction_end_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    // Relations
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }
}
