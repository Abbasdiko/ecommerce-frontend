<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'uuid', 'reference', 'gateway', 'method', 'purpose', 'payable_type', 'payable_id',
        'user_id', 'amount', 'currency', 'status', 'gateway_reference', 'proof_path',
        'meta', 'paid_at', 'verified_at', 'verified_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $p) {
            $p->uuid ??= (string) Str::uuid();
            $p->reference ??= 'PMT-' . now()->format('ymd') . '-' . strtoupper(Str::random(8));
        });
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
