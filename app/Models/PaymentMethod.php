<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'meta',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'json',
        ];
    }
}
