<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'member_display_name',
        'trading_platform_id',
        'category',
        'account_group',
        'minimum_deposit',
        'leverage',
        'currency',
        'allow_create_account',
        'type',
        'commission_structure',
        'trade_open_duration',
        'maximum_account_number',
        'descriptions',
        'color',
        'edited_by',
        'status',
        'balance_multiplier',
    ];

    // Relations
    public function trading_accounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class, 'account_type_id', 'id');
    }

    public function markupProfileToAccountTypes(): HasMany
    {
        return $this->hasMany(MarkupProfileToAccountType::class, 'account_type_id', 'id');
    }

    public function trading_platform(): BelongsTo
    {
        return $this->belongsTo(TradingPlatform::class, 'trading_platform_id', 'id');
    }
}
