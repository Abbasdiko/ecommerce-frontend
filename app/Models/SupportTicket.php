<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'assigned_to', 'subject', 'category', 'priority',
        'status', 'order_id', 'last_reply_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (SupportTicket $t) => $t->reference ??= 'TKT-' . strtoupper(Str::random(8)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest();
    }
}
