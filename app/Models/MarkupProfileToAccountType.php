<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarkupProfileToAccountType extends Model
{
    use HasFactory;

    protected $fillable = ['markup_profile_id', 'account_type_id'];

    // Relations
    public function markupProfile(): BelongsTo
    {
        return $this->belongsTo(MarkupProfile::class, 'markup_profile_id', 'id');
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id', 'id');
    }

}
