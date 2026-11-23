<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    protected $fillable = [
        'user_id', 'bank_name', 'bank_code', 'account_name', 'account_number', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $hidden = ['account_number'];

    protected $appends = ['account_number_masked'];

    public function getAccountNumberMaskedAttribute(): string
    {
        $n = (string) $this->attributes['account_number'];

        return strlen($n) > 4 ? str_repeat('*', strlen($n) - 4) . substr($n, -4) : $n;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
