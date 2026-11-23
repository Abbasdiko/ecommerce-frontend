<?php

namespace App\Services;

use App\Models\Setting;

/**
 * DB-backed payment gateway configuration (spec: admin "Payment Configuration").
 * Admins add Paystack / Flutterwave / Remita keys + bank accounts in the dashboard
 * and every gateway driver, the checkout and the storefront pick them up here.
 * Falls back to config/payments.php (.env) when a key has not been set in the DB.
 */
class PaymentConfig
{
    /** Redirect gateways that have driver implementations. */
    public const GATEWAYS = ['paystack', 'flutterwave', 'remita'];

    /** All selectable checkout methods. */
    public const METHODS = ['paystack', 'flutterwave', 'remita', 'bank_transfer', 'pay_on_delivery'];

    /** Per-gateway credential fields; `secret` fields are masked on read. */
    public const FIELDS = [
        'paystack' => [
            'public_key' => 'plain',
            'secret_key' => 'secret',
        ],
        'flutterwave' => [
            'public_key' => 'plain',
            'secret_key' => 'secret',
            'encryption_key' => 'secret',
        ],
        'remita' => [
            'merchant_id' => 'plain',
            'api_key' => 'secret',
            'service_type_id' => 'plain',
            'base_url' => 'plain',
        ],
    ];

    public static function mode(): string
    {
        $default = config('payments.simulated') ? 'simulated' : 'live';

        return Setting::value('payments.mode', $default) === 'live' ? 'live' : 'simulated';
    }

    public static function simulated(): bool
    {
        return self::mode() === 'simulated';
    }

    /** A single credential value: DB first, then config/.env. */
    public static function credential(string $gateway, string $key, $default = null)
    {
        $stored = Setting::value("payments.{$gateway}.{$key}");
        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        return config("payments.gateways.{$gateway}.{$key}", $default);
    }

    public static function methodEnabled(string $method): bool
    {
        if (! in_array($method, self::METHODS, true)) {
            return false;
        }

        // Default to enabled unless an admin has explicitly switched it off.
        return (bool) Setting::value("payments.{$method}.enabled", true);
    }

    /** Method ids available at checkout right now. */
    public static function enabledMethods(): array
    {
        return array_values(array_filter(self::METHODS, fn ($m) => self::methodEnabled($m)));
    }

    /** Methods safe to offer to customers in the current processing mode. */
    public static function checkoutMethods(): array
    {
        return array_values(array_filter(self::enabledMethods(), fn ($method) => ! in_array($method, self::GATEWAYS, true)
            || self::simulated()
            || self::gatewayReady($method)
        ));
    }

    public static function gatewayReady(string $gateway, array $incoming = []): bool
    {
        $required = match ($gateway) {
            'paystack' => ['public_key', 'secret_key'],
            'flutterwave' => ['public_key', 'secret_key', 'encryption_key'],
            'remita' => ['merchant_id', 'api_key', 'service_type_id'],
            default => [],
        };

        if ($required === []) {
            return false;
        }

        foreach ($required as $field) {
            $provided = $incoming[$field] ?? null;
            $value = filled($provided) ? $provided : self::credential($gateway, $field);
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        $baseUrl = filled($incoming['base_url'] ?? null)
            ? $incoming['base_url']
            : self::credential($gateway, 'base_url');
        $parts = is_string($baseUrl) ? parse_url($baseUrl) : false;
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        if ($gateway === 'remita' && is_string($baseUrl) && str_contains(strtolower($baseUrl), 'remitademo.net')) {
            return false;
        }

        return true;
    }

    /** Platform bank accounts for the Bank Transfer method. */
    public static function bankAccounts(): array
    {
        $stored = Setting::value('payments.bank_transfer.accounts');
        if (is_array($stored) && ! empty($stored)) {
            return array_values($stored);
        }

        return (array) config('payments.bank_accounts', []);
    }

    /** Non-secret snapshot for /settings/public. */
    public static function publicSnapshot(): array
    {
        return [
            'mode' => self::mode(),
            'methods' => self::checkoutMethods(),
            'bank_accounts' => self::bankAccounts(),
        ];
    }

    /** Full config for the admin editor — secrets are replaced with a "set" flag. */
    public static function adminSnapshot(): array
    {
        $gateways = [];
        foreach (self::FIELDS as $gateway => $fields) {
            $entry = [
                'enabled' => self::methodEnabled($gateway),
                'ready' => self::gatewayReady($gateway),
            ];
            foreach ($fields as $field => $kind) {
                if ($kind === 'secret') {
                    $entry["{$field}_set"] = filled(self::credential($gateway, $field));
                    $entry[$field] = '';
                } else {
                    $entry[$field] = (string) (self::credential($gateway, $field) ?? '');
                }
            }
            $gateways[$gateway] = $entry;
        }

        return [
            'mode' => self::mode(),
            'gateways' => $gateways,
            'bank_transfer' => [
                'enabled' => self::methodEnabled('bank_transfer'),
                'accounts' => self::bankAccounts(),
            ],
            'pay_on_delivery' => [
                'enabled' => self::methodEnabled('pay_on_delivery'),
            ],
        ];
    }
}
