<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for platform configuration. Every portal reads from
 * here (via /api/v1/settings/public) or via Setting::value() on the server.
 */
class PlatformSettings
{
    /** key => [group, default, type]  (types: string|text|number|bool|select|image|accounts) */
    public const SCHEMA = [
        'platform.logo' => ['branding', '', 'image'],
        'platform.logo_on_dark' => ['branding', '', 'image'],
        'platform.favicon' => ['branding', '', 'image'],

        'platform.name' => ['general', 'MegaMarket', 'string'],
        'platform.tagline' => ['general', 'One marketplace. Every service.', 'string'],
        'platform.support_email' => ['general', 'support@megamarket.africa', 'string'],
        'platform.phone' => ['general', '+234 700 634 2762', 'string'],
        'platform.address' => ['general', '12 Adeola Odeku Street, Victoria Island, Lagos, Nigeria', 'text'],
        'platform.hours' => ['general', 'Mon–Sat, 8:00–20:00 WAT', 'string'],

        'platform.currency' => ['localization', 'NGN', 'string'],
        'platform.currency_symbol' => ['localization', '₦', 'string'],
        'platform.default_language' => ['localization', 'en', 'select', ['en', 'fr', 'ar']],
        'platform.tax_percentage' => ['localization', 0, 'number'],

        'commission.default_rate' => ['commission', 10, 'number'],
        'agents.listing_billing_mode' => ['commission', 'commission', 'select', ['subscription', 'commission', 'subscription_commission']],

        'delivery.default_fee' => ['delivery', 1500, 'number'],
        'delivery.free_over' => ['delivery', 100000, 'number'],

        'cod.enabled' => ['checkout', true, 'bool'],
        'cod.max_order_value' => ['checkout', 200000, 'number'],

        'withdrawals.min_amount' => ['payouts', 2000, 'number'],
        'withdrawals.fee' => ['payouts', 100, 'number'],
        'withdrawals.agent_fund_hold_days' => ['payouts', 3, 'number'],

        'coupons.agent_max_percentage' => ['coupons', 50, 'number'],

        'agents.auto_approve' => ['approvals', false, 'bool'],
        'listings.require_approval' => ['approvals', true, 'bool'],
    ];

    /** Bundled fallback asset (under public/) for each uploadable brand image. */
    public const BRAND_DEFAULTS = [
        'platform.logo' => 'brand/augd-logo-primary.svg',
        'platform.logo_on_dark' => 'brand/augd-logo-on-dark.svg',
        'platform.favicon' => 'brand/favicon.svg',
    ];

    public static function value(string $key, $default = null)
    {
        $schemaDefault = self::SCHEMA[$key][1] ?? null;

        return Setting::value($key, $default ?? $schemaDefault);
    }

    public static function listingBillingMode(): string
    {
        $mode = self::value('agents.listing_billing_mode', 'commission');

        return in_array($mode, ['subscription', 'commission', 'subscription_commission'], true)
            ? $mode
            : 'commission';
    }

    public static function requiresListingSubscription(): bool
    {
        return in_array(self::listingBillingMode(), ['subscription', 'subscription_commission'], true);
    }

    public static function chargesSaleCommission(): bool
    {
        return in_array(self::listingBillingMode(), ['commission', 'subscription_commission'], true);
    }

    /**
     * Resolve a brand image setting to a usable URL: the admin-uploaded file when
     * one is set, otherwise the bundled default shipped under public/brand.
     */
    public static function brandAsset(string $key): string
    {
        $stored = self::value($key);

        if (is_string($stored) && $stored !== '') {
            return str_starts_with($stored, 'http') ? $stored : Storage::disk('public')->url($stored);
        }

        return asset(self::BRAND_DEFAULTS[$key] ?? '');
    }

    public static function brandAssetDataUri(string $key): ?string
    {
        $stored = self::value($key);

        if (is_string($stored) && $stored !== '' && ! str_starts_with($stored, 'http')) {
            $disk = Storage::disk('public');
            if (! $disk->exists($stored)) {
                return null;
            }

            $contents = $disk->get($stored);
            $extension = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
        } else {
            $path = public_path(self::BRAND_DEFAULTS[$key] ?? '');
            if (! is_file($path)) {
                return null;
            }

            $contents = file_get_contents($path);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }

        if ($contents === false) {
            return null;
        }

        $mime = match ($extension) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            default => null,
        };

        return $mime ? 'data:'.$mime.';base64,'.base64_encode($contents) : null;
    }

    /** Public, non-secret configuration for the storefront + dashboard shells. */
    public static function publicPayload(): array
    {
        $g = fn ($k) => self::value($k);

        return [
            'name' => $g('platform.name'),
            'tagline' => $g('platform.tagline'),
            'support_email' => $g('platform.support_email'),
            'phone' => $g('platform.phone'),
            'address' => $g('platform.address'),
            'hours' => $g('platform.hours'),
            'storefront_url' => config('app.url'),
            'currency' => $g('platform.currency'),
            'currency_symbol' => $g('platform.currency_symbol'),
            'default_language' => $g('platform.default_language'),
            'tax_percentage' => (float) $g('platform.tax_percentage'),
            'listing_billing_mode' => self::listingBillingMode(),
            'delivery' => [
                'fee' => (float) $g('delivery.default_fee'),
                'free_over' => (float) $g('delivery.free_over'),
            ],
            'cod' => [
                'enabled' => (bool) $g('cod.enabled'),
                'max_order_value' => (float) $g('cod.max_order_value'),
            ],
            'payment_methods' => PaymentConfig::checkoutMethods(),
            'brand' => [
                'logo' => self::brandAsset('platform.logo'),
                'logo_on_dark' => self::brandAsset('platform.logo_on_dark'),
                'icon' => asset('brand/augd-icon-mark.svg'),
                'favicon' => self::brandAsset('platform.favicon'),
            ],
        ];
    }
}
