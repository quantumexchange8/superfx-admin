<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGatewayHasBank extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_gateway_id',
        'bank_id',
    ];
}
