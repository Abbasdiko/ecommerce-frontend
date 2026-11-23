<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\AuditLogger;
use App\Services\CouponService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    use ApiResponse;

    public function __construct(private CouponService $coupons)
    {
    }

    private function agent(Request $request)
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 403, 'Complete your business profile first.');

        return $agent;
    }

    public function index(Request $request)
    {
        $agent = $this->agent($request);

        $coupons = Coupon::where('owner_type', 'agent')
            ->where('owner_id', $agent->id)
            ->withCount('usages')
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($coupons);
    }

    public function store(Request $request)
    {
        $agent = $this->agent($request);
        $data = $this->validated($request);
        $this->coupons->assertAgentAllowed($agent, $data);

        $coupon = Coupon::create($data + [
            'code' => strtoupper($data['code'] ?? Str::random(8)),
            'owner_type' => 'agent',
            'owner_id' => $agent->id,
            'funding_type' => 'agent',
        ]);
        AuditLogger::record('coupon.created', $coupon);

        return $this->created($coupon, 'Coupon created');
    }

    public function update(Request $request, Coupon $coupon)
    {
        $agent = $this->agent($request);
        abort_unless($coupon->owner_type === 'agent' && $coupon->owner_id === $agent->id, 403);

        $data = $this->validated($request, true);
        if (isset($data['discount_type'], $data['discount_value'])) {
            $this->coupons->assertAgentAllowed($agent, $data);
        }

        $coupon->update($data);
        AuditLogger::record('coupon.updated', $coupon);

        return $this->ok($coupon->fresh(), 'Coupon updated');
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        $agent = $this->agent($request);
        abort_unless($coupon->owner_type === 'agent' && $coupon->owner_id === $agent->id, 403);

        $coupon->delete();
        AuditLogger::record('coupon.deleted', $coupon);

        return $this->ok(null, 'Coupon deleted');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'code' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:30', Rule::unique('coupons', 'code')->ignore($request->route('coupon'))],
            'name' => [$rule, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => [$rule, Rule::in(['percentage', 'fixed'])],
            'discount_value' => [$rule, 'numeric', 'min:1'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'disabled'])],
        ]);
    }
}
