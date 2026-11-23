<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Models\Agent;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderNotificationService;
use App\Services\PaymentConfig;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InertiaWebFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_and_registration_are_served_as_inertia_pages(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_customer_checkout_requires_a_session(): void
    {
        $this->get('/checkout')
            ->assertRedirect('/login');
    }

    public function test_checkout_shows_the_authenticated_customers_available_and_pending_wallet_balances(): void
    {
        $customer = User::create([
            'first_name' => 'Wallet',
            'last_name' => 'Customer',
            'email' => 'wallet-checkout-'.uniqid().'@example.test',
            'password' => 'password123',
            'status' => 'active',
        ]);
        $customer->assignRole(Role::findOrCreate('customer', 'web'));
        Wallet::create([
            'user_id' => $customer->id,
            'owner_type' => 'customer',
            'available_balance' => 24385,
            'pending_balance' => 1250,
            'currency' => 'NGN',
        ]);
        $listing = Listing::published()->firstOrFail();

        $this->actingAs($customer)
            ->get('/checkout?items='.urlencode(json_encode([[
                'listing_id' => $listing->id,
                'quantity' => 1,
            ]])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Customer/Checkout')
                ->where('walletBalance', 24385)
                ->where('walletPendingBalance', 1250)
                ->has('methods')
                ->has('bankAccounts'));
    }

    public function test_registration_returns_a_guest_to_their_requested_checkout(): void
    {
        $items = urlencode(json_encode([['listing_id' => 1, 'quantity' => 1]]));
        $this->get('/checkout?items='.$items)->assertRedirect('/login');

        $this->post('/register', [
            'first_name' => 'Checkout',
            'last_name' => 'Customer',
            'email' => 'checkout-flow@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirectContains('/checkout?items='.$items);
    }

    public function test_listing_detail_includes_agent_reviews_and_review_eligibility(): void
    {
        $listing = Listing::published()->firstOrFail();

        $this->get('/products/'.$listing->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Listing')
                ->has('listing')
                ->has('vendor.name')
                ->has('reviews')
                ->has('reviewEligibility.canReview'));
    }

    public function test_cart_accepts_json_and_legacy_item_query_formats(): void
    {
        $listing = Listing::published()->firstOrFail();
        $jsonItems = json_encode([[
            'listing_id' => $listing->id,
            'quantity' => 2,
            'selected_options' => ['size' => 'M', 'color' => 'Blue'],
        ]]);

        $this->get('/cart?'.http_build_query(['items' => $jsonItems]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Cart')
                ->where('items.0.listing.id', (string) $listing->id)
                ->where('items.0.quantity', 2)
                ->where('items.0.selected_options.size', 'M')
                ->where('items.0.selected_options.color', 'Blue'));

        $this->get('/cart?items='.$listing->id.':3')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Cart')
                ->where('items.0.listing.id', (string) $listing->id)
                ->where('items.0.quantity', 3));
    }

    public function test_homepage_exposes_every_marketplace_section_dataset(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Home')
                ->has('hero')
                ->has('hotDeals')
                ->has('bestSellers')
                ->has('departmentListings.new_arrivals')
                ->has('vendors')
                ->has('services')
                ->has('serviceListings')
                ->has('sponsors')
                ->has('blogs')
                ->has('storefrontNavigation.departments')
                ->has('storefrontNavigation.services')
                ->has('storefrontNavigation.vendors'));
    }

    public function test_shop_filters_support_department_vendor_and_service_selection(): void
    {
        $this->get('/shop?category=electronics&vendor=verified-shop&service=repairs&type=service')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Shop')
                ->where('filters.category', 'electronics')
                ->where('filters.vendor', 'verified-shop')
                ->where('filters.service', 'repairs')
                ->where('filters.type', 'service'));
    }

    public function test_shop_exposes_price_rating_stock_and_sort_filters(): void
    {
        $this->get('/shop?max_price=50000&rating=4.5&in_stock=1&sort=price_low')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Shop')
                ->where('filters.max_price', '50000')
                ->where('filters.rating', '4.5')
                ->where('filters.in_stock', true)
                ->where('filters.sort', 'price_low')
                ->has('brands')
                ->has('maxPrice'));
    }

    public function test_services_page_includes_product_listings_from_service_vendors(): void
    {
        $this->get('/services?type=product')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Shop')
                ->where('pageMode', 'services')
                ->where('filters.type', 'product')
                ->has('serviceCategories')
                ->has('vendors'));
    }

    public function test_services_page_returns_all_listings_for_a_selected_vendor(): void
    {
        $vendor = Agent::query()->where('status', 'active')
            ->whereHas('listings', fn ($query) => $query->published()->whereNotNull('service_id'))
            ->firstOrFail();
        $expectedCount = Listing::published()
            ->whereNotNull('service_id')
            ->where('agent_id', $vendor->id)
            ->count();

        $this->get('/services?vendor='.$vendor->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.vendor', $vendor->slug)
                ->where('listings.total', $expectedCount));
    }

    public function test_storefront_information_and_collection_pages_are_available(): void
    {
        foreach ([
            '/about' => 'Storefront/PublicPage',
            '/contact' => 'Storefront/Contact',
            '/become-vendor' => 'Storefront/PublicPage',
            '/track-order' => 'Storefront/TrackOrder',
            '/deals' => 'Storefront/Shop',
            '/vendors' => 'Storefront/Vendors',
            '/compare' => 'Storefront/LocalListings',
            '/wishlist' => 'Storefront/LocalListings',
            '/privacy-policy' => 'Storefront/PublicPage',
            '/terms-and-conditions' => 'Storefront/PublicPage',
            '/blog' => 'Storefront/Blog',
        ] as $uri => $component) {
            $this->get($uri)->assertOk()->assertInertia(fn ($page) => $page->component($component));
        }
    }

    public function test_newsletter_subscription_is_saved(): void
    {
        $this->postJson('/newsletter', [
            'email' => 'shopper@example.com',
            'source' => 'storefront',
        ])->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'shopper@example.com',
            'status' => 'subscribed',
            'source' => 'storefront',
        ]);
    }

    public function test_customer_order_details_require_a_session(): void
    {
        $this->get('/account/orders/1')
            ->assertRedirect('/login');
    }

    public function test_customer_dashboard_login_ticket_is_short_lived_and_one_time(): void
    {
        $customer = $this->createCustomer('portal-sso');
        $response = $this->actingAs($customer)->get('/account/dashboard')->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $ticket = $query['ticket'] ?? '';
        $this->assertSame(64, strlen($ticket));
        $this->assertDatabaseHas('portal_login_tickets', ['ticket_hash' => hash('sha256', $ticket)]);

        $exchange = $this->postJson('/api/v1/auth/sso/exchange', ['ticket' => $ticket])
            ->assertOk()
            ->assertJsonPath('data.user.id', $customer->id);
        $this->assertNotEmpty($exchange->json('data.token'));

        $this->postJson('/api/v1/auth/sso/exchange', ['ticket' => $ticket])
            ->assertUnauthorized();
    }

    public function test_simulated_checkout_requires_an_explicit_confirmation_before_settlement(): void
    {
        Mail::fake();
        Setting::put('payments.mode', 'simulated', 'payments');
        $customer = $this->createCustomer('simulated-payment');
        $order = $this->createOrder($customer);
        $payment = app(PaymentService::class)->create($customer, 'paystack', 'order', $order, 2500);
        $payment->update(['meta' => ['return_url' => url('/payments/return')]]);

        $initialized = app(PaymentService::class)->initialize($payment);
        $this->assertStringContainsString('localhost:5174/pay/'.$payment->reference.'?ticket=', $initialized['checkout_url']);
        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame('processing', $order->fresh()->payment_status);

        parse_str((string) parse_url($initialized['checkout_url'], PHP_URL_QUERY), $query);
        $token = $this->postJson('/api/v1/auth/sso/exchange', ['ticket' => $query['ticket']])
            ->assertOk()
            ->json('data.token');

        $this->withToken($token)->postJson('/api/v1/payments/'.$payment->reference.'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');
        $this->assertSame('successful', $order->fresh()->payment_status);
        $this->assertSame('successful', $payment->fresh()->status);
    }

    public function test_order_receipt_is_a_pdf_and_is_limited_to_its_customer(): void
    {
        $customer = $this->createCustomer('receipt-owner');
        $order = $this->createOrder($customer);

        $this->actingAs($customer)
            ->get(route('account.orders.receipt', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $otherCustomer = $this->createCustomer('receipt-other');
        $this->actingAs($otherCustomer)
            ->get(route('account.orders.receipt', $order))
            ->assertNotFound();
    }

    public function test_order_status_emails_are_sent_to_the_customer_and_company(): void
    {
        Mail::fake();
        $order = $this->createOrder($this->createCustomer('order-mail'));

        app(OrderNotificationService::class)->orderPlaced($order);

        Mail::assertSent(OrderStatusMail::class, 2);
    }

    public function test_live_mode_does_not_offer_gateways_without_secure_credentials(): void
    {
        config([
            'payments.gateways.paystack.public_key' => null,
            'payments.gateways.paystack.secret_key' => null,
            'payments.gateways.flutterwave.public_key' => null,
            'payments.gateways.flutterwave.secret_key' => null,
            'payments.gateways.flutterwave.encryption_key' => null,
            'payments.gateways.remita.merchant_id' => null,
            'payments.gateways.remita.api_key' => null,
            'payments.gateways.remita.service_type_id' => null,
        ]);
        Setting::put('payments.mode', 'live', 'payments');

        $this->assertNotContains('paystack', PaymentConfig::checkoutMethods());
        $this->assertFalse(PaymentConfig::gatewayReady('remita', [
            'merchant_id' => 'merchant',
            'api_key' => 'api-key',
            'service_type_id' => 'service',
            'base_url' => 'https://remitademo.net',
        ]));

        Setting::put('payments.paystack.public_key', 'pk_live_test', 'payments');
        Setting::put('payments.paystack.secret_key', 'sk_live_test', 'payments');
        $this->assertContains('paystack', PaymentConfig::checkoutMethods());
    }

    public function test_bank_transfer_waits_for_manual_confirmation_and_persists_instructions(): void
    {
        Mail::fake();
        $customer = $this->createCustomer('bank-transfer');
        $admin = $this->createCustomer('bank-admin');
        $order = $this->createOrder($customer);
        $order->update(['payment_method' => 'bank_transfer']);
        Setting::put('payments.bank_transfer.accounts', [[
            'bank_name' => 'Test Bank',
            'account_name' => 'Marketplace',
            'account_number' => '1234567890',
        ]], 'payments');

        $payments = app(PaymentService::class);
        $payment = $payments->create($customer, 'bank_transfer', 'order', $order, 2500);
        $result = $payments->initialize($payment);

        $this->assertSame('awaiting_verification', $payment->fresh()->status);
        $this->assertSame('awaiting_verification', $order->fresh()->payment_status);
        $this->assertSame('Test Bank', $payment->fresh()->meta['instructions']['accounts'][0]['bank_name']);
        $this->assertSame('1234567890', $result['instructions']['accounts'][0]['account_number']);

        $payments->confirmBankTransfer($payment->fresh(), $admin);
        $this->assertSame('successful', $payment->fresh()->status);
        $this->assertSame('successful', $order->fresh()->payment_status);
    }

    private function createCustomer(string $prefix): User
    {
        $customer = User::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'password123',
            'status' => 'active',
        ]);
        $customer->assignRole(Role::findOrCreate('customer', 'web'));

        return $customer;
    }

    private function createOrder(User $customer): Order
    {
        return Order::create([
            'customer_id' => $customer->id,
            'subtotal' => 2500,
            'total' => 2500,
            'currency' => 'NGN',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'payment_method' => 'paystack',
            'placed_at' => now(),
        ]);
    }
}
