<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $fillable = [
        'scope_type', 'scope_id', 'commission_type', 'commission_value',
        'status', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'commission_value' => 'decimal:3',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public const SCOPE_PRIORITY = ['agent', 'category', 'service', 'global'];

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()));
    }
}
