<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRevoke extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'asset_subscription_id',
        'meta_login',
        'balance_on_revoke',
        'penalty_percentage',
        'penalty_fee',
        'asset_master_id',
        'status',
        'remarks',
        'handle_by',
        'approval_at',
    ];

    protected $casts = [
        'approval_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function asset_subscription(): BelongsTo
    {
        return $this->belongsTo(AssetSubscription::class, 'asset_subscription_id', 'id');
    }

    public function trading_account(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class, 'meta_login', 'meta_login');
    }

    public function asset_master(): BelongsTo
    {
        return $this->belongsTo(AssetMaster::class, 'asset_master_id', 'id');
    }

}
