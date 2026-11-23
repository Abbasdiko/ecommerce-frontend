<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'agent_id', 'service_id', 'category_id', 'subscription_id', 'name', 'slug',
        'description', 'sku', 'price', 'sale_price', 'deal_ends_at', 'quantity', 'sales_count',
        'stock_status', 'condition', 'type', 'featured', 'brand', 'status', 'approval_status',
        'rejection_reason', 'reviewed_by', 'published_at',
        'sizes', 'colors',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'published_at' => 'datetime',
        'deal_ends_at' => 'datetime',
        'featured' => 'boolean',
        'sizes' => 'array',
        'colors' => 'array',
    ];

    public function scopePublished($query)
    {
        return $query->where('approval_status', 'approved')
            ->where('status', 'active')
            ->whereNotNull('published_at');
    }

    public function effectivePrice(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    protected static function booted(): void
    {
        static::creating(function (Listing $listing) {
            $listing->uuid ??= (string) Str::uuid();
            if (! $listing->slug) {
                $base = Str::slug($listing->name);
                $slug = $base.'-'.Str::lower(Str::random(6));
                $listing->slug = $slug;
            }
        });
    }

    public function isPublished(): bool
    {
        return $this->status === 'draft' ? false : $this->approval_status === 'approved' && $this->published_at !== null;
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AgentSubscription::class, 'subscription_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'subject_id')->where('subject_type', 'listing');
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }
}
