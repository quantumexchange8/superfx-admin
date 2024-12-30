<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountTypeSymbol extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_type_id',
        'symbol_group_id',
        'symbol_group_name',
        'symbol_id',
        'meta_symbol_name',
    ];
}
