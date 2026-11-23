<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'customer_id', 'subject_type', 'subject_id', 'order_id', 'order_item_id',
        'rating', 'title', 'body', 'response', 'responded_at', 'verified_purchase', 'status',
    ];

    protected $casts = [
        'verified_purchase' => 'boolean',
        'rating' => 'integer',
        'responded_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeForListing($q, int $listingId)
    {
        return $q->where('subject_type', 'listing')->where('subject_id', $listingId);
    }

    public function scopeForAgent($q, int $agentId)
    {
        return $q->where('subject_type', 'agent')->where('subject_id', $agentId);
    }
}
