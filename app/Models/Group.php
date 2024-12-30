<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'fee_charges',
        'color',
        'group_leader_id',
        'edited_by',
    ];

    // Relations
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'group_leader_id', 'id');
    }

    public function group_has_user(): HasMany
    {
        return $this->hasMany(GroupHasUser::class, 'group_id', 'id');
    }
}
