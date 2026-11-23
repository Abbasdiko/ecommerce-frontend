<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'order_id', 'agent_order_id', 'agent_id', 'order_item_id',
        'commission_base', 'commission_rate', 'commission_amount', 'agent_earning', 'status',
    ];

    protected $casts = [
        'commission_base' => 'decimal:2',
        'commission_rate' => 'decimal:3',
        'commission_amount' => 'decimal:2',
        'agent_earning' => 'decimal:2',
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
}
