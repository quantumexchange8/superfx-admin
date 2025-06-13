<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRunLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_ran_at' => 'datetime',
        ];
    }

}
