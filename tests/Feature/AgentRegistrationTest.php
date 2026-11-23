<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentInvoice;
use App\Models\AgentSubscription;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Setting;
use App\Models\User;
use App\Services\AgentEmailService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgentRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('agent', 'web');
    }

    public function test_free_listing_registration_creates_pending_agent_and_free_invoice(): void
    {
        Setting::put('agents.listing_billing_mode', 'commission', 'commission');
        $service = $this->createService('Home repairs');
        Mail::shouldReceive('raw')->once();

        $response = $this->post('/become-vendor', $this->registrationData([
            ['service_id' => $service->id],
        ]));

        $response->assertRedirect();
        $user = User::where('email', 'agent@example.test')->firstOrFail();
        $agent = Agent::where('user_id', $user->id)->firstOrFail();
        $invoice = AgentInvoice::where('agent_id', $agent->id)->firstOrFail();

        $this->assertSame('pending', $agent->status);
        $this->assertSame('pending', $agent->verification_status);
        $this->assertSame('free', $invoice->status);
        $this->assertSame('0.00', $invoice->total);
        $this->assertNotNull($agent->welcome_email_sent_at);
        $this->assertSame(0, AgentSubscription::where('agent_id', $agent->id)->count());
        $this->assertDatabaseHas('agent_services', ['agent_id' => $agent->id, 'service_id' => $service->id]);
    }

    public function test_subscription_registration_uses_one_invoice_and_activates_selected_plans_after_payment(): void
    {
        Setting::put('agents.listing_billing_mode', 'subscription', 'commission');
        $service = $this->createService('Cleaning');
        $package = ServicePackage::create([
            'service_id' => $service->id,
            'name' => 'Standard',
            'price' => 12500,
            'duration_days' => 30,
            'listing_limit' => 8,
            'status' => 'active',
        ]);

        $response = $this->post('/become-vendor', $this->registrationData([
            ['service_id' => $service->id, 'package_id' => $package->id],
        ]));
        $user = User::where('email', 'agent@example.test')->firstOrFail();
        $agent = Agent::where('user_id', $user->id)->firstOrFail();
        $invoice = AgentInvoice::where('agent_id', $agent->id)->firstOrFail();
        $subscription = AgentSubscription::where('agent_id', $agent->id)->firstOrFail();

        $response->assertRedirect(route('agent-invoices.show', $invoice));
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('12500.00', $invoice->total);
        $this->assertSame('pending', $subscription->status);

        $payResponse = $this->post(route('agent-invoices.pay', $invoice), ['gateway' => 'paystack']);
        $payment = $invoice->payments()->firstOrFail();
        $payResponse->assertRedirectContains('localhost:5174/pay/'.$payment->reference.'?ticket=');

        parse_str((string) parse_url($payResponse->headers->get('Location'), PHP_URL_QUERY), $query);
        $token = $this->postJson('/api/v1/auth/sso/exchange', ['ticket' => $query['ticket']])
            ->assertOk()
            ->json('data.token');
        $this->withToken($token)
            ->postJson('/api/v1/payments/'.$payment->reference.'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');
        $this->get(route('agent-invoices.return', $payment->reference))
            ->assertRedirect(route('agent-invoices.show', $invoice));

        $this->assertSame('successful', $payment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame('pending', $agent->fresh()->status);
    }

    public function test_selected_package_must_belong_to_selected_service(): void
    {
        Setting::put('agents.listing_billing_mode', 'subscription', 'commission');
        $firstService = $this->createService('Photography');
        $otherService = $this->createService('Event planning');
        $package = ServicePackage::create([
            'service_id' => $otherService->id,
            'name' => 'Standard',
            'price' => 1000,
            'duration_days' => 30,
            'listing_limit' => 5,
            'status' => 'active',
        ]);

        $this->from('/become-vendor')
            ->post('/become-vendor', $this->registrationData([
                ['service_id' => $firstService->id, 'package_id' => $package->id],
            ]))
            ->assertSessionHasErrors('service_selections.0.package_id');

        $this->assertDatabaseMissing('users', ['email' => 'agent@example.test']);
    }

    public function test_bank_transfer_proof_can_be_uploaded_and_admin_confirmation_pays_invoice(): void
    {
        Setting::put('agents.listing_billing_mode', 'subscription', 'commission');
        $service = $this->createService('Appliance repair');
        $package = ServicePackage::create([
            'service_id' => $service->id,
            'name' => 'Basic',
            'price' => 5000,
            'duration_days' => 30,
            'listing_limit' => 4,
            'status' => 'active',
        ]);
        $this->post('/become-vendor', $this->registrationData([
            ['service_id' => $service->id, 'package_id' => $package->id],
        ]));
        $agent = Agent::whereHas('user', fn ($query) => $query->where('email', 'agent@example.test'))->firstOrFail();
        $invoice = AgentInvoice::where('agent_id', $agent->id)->firstOrFail();
        $this->post(route('agent-invoices.pay', $invoice), ['gateway' => 'bank_transfer'])
            ->assertRedirect(route('agent-invoices.show', $invoice));

        $payment = $invoice->payments()->firstOrFail();
        $this->assertSame('awaiting_verification', $payment->status);
        $this->assertSame('awaiting_verification', $invoice->fresh()->status);

        Storage::fake('public');
        $this->post(route('agent-invoices.proof', $invoice), [
            'proof' => UploadedFile::fake()->create('transfer.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $payment->refresh();
        Storage::disk('public')->assertExists($payment->proof_path);

        $admin = User::create([
            'first_name' => 'Finance',
            'last_name' => 'Admin',
            'email' => 'finance-admin@example.test',
            'password' => 'password',
        ]);
        app(PaymentService::class)->confirmBankTransfer($payment, $admin);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('active', $agent->subscriptions()->firstOrFail()->status);
    }

    public function test_approval_email_is_sent_only_once(): void
    {
        $user = User::create([
            'first_name' => 'Taylor',
            'last_name' => 'Agent',
            'email' => 'approved-agent@example.test',
            'password' => 'password',
        ]);
        $agent = Agent::create([
            'user_id' => $user->id,
            'business_name' => 'Approved business',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        Mail::shouldReceive('raw')->once();
        $emails = app(AgentEmailService::class);
        $emails->sendVerificationApproved($user, $agent);
        $emails->sendVerificationApproved($user, $agent->fresh());

        $this->assertNotNull($agent->fresh()->verification_email_sent_at);
    }

    private function createService(string $name): Service
    {
        return Service::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'status' => 'active',
        ]);
    }

    private function registrationData(array $selections): array
    {
        return [
            'first_name' => 'Taylor',
            'last_name' => 'Agent',
            'email' => 'agent@example.test',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
            'business_name' => 'Taylor Services',
            'phone' => '08000000000',
            'address' => '12 Sample Street',
            'state' => 'Lagos',
            'description' => 'A local service business.',
            'service_selections' => $selections,
        ];
    }
}
