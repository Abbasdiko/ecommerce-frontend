<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\AuditLogger;
use App\Services\CouponValidator;
use App\Services\OrderCreationService;
use App\Services\PaymentConfig;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderCreationService $orders,
        private CouponValidator $couponValidator,
    ) {}

    private function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.listing_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function quote(Request $request)
    {
        $data = $request->validate($this->rules());

        return $this->ok($this->orders->quote($request->user(), $data));
    }

    public function validateCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.listing_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $lines = [];
        foreach ($data['items'] as $i) {
            $listing = \App\Models\Listing::published()->find($i['listing_id']);
            if (! $listing) {
                continue;
            }
            $qty = (int) $i['quantity'];
            $lines[] = ['listing' => $listing, 'quantity' => $qty, 'subtotal' => $listing->effectivePrice() * $qty];
        }

        $result = $this->couponValidator->evaluate($data['code'], $request->user(), $lines);

        return $this->ok([
            'valid' => $result['valid'],
            'reason' => $result['reason'],
            'discount' => $result['discount'],
            'code' => $result['coupon']?->code,
        ]);
    }

    public function place(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'shipping_address_id' => ['required', 'integer', Rule::exists('addresses', 'id')->where('user_id', $request->user()->id)],
            'billing_address_id' => ['nullable', 'integer', Rule::exists('addresses', 'id')->where('user_id', $request->user()->id)],
            'payment_method' => ['required', Rule::in(array_merge(PaymentConfig::checkoutMethods(), ['wallet']))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['return_url'] = url('/payments/return');

        $result = $this->orders->place($request->user(), $data);
        AuditLogger::record('order.placed', $result['order']);

        return $this->created([
            'order' => new OrderResource($result['order']->load('agentOrders.items')),
            'payment' => [
                'reference' => $result['payment']->reference,
                'status' => $result['payment']->status,
                'gateway' => $result['payment']->gateway,
            ],
            'checkout_url' => $result['checkout_url'],
            'instructions' => $result['instructions'],
        ], 'Order placed');
    }
}
