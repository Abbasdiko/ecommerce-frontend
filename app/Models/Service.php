<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'service_type', 'icon', 'tagline', 'status', 'requires_approval',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            $service->slug ??= Str::slug($service->name);
        });
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ServicePackage::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
