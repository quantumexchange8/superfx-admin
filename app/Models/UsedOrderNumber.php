<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsedOrderNumber extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'order_number',
    ];
}
