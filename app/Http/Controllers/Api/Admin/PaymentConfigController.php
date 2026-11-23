<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\PaymentConfig;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentConfigController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        $this->authorizePermission($request, 'setting.view');

        return $this->ok(PaymentConfig::adminSnapshot());
    }

    public function update(Request $request)
    {
        $this->authorizePermission($request, 'setting.update');

        $data = $request->validate([
            'mode' => ['required', Rule::in(['simulated', 'live'])],

            'gateways' => ['required', 'array'],
            'gateways.*.enabled' => ['boolean'],
            'gateways.*.public_key' => ['nullable', 'string', 'max:255'],
            'gateways.*.secret_key' => ['nullable', 'string', 'max:255'],
            'gateways.*.encryption_key' => ['nullable', 'string', 'max:255'],
            'gateways.*.merchant_id' => ['nullable', 'string', 'max:255'],
            'gateways.*.api_key' => ['nullable', 'string', 'max:255'],
            'gateways.*.service_type_id' => ['nullable', 'string', 'max:255'],
            'gateways.*.base_url' => ['nullable', 'string', 'max:255'],

            'bank_transfer.enabled' => ['boolean'],
            'bank_transfer.accounts' => ['array'],
            'bank_transfer.accounts.*.bank_name' => ['required_with:bank_transfer.accounts', 'string', 'max:120'],
            'bank_transfer.accounts.*.account_name' => ['required_with:bank_transfer.accounts', 'string', 'max:120'],
            'bank_transfer.accounts.*.account_number' => ['required_with:bank_transfer.accounts', 'string', 'max:40'],

            'pay_on_delivery.enabled' => ['boolean'],
        ]);

        if ($data['mode'] === 'live') {
            $errors = [];
            foreach (PaymentConfig::GATEWAYS as $gateway) {
                $incoming = $data['gateways'][$gateway] ?? [];
                if (! ($incoming['enabled'] ?? false)) {
                    continue;
                }

                if (! PaymentConfig::gatewayReady($gateway, $incoming)) {
                    $errors["gateways.{$gateway}"][] = ucfirst($gateway).' live credentials are incomplete. Add the required keys or disable this gateway before switching to Live.';
                }
            }
            if ($errors !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages($errors);
            }
        }

        Setting::put('payments.mode', $data['mode'], 'payments');

        foreach (PaymentConfig::FIELDS as $gateway => $fields) {
            $incoming = $data['gateways'][$gateway] ?? [];

            Setting::put("payments.{$gateway}.enabled", (bool) ($incoming['enabled'] ?? false), 'payments');

            foreach ($fields as $field => $kind) {
                if (! array_key_exists($field, $incoming)) {
                    continue;
                }
                $value = $incoming[$field];
                // Never overwrite a stored secret with a blank submission.
                if ($kind === 'secret' && ($value === null || $value === '')) {
                    continue;
                }
                Setting::put("payments.{$gateway}.{$field}", $value === null ? '' : (string) $value, 'payments');
            }
        }

        Setting::put('payments.bank_transfer.enabled', (bool) ($data['bank_transfer']['enabled'] ?? false), 'payments');
        if (array_key_exists('accounts', $data['bank_transfer'] ?? [])) {
            Setting::put('payments.bank_transfer.accounts', array_values($data['bank_transfer']['accounts'] ?? []), 'payments');
        }

        Setting::put('payments.pay_on_delivery.enabled', (bool) ($data['pay_on_delivery']['enabled'] ?? false), 'payments');

        AuditLogger::record('payments.config_updated', null, [], ['mode' => $data['mode']]);

        return $this->ok(PaymentConfig::adminSnapshot(), 'Payment configuration saved');
    }
}
