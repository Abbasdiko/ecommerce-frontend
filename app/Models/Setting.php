<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'group'];

    protected $casts = ['value' => 'array'];

    protected static function booted(): void
    {
        $flush = function () {
            Cache::forget('settings.all');
            Cache::forget('settings.public');
        };
        static::saved($flush);
        static::deleted($flush);
    }

    /** @return \Illuminate\Support\Collection<string,mixed> keyed by setting key */
    public static function cached()
    {
        return Cache::rememberForever(
            'settings.all',
            fn () => static::query()->get()->mapWithKeys(fn ($s) => [$s->key => $s->value]),
        );
    }

    public static function value(string $key, $default = null)
    {
        return static::cached()->get($key, $default);
    }

    public static function put(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
