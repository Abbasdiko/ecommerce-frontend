<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Settlement extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'wallet_id', 'bank_account_id', 'amount', 'fee',
        'net_amount', 'status', 'note', 'processed_by', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Settlement $s) => $s->reference ??= 'STL-' . strtoupper(Str::random(10)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
