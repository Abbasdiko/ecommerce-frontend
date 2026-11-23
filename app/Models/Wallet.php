<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'user_id', 'owner_type', 'pending_balance', 'available_balance', 'withdrawn_balance', 'currency',
    ];

    protected $casts = [
        'pending_balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'withdrawn_balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('created_at');
    }
}
