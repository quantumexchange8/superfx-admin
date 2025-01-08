<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CurrencyConversionRate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'deposit_rate',
        'withdrawal_rate',
        'deposit_charge_type',
        'deposit_charge_amount',
        'withdrawal_charge_type',
        'withdrawal_charge_amount',
        'status',
    ];
}
