<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGatewayMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_gateway_id',
        'payment_method_id',
        'min_amount',
        'max_amount',
    ];
}
