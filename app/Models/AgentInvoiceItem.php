<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentInvoiceItem extends Model
{
    protected $fillable = [
        'agent_invoice_id', 'service_id', 'package_id', 'subscription_id', 'description', 'amount',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AgentInvoice::class, 'agent_invoice_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AgentSubscription::class);
    }
}
