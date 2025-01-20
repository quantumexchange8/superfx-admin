<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradeRebateHistory extends Model
{
    use SoftDeletes;

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'upline_user_id', 'id');
    }

    public function downline(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ticket_user_id', 'id');
    }

    public function of_account_type(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type', 'id');
    }
}
