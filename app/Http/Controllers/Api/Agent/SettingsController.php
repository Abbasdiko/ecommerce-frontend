<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ApiResponse;

    private const DEFAULTS = [
        'notify_new_order' => true,
        'notify_payout_status' => true,
        'notify_low_stock' => true,
        'notify_new_review' => true,
        'auto_accept_orders' => false,
        'vacation_mode' => false,
    ];

    private function agent(Request $request)
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 403, 'Complete your business profile first.');

        return $agent;
    }

    public function show(Request $request)
    {
        $agent = $this->agent($request);

        return $this->ok([
            'store' => [
                'business_name' => $agent->business_name,
                'description' => $agent->description,
                'phone' => $agent->phone,
                'email' => $agent->email,
                'state' => $agent->state,
                'lga' => $agent->lga,
            ],
            'preferences' => array_merge(self::DEFAULTS, $agent->settings ?? []),
        ]);
    }

    public function update(Request $request)
    {
        $agent = $this->agent($request);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'state' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
            'preferences' => ['sometimes', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        $agent->fill(collect($data)->only(['description', 'phone', 'email', 'state', 'lga'])->toArray());
        if (isset($data['preferences'])) {
            $agent->settings = array_merge(self::DEFAULTS, $agent->settings ?? [], $data['preferences']);
        }
        $agent->save();
        AuditLogger::record('agent.settings_updated', $agent);

        return $this->ok(null, 'Settings saved');
    }
}
