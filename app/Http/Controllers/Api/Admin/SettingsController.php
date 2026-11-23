<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\PlatformSettings;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    use ApiResponse;

    /** The typed platform-settings schema lives on PlatformSettings so every layer shares it. */
    public const SCHEMA = PlatformSettings::SCHEMA;

    /** Brand image settings — uploaded via uploadBrand(), never through update(). */
    private const BRAND_KEYS = ['platform.logo', 'platform.logo_on_dark', 'platform.favicon'];

    public function show(Request $request)
    {
        $this->authorizePermission($request, 'setting.view');

        $stored = Setting::cached();
        $out = [];
        foreach (self::SCHEMA as $key => $meta) {
            [$group, $default, $type] = $meta;
            $raw = $stored->get($key, $default);
            $row = [
                'key' => $key,
                'value' => $raw,
                'type' => $type,
                'options' => $meta[3] ?? null,
            ];
            if ($type === 'image') {
                // Send the resolved URL (custom upload or bundled default) for preview,
                // plus a flag so the UI can offer "reset to default".
                $row['value'] = PlatformSettings::brandAsset($key);
                $row['custom'] = is_string($raw) && $raw !== '';
            }
            $out[$group][] = $row;
        }

        return $this->ok($out);
    }

    public function update(Request $request)
    {
        $this->authorizePermission($request, 'setting.update');

        $data = $request->validate(['settings' => ['required', 'array']]);

        if (array_key_exists('commission.default_rate', $data['settings'])
            && (! is_numeric($data['settings']['commission.default_rate'])
                || $data['settings']['commission.default_rate'] < 0
                || $data['settings']['commission.default_rate'] > 100)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'settings.commission.default_rate' => ['The default commission must be between 0 and 100.'],
            ]);
        }
        if (array_key_exists('agents.listing_billing_mode', $data['settings'])
            && ! in_array($data['settings']['agents.listing_billing_mode'], ['subscription', 'commission', 'subscription_commission'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'settings.agents.listing_billing_mode' => ['Choose a valid vendor listing billing mode.'],
            ]);
        }

        $changed = [];
        foreach ($data['settings'] as $key => $value) {
            if (! isset(self::SCHEMA[$key])) {
                continue;
            }
            [$group, , $type] = self::SCHEMA[$key];
            if ($type === 'image') {
                continue; // managed by uploadBrand()/clearBrand()
            }
            $cast = match ($type) {
                'bool' => (bool) $value,
                'number' => is_numeric($value) ? $value + 0 : throw \Illuminate\Validation\ValidationException::withMessages([
                    "settings.{$key}" => ['The setting must be numeric.'],
                ]),
                default => (string) $value,
            };
            Setting::put($key, $cast, $group);
            $changed[$key] = $cast;
        }

        AuditLogger::record('settings.updated', null, [], $changed);

        return $this->ok(null, 'Settings saved');
    }

    /** Upload a new company logo or favicon. */
    public function uploadBrand(Request $request)
    {
        $this->authorizePermission($request, 'setting.update');

        $data = $request->validate([
            'field' => ['required', Rule::in(self::BRAND_KEYS)],
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg,ico', 'max:2048'],
        ]);

        $previous = Setting::value($data['field']);
        $path = $request->file('file')->store('branding', 'public');

        Setting::put($data['field'], $path, 'branding');
        $this->deleteStored($previous);

        AuditLogger::record('settings.brand_updated', null, [], [$data['field'] => $path]);

        return $this->ok(
            ['field' => $data['field'], 'url' => Storage::disk('public')->url($path)],
            'Image updated — applied across all portals',
        );
    }

    /** Clear a brand image so the bundled default is used again. */
    public function clearBrand(Request $request)
    {
        $this->authorizePermission($request, 'setting.update');

        $data = $request->validate([
            'field' => ['required', Rule::in(self::BRAND_KEYS)],
        ]);

        $previous = Setting::value($data['field']);
        Setting::put($data['field'], '', 'branding');
        $this->deleteStored($previous);

        AuditLogger::record('settings.brand_updated', null, [], [$data['field'] => null]);

        return $this->ok(null, 'Image reset to default');
    }

    private function deleteStored($path): void
    {
        if (is_string($path) && $path !== '' && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}
