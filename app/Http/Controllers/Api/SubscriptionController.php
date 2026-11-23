<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentSubscriptionResource;
use App\Http\Resources\PaymentResource;
use App\Models\AgentSubscription;
use App\Models\ServicePackage;
use App\Payments\PaymentManager;
use App\Services\AuditLogger;
use App\Services\PlatformSettings;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private SubscriptionService $subscriptions,
        private PaymentService $payments,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = AgentSubscription::query()
            ->with(['service', 'package', 'agent.user'])
            ->latest();

        if ($user->hasRole('agent')) {
            abort_unless($user->agent, 403, 'Complete your business profile first.');
            $query->where('agent_id', $user->agent->id);
        } else {
            $this->authorizePermission($request, 'subscription.view');
            $query->when($request->agent_id, fn ($q, $id) => $q->where('agent_id', $id))
                ->when($request->status, fn ($q, $s) => $q->where('status', $s));
        }

        return AgentSubscriptionResource::collection($query->paginate($this->perPage($request)));
    }

    /**
     * Agent selects a service package -> a PENDING subscription is created (spec §23).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('agent') && $user->agent, 403, 'Only agents with a business profile can subscribe.');
        abort_unless(PlatformSettings::requiresListingSubscription(), 409, 'Vendor subscriptions are disabled by the current listing billing mode.');

        // KYC must be approved before an agent can subscribe / pay (spec §18).
        if (! $user->agent->isKycVerified()) {
            return $this->fail('Your KYC must be approved before you can subscribe to a service.', ['kyc' => 'not_verified'], 403);
        }

        $data = $request->validate([
            'package_id' => ['required', 'exists:service_packages,id'],
        ]);

        $package = ServicePackage::with('service')->findOrFail($data['package_id']);

        if ($package->status !== 'active' || $package->service->status !== 'active') {
            return $this->fail('This package is not available.', null, 409);
        }

        $subscription = $this->subscriptions->createPending($user->agent, $package);
        AuditLogger::record('subscription.created', $subscription);

        return $this->created(
            new AgentSubscriptionResource($subscription->load(['service', 'package'])),
            'Subscription created. Complete payment to activate.'
        );
    }

    /**
     * Start a subscription payment with the chosen gateway (spec §23). Returns a
     * checkout URL (redirect gateways) or bank-transfer instructions. The subscription
     * is only activated after server-side verification of the payment.
     */
    public function pay(Request $request, AgentSubscription $subscription)
    {
        $user = $request->user();
        $ownsIt = $user->agent && $user->agent->id === $subscription->agent_id;
        abort_unless($ownsIt, 403);
        abort_unless(PlatformSettings::requiresListingSubscription(), 409, 'Vendor subscriptions are disabled by the current listing billing mode.');

        if ($subscription->status === 'active') {
            return $this->fail('Subscription is already active.', null, 409);
        }

        $data = $request->validate([
            'gateway' => ['required', Rule::in(PaymentManager::GATEWAYS)],
        ]);

        $payment = $this->payments->createForSubscription($subscription, $user, $data['gateway']);
        $subscription->update(['payment_id' => $payment->id]);
        $result = $this->payments->initialize($payment);

        AuditLogger::record('subscription.payment_initialized', $subscription, [], ['gateway' => $data['gateway']]);

        return $this->ok([
            'payment' => new PaymentResource($payment->fresh()),
            'checkout_url' => $result['checkout_url'],
            'instructions' => $result['instructions'] ?? null,
        ], 'Payment started');
    }

    public function activate(Request $request, AgentSubscription $subscription)
    {
        $this->authorizePermission($request, 'subscription.activate');

        $subscription = $this->subscriptions->activate(
            $subscription,
            $request->input('payment_reference', 'ADMIN-' . strtoupper(Str::random(10)))
        );
        AuditLogger::record('subscription.activated_by_staff', $subscription);

        return $this->ok(new AgentSubscriptionResource($subscription->load(['service', 'package'])), 'Subscription activated');
    }

    public function cancel(Request $request, AgentSubscription $subscription)
    {
        $user = $request->user();
        $ownsIt = $user->agent && $user->agent->id === $subscription->agent_id;
        abort_unless($ownsIt || $user->can('subscription.cancel'), 403);

        $this->subscriptions->cancel($subscription);
        AuditLogger::record('subscription.cancelled', $subscription);

        return $this->ok(new AgentSubscriptionResource($subscription->fresh(['service', 'package'])), 'Subscription cancelled');
    }
}
