<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'user_id', 'business_name', 'slug', 'business_registration_number',
        'description', 'logo', 'phone', 'email', 'address', 'state', 'lga', 'country',
        'status', 'verification_status', 'rejection_reason', 'approved_at', 'approved_by',
        'settings', 'kyc_submitted_at', 'about', 'followers', 'welcome_email_sent_at',
        'verification_email_sent_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'kyc_submitted_at' => 'datetime',
        'welcome_email_sent_at' => 'datetime',
        'verification_email_sent_at' => 'datetime',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Agent $agent) {
            $agent->uuid ??= (string) Str::uuid();
            if (! $agent->slug) {
                $base = Str::slug($agent->business_name);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $agent->slug = $slug;
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isKycVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function wallet(): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $this->user_id, 'owner_type' => 'agent'],
            ['currency' => 'NGN'],
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AgentDocument::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AgentSubscription::class);
    }

    public function serviceSelections(): HasMany
    {
        return $this->hasMany(AgentService::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', 'active')->where('end_date', '>', now());
    }

    public function agentOrders(): HasMany
    {
        return $this->hasMany(AgentOrder::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'subject_id')->where('subject_type', 'agent');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'owner_id')->where('owner_type', 'agent');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
