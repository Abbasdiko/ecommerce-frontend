<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class AgentInvoice extends Model
{
    protected $fillable = [
        'invoice_number', 'agent_id', 'billing_mode', 'currency', 'subtotal', 'total', 'status', 'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AgentInvoice $invoice) {
            $invoice->invoice_number ??= 'AGI-'.now()->format('ymd').'-'.strtoupper(Str::random(8));
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(AgentInvoiceItem::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
