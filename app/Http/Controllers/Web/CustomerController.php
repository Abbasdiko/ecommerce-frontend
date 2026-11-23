<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Listing;
use App\Models\Order;
use App\Payments\PaymentManager;
use App\Services\OrderCreationService;
use App\Services\PaymentConfig;
use App\Services\PaymentService;
use App\Services\PlatformSettings;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CustomerController extends Controller
{
    public function account(Request $request, WalletService $wallets): Response
    {
        $user = $request->user();
        $wallet = $wallets->walletFor($user);
        $pendingDeposit = $request->session()->get('wallet_deposit');
        if ($pendingDeposit) {
            $pendingPayment = \App\Models\Payment::where('reference', $pendingDeposit['reference'])
                ->where('user_id', $user->id)
                ->first();
            $pendingDeposit['proof_uploaded'] = filled($pendingPayment?->proof_path);
        }

        return Inertia::render('Customer/Account', [
            'orders' => $user->orders()->latest('placed_at')->take(10)->get([
                'id', 'order_number', 'total', 'payment_status', 'order_status', 'placed_at',
            ]),
            'wallet' => [
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
                'currency' => $wallet->currency,
            ],
            'walletTransactions' => $wallet->transactions()
                ->latest('created_at')
                ->take(8)
                ->get(['id', 'type', 'direction', 'amount', 'description', 'status', 'created_at']),
            'depositMethods' => array_values(array_filter(
                app(PaymentManager::class)->options(),
                fn ($method) => $method['id'] !== 'pay_on_delivery',
            )),
            'pendingDeposit' => $pendingDeposit,
        ]);
    }

    public function dashboard(Request $request): HttpResponse
    {
        $ticket = app(\App\Services\PortalLoginTicketService::class)->issue($request->user());
        $dashboardUrl = rtrim((string) config('app.dashboard_url'), '/');
        abort_if($dashboardUrl === '', 500, 'The dashboard URL is not configured.');

        return Inertia::location($dashboardUrl.'/sso?ticket='.urlencode($ticket));
    }

    public function showOrder(Request $request, Order $order): Response
    {
        $order = Order::query()
            ->where('customer_id', $request->user()->id)
            ->with([
                'agentOrders.agent:id,business_name',
                'agentOrders.items',
                'statusHistory',
                'shippingAddress',
                'billingAddress',
                'cancellationRequests',
                'returnRequests',
            ])
            ->findOrFail($order->id);

        return Inertia::render('Customer/Order', [
            'order' => (new OrderResource($order))->resolve($request),
        ]);
    }

    public function fundWallet(Request $request, PaymentService $payments, WalletService $wallets): HttpResponse
    {
        $methods = array_values(array_intersect(
            PaymentConfig::checkoutMethods(),
            ['paystack', 'flutterwave', 'remita', 'bank_transfer'],
        ));
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'payment_method' => ['required', Rule::in($methods)],
        ]);
        $wallet = $wallets->walletFor($request->user());
        $payment = $payments->createWalletTopUp(
            $request->user(),
            $wallet,
            $data['payment_method'],
            (float) $data['amount'],
        );
        $payment->update(['meta' => array_merge($payment->meta ?? [], [
            'return_url' => url('/payments/return'),
        ])]);
        try {
            $result = $payments->initialize($payment);
        } catch (PaymentGatewayException $exception) {
            return back()->withErrors(['payment_method' => $exception->getMessage()])->withInput();
        }

        if ($result['checkout_url']) {
            return Inertia::location($result['checkout_url']);
        }

        $request->session()->put('wallet_deposit', [
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'gateway' => $payment->gateway,
            'instructions' => $result['instructions'] ?? null,
        ]);

        return redirect()->route('account.show')->with('success', 'Wallet funding instructions are ready.');
    }

    public function cart(Request $request): Response
    {
        $cartItems = $this->cartItems($request->query('items'));
        $listings = $this->listingQuery()
            ->whereIn('id', array_keys($cartItems))
            ->get()
            ->map(fn (Listing $listing) => [
                'listing' => $this->listingShape($listing),
                'quantity' => $cartItems[$listing->id]['quantity'],
                'selected_options' => $cartItems[$listing->id]['selected_options'],
            ])
            ->values();

        return Inertia::render('Storefront/Cart', ['items' => $listings]);
    }

    public function checkout(Request $request, OrderCreationService $orders, WalletService $wallets): Response
    {
        $cartItems = $this->cartItems($request->query('items'));
        $items = collect($cartItems)->map(fn ($item, $id) => [
            'listing_id' => (int) $id,
            'quantity' => $item['quantity'],
            'selected_options' => $item['selected_options'],
        ])->values()->all();
        $couponCode = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ])['coupon_code'] ?? null;
        $quote = $items ? $orders->quote($request->user(), [
            'items' => $items,
            'coupon_code' => $couponCode,
        ])['quote'] : null;
        $wallet = $wallets->walletFor($request->user())->fresh();

        return Inertia::render('Customer/Checkout', [
            'items' => $items,
            'quote' => $quote,
            'addresses' => $request->user()->addresses()->where('type', 'shipping')->orderByDesc('is_default')->get(),
            'methods' => app(PaymentManager::class)->options(),
            'walletBalance' => (float) $wallet->available_balance,
            'walletPendingBalance' => (float) $wallet->pending_balance,
            'bankAccounts' => PaymentConfig::bankAccounts(),
            'paymentMode' => PaymentConfig::mode(),
        ]);
    }

    public function placeOrder(Request $request, OrderCreationService $orders): HttpResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.listing_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.selected_options' => ['nullable', 'array'],
            'items.*.selected_options.size' => ['nullable', 'string', 'max:80'],
            'items.*.selected_options.color' => ['nullable', 'string', 'max:80'],
            'shipping_address_id' => [
                'required', 'integer',
                Rule::exists('addresses', 'id')->where('user_id', $request->user()->id),
            ],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['required', Rule::in(array_merge(PaymentConfig::checkoutMethods(), ['wallet']))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['return_url'] = url('/payments/return');
        try {
            $result = $orders->place($request->user(), $data);
        } catch (PaymentGatewayException $exception) {
            return back()->withErrors(['payment_method' => $exception->getMessage()])->withInput();
        }
        $request->session()->put('last_order_number', $result['order']->order_number);

        if ($result['checkout_url']) {
            return Inertia::location($result['checkout_url']);
        }

        return redirect()->route('checkout.complete');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:180'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'max:80'],
            'items' => ['nullable', 'string', 'max:12000'],
        ]);
        $request->user()->addresses()->create([
            'type' => 'shipping',
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'],
            'line1' => $data['line1'],
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => 'Nigeria',
            'is_default' => true,
        ]);

        return redirect()->route('checkout.show', array_filter(['items' => $data['items'] ?? null]));
    }

    public function complete(Request $request): Response
    {
        $orderNumber = $request->session()->get('last_order_number');
        abort_unless($orderNumber, 404);
        $order = $request->user()->orders()
            ->with(['payments' => fn ($query) => $query->latest()])
            ->where('order_number', $orderNumber)
            ->firstOrFail();
        $payment = $order->payments->first();
        $instructions = $payment?->meta['instructions'] ?? null;
        if ($instructions) {
            $instructions['reference'] = $payment->reference;
        }

        return Inertia::render('Customer/OrderComplete', [
            'order' => $order->only('id', 'order_number', 'total', 'payment_status', 'order_status', 'payment_method'),
            'instructions' => $instructions,
            'receiptUrl' => route('account.orders.receipt', $order),
        ]);
    }

    public function receipt(Request $request, Order $order)
    {
        $order = $request->user()->orders()
            ->with(['items', 'shippingAddress', 'customer', 'payments'])
            ->findOrFail($order->id);

        return app('dompdf.wrapper')
            ->loadView('pdf.order-receipt', [
                'order' => $order,
                'payment' => $order->payments->sortByDesc('created_at')->first(),
                'company' => [
                    'logo' => PlatformSettings::brandAssetDataUri('platform.logo'),
                    'name' => PlatformSettings::value('platform.name'),
                    'tagline' => PlatformSettings::value('platform.tagline'),
                    'address' => PlatformSettings::value('platform.address'),
                    'phone' => PlatformSettings::value('platform.phone'),
                    'email' => PlatformSettings::value('platform.support_email'),
                ],
            ])
            ->setPaper('a4')
            ->download("receipt-{$order->order_number}.pdf");
    }

    public function paymentReturn(Request $request, string $reference, PaymentService $payments): RedirectResponse
    {
        $payment = \App\Models\Payment::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $payment = $payments->verifyAndSettle($payment);

        if ($payment->purpose === 'wallet_topup') {
            $request->session()->forget('wallet_deposit');

            return redirect()->route('account.show')->with(
                $payment->isSuccessful() ? 'success' : 'error',
                $payment->isSuccessful() ? 'Wallet funding confirmed.' : 'Wallet funding could not be confirmed.',
            );
        }

        if ($payment->payable instanceof Order) {
            $request->session()->put('last_order_number', $payment->payable->order_number);
        }

        return redirect()->route('checkout.complete')
            ->with($payment->isSuccessful() ? 'success' : 'error', $payment->isSuccessful()
                ? 'Payment confirmed.'
                : 'Payment could not be confirmed. Please check your orders or contact support.');
    }

    public function uploadPaymentProof(Request $request, string $reference): RedirectResponse
    {
        $payment = \App\Models\Payment::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->where('gateway', 'bank_transfer')
            ->firstOrFail();
        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $payment->update([
            'proof_path' => $data['proof']->store('payment-proofs', 'public'),
            'status' => 'awaiting_verification',
        ]);

        if ($request->session()->get('wallet_deposit.reference') === $reference) {
            $request->session()->put('wallet_deposit.proof_uploaded', true);
        }

        return back()->with('success', 'Transfer proof uploaded for review.');
    }

    private function cartItems(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $items = [];
        if (is_array($decoded)) {
            foreach (array_slice($decoded, 0, 100) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $id = $entry['listing_id'] ?? $entry['id'] ?? null;
                $quantity = $entry['quantity'] ?? null;
                if (! ctype_digit((string) $id) || ! ctype_digit((string) $quantity)) {
                    continue;
                }
                $options = $entry['selected_options'] ?? [];
                $items[(int) $id] = [
                    'quantity' => min(99, max(1, (int) $quantity)),
                    'selected_options' => array_filter([
                        'size' => is_string($options['size'] ?? null) ? $options['size'] : null,
                        'color' => is_string($options['color'] ?? null) ? $options['color'] : null,
                    ]),
                ];
            }

            return $items;
        }

        foreach (array_slice(explode(',', $raw), 0, 100) as $entry) {
            [$id, $quantity] = array_pad(explode(':', $entry, 2), 2, null);
            if (ctype_digit((string) $id) && ctype_digit((string) $quantity)) {
                $items[(int) $id] = [
                    'quantity' => min(99, max(1, (int) $quantity)),
                    'selected_options' => [],
                ];
            }
        }

        return $items;
    }

    private function listingQuery()
    {
        return Listing::query()->published()->with([
            'agent:id,slug,business_name,state,verification_status',
            'category:id,name,slug,parent_id',
            'category.parent:id,slug',
            'service:id,name,slug',
            'images',
        ])->withCount('reviews')->withAvg('reviews as reviews_avg_rating', 'rating');
    }

    private function listingShape(Listing $listing): array
    {
        return (new \App\Http\Resources\Storefront\ListingResource($listing))->resolve();
    }
}
