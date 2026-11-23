<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'agent_order_id', 'agent_id', 'listing_id', 'product_name', 'sku',
        'agent_name', 'unit_price', 'quantity', 'discount', 'tax', 'subtotal', 'total',
        'commission', 'agent_earning', 'reviewed', 'selected_options',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'commission' => 'decimal:2',
        'agent_earning' => 'decimal:2',
        'selected_options' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function agentOrder(): BelongsTo
    {
        return $this->belongsTo(AgentOrder::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
