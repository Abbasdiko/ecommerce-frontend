<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeMessage extends Model
{
    protected $fillable = ['dispute_id', 'user_id', 'author_role', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
