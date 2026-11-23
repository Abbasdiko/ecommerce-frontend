<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Dispute extends Model
{
    protected $fillable = [
        'reference', 'order_id', 'agent_order_id', 'raised_by', 'agent_id', 'category',
        'subject', 'detail', 'status', 'resolution', 'assigned_to', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Dispute $d) => $d->reference ??= 'DSP-' . strtoupper(Str::random(8)));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->oldest();
    }
}
