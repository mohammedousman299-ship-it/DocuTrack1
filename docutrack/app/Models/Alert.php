<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function foundDocument(): BelongsTo
    {
        return $this->belongsTo(FoundDocument::class);
    }

    public function lostDeclaration(): BelongsTo
    {
        return $this->belongsTo(LostDeclaration::class);
    }
}
