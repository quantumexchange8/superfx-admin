<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CurrencyConversionRate extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'api_host',
        'api_key',
        'deposit_rate',
        'withdrawal_rate',
        'deposit_charge_type',
        'deposit_charge_amount',
        'withdrawal_charge_type',
        'withdrawal_charge_amount',
        'status',
    ];

    // Logs
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('currency_conversion_rates')
            ->logOnly([
                'base_currency',
                'target_currency',
                'api_host',
                'api_key',
                'deposit_rate',
                'withdrawal_rate',
                'deposit_charge_type',
                'deposit_charge_amount',
                'withdrawal_charge_type',
                'withdrawal_charge_amount',
                'status',
            ])
            ->setDescriptionForEvent(function (string $eventName) {
                return "Cronjob has $eventName exchange rates";
            })
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
