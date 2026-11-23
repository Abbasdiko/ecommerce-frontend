<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgentOrder extends Model
{
    protected $fillable = [
        'reference', 'order_id', 'agent_id', 'subtotal', 'discount', 'commission',
        'delivery_fee', 'total', 'agent_earning', 'status', 'tracking_number',
        'delivered_at', 'funds_released_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'commission' => 'decimal:2',
        'total' => 'decimal:2',
        'agent_earning' => 'decimal:2',
        'delivered_at' => 'datetime',
        'funds_released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AgentOrder $ao) {
            $ao->reference ??= 'AGO-' . strtoupper(Str::random(8));
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
