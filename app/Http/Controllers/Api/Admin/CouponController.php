<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'coupon.view');

        $coupons = Coupon::query()
            ->withCount('usages')
            ->when($request->owner_type, fn ($q, $t) => $q->where('owner_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('code', 'like', "%$s%")->orWhere('name', 'like', "%$s%"))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($coupons);
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, 'coupon.create');

        $data = $this->validated($request);
        $coupon = Coupon::create($data + [
            'code' => strtoupper($data['code'] ?? Str::random(8)),
            'owner_type' => 'platform',
            'owner_id' => null,
        ]);
        AuditLogger::record('coupon.platform_created', $coupon);

        return $this->created($coupon, 'Platform coupon created');
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizePermission($request, 'coupon.update');

        $coupon->update($this->validated($request, true));
        AuditLogger::record('coupon.updated', $coupon);

        return $this->ok($coupon->fresh(), 'Coupon updated');
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        $this->authorizePermission($request, 'coupon.delete');
        $coupon->delete();
        AuditLogger::record('coupon.deleted', $coupon);

        return $this->ok(null, 'Coupon deleted');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'code' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:30', Rule::unique('coupons', 'code')->ignore($request->route('coupon'))],
            'name' => [$req, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => [$req, Rule::in(['percentage', 'fixed'])],
            'discount_value' => [$req, 'numeric', 'min:1'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_per_customer' => ['nullable', 'integer', 'min:1'],
            'funding_type' => ['nullable', Rule::in(['platform', 'agent', 'shared'])],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'disabled'])],
        ]);
    }
}
