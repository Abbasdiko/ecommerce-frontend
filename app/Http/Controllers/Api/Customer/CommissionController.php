<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();

        $base = ReferralCommission::where('referrer_user_id', $user->id);

        $summary = [
            'total_earned' => (float) (clone $base)->whereIn('status', ['available', 'paid'])->sum('commission_amount'),
            'pending' => (float) (clone $base)->where('status', 'pending')->sum('commission_amount'),
            'available' => (float) (clone $base)->where('status', 'available')->sum('commission_amount'),
            'paid' => (float) (clone $base)->where('status', 'paid')->sum('commission_amount'),
        ];

        $commissions = (clone $base)
            ->with(['referred:id,first_name,last_name', 'order:id,order_number'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok([
            'summary' => $summary,
            'referral' => [
                'code' => $user->referral_code,
                'referred_count' => User::where('referred_by', $user->id)->count(),
            ],
            'commissions' => $commissions,
        ]);
    }

    public function referrals(Request $request)
    {
        $user = $request->user();

        return $this->ok([
            'code' => $user->referral_code,
            'link' => url('/register?ref=' . $user->referral_code),
            'referred' => User::where('referred_by', $user->id)
                ->get(['id', 'first_name', 'last_name', 'created_at'])
                ->map(fn ($u) => [
                    'name' => trim($u->first_name . ' ' . $u->last_name),
                    'joined' => $u->created_at,
                ]),
        ]);
    }
}
