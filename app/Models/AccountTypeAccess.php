<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountTypeAccess extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function accountType()
    {
        return $this->belongsTo(AccountType::class, 'account_type_id', 'id');
    }

}
