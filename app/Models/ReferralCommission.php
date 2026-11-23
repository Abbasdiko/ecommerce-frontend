<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommission extends Model
{
    protected $fillable = [
        'referrer_user_id', 'referred_user_id', 'order_id', 'commission_base',
        'commission_rate', 'commission_amount', 'status',
    ];

    protected $casts = [
        'commission_base' => 'decimal:2',
        'commission_rate' => 'decimal:3',
        'commission_amount' => 'decimal:2',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
