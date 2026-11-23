<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'owner_type', 'owner_id', 'discount_type', 'discount_value',
        'maximum_discount', 'minimum_order_amount', 'usage_limit', 'usage_per_customer',
        'times_used', 'funding_type', 'starts_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'owner_id')->where('owner_type', 'agent');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'active'
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->expires_at || $this->expires_at->isFuture())
            && (! $this->usage_limit || $this->times_used < $this->usage_limit);
    }
}
