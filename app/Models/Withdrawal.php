<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Withdrawal extends Model
{
    protected $fillable = [
        'reference', 'agent_id', 'wallet_id', 'bank_account_id', 'amount', 'fee', 'net_amount',
        'bank_name', 'bank_code', 'account_name', 'account_number', 'status', 'payout_method',
        'transfer_reference', 'transfer_code', 'note', 'processed_by', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    protected $hidden = ['account_number'];

    protected $appends = ['account_number_masked'];

    public function getAccountNumberMaskedAttribute(): ?string
    {
        $n = $this->attributes['account_number'] ?? null;
        if (! $n) {
            return null;
        }

        return strlen($n) > 4 ? str_repeat('*', strlen($n) - 4) . substr($n, -4) : $n;
    }

    protected static function booted(): void
    {
        static::creating(fn (Withdrawal $w) => $w->reference ??= 'WDR-' . strtoupper(Str::random(10)));
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
