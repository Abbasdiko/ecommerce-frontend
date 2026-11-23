<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id', 'name', 'description', 'price', 'duration_days',
        'listing_limit', 'featured_listing_limit', 'commission_rate', 'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'commission_rate' => 'decimal:3',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AgentSubscription::class, 'package_id');
    }
}
