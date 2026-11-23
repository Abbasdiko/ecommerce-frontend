<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentSubscription extends Model
{
    protected $fillable = [
        'subscription_reference', 'agent_id', 'service_id', 'package_id', 'amount',
        'start_date', 'end_date', 'status', 'payment_reference', 'payment_id', 'payment_status',
        'auto_renew', 'listings_used',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'amount' => 'decimal:2',
        'auto_renew' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AgentSubscription $sub) {
            $sub->subscription_reference ??= 'SUB-' . strtoupper(Str::random(10));
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->end_date
            && $this->end_date->isFuture();
    }

    public function remainingListingSlots(): int
    {
        return max(0, ($this->package->listing_limit ?? 0) - $this->listings_used);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'package_id');
    }
}
