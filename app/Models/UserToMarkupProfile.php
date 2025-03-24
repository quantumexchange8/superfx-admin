<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserToMarkupProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'markup_profile_id'];

    // Relations
    public function markupProfile(): BelongsTo
    {
        return $this->belongsTo(MarkupProfile::class, 'markup_profile_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
