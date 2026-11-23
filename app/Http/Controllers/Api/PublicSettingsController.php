<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Unauthenticated read-only feed of platform settings. Every portal (storefront +
 * dashboard shell) hydrates its branding, contact details, currency, language and
 * enabled payment methods from here so the admin "Platform Settings" screen is the
 * single source of truth.
 */
class PublicSettingsController extends Controller
{
    use ApiResponse;

    public function show()
    {
        $payload = Cache::remember('settings.public', now()->addMinutes(10), fn () => PlatformSettings::publicPayload());

        return $this->ok($payload);
    }
}
