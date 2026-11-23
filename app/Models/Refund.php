<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Refund extends Model
{
    protected $fillable = [
        'reference', 'return_request_id', 'order_id', 'customer_id', 'amount', 'method',
        'status', 'commission_reversed', 'processed_by', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_reversed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Refund $r) => $r->reference ??= 'RFD-' . strtoupper(Str::random(10)));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
