<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentGateway extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'platform',
        'environment',
        'payment_url',
        'payment_app_name',
        'secret_key',
        'secondary_key',
        'edited_by',
        'status',
        'can_deposit',
        'can_withdraw',
    ];

    // Relations
    public function successTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payment_gateway_id', 'id')->where('status', 'Success');
    }
}
