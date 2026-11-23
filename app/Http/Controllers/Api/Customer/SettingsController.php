<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    use ApiResponse;

    private const DEFAULT_SETTINGS = [
        'notify_order_updates' => true,
        'notify_promotions' => true,
        'notify_price_drops' => false,
        'newsletter' => true,
    ];

    public function show(Request $request)
    {
        $user = $request->user();
        $profile = CustomerProfile::firstOrCreate(['user_id' => $user->id]);

        return $this->ok([
            'profile' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'date_of_birth' => $profile->date_of_birth?->toDateString(),
                'gender' => $profile->gender,
            ],
            'notifications' => array_merge(self::DEFAULT_SETTINGS, $profile->settings ?? []),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $profile = CustomerProfile::firstOrCreate(['user_id' => $user->id]);

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'notifications' => ['sometimes', 'array'],
            'notifications.*' => ['boolean'],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user->fill(collect($data)->only(['first_name', 'last_name', 'phone'])->toArray());
        if (! empty($data['new_password'])) {
            $user->password = Hash::make($data['new_password']);
        }
        $user->name = trim($user->first_name . ' ' . $user->last_name);
        $user->save();

        $profile->fill(collect($data)->only(['date_of_birth', 'gender'])->toArray());
        if (isset($data['notifications'])) {
            $profile->settings = array_merge(self::DEFAULT_SETTINGS, $profile->settings ?? [], $data['notifications']);
        }
        $profile->save();

        return $this->ok(null, 'Settings saved');
    }
}
