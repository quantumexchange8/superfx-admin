<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarkupProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status'
    ];

    // Relations
    public function markupProfileToAccountTypes(): HasMany
    {
        return $this->hasMany(MarkupProfileToAccountType::class, 'markup_profile_id', 'id');
    }

    public function userToMarkupProfiles(): HasMany
    {
        return $this->hasMany(UserToMarkupProfile::class, 'markup_profile_id', 'id');
    }
}
