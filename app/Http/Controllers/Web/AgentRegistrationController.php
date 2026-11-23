<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentInvoice;
use App\Models\AgentInvoiceItem;
use App\Models\AgentService;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\AgentEmailService;
use App\Services\AuditLogger;
use App\Services\PaymentConfig;
use App\Services\PaymentService;
use App\Services\PlatformSettings;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AgentRegistrationController extends Controller
{
    public function register(Request $request, SubscriptionService $subscriptions, AgentEmailService $emails): RedirectResponse
    {
        $existingUser = $request->user();
        $billingMode = PlatformSettings::listingBillingMode();
        $requiresSubscription = PlatformSettings::requiresListingSubscription();
        $data = $request->validate([
            'first_name' => [$existingUser ? 'nullable' : 'required', 'string', 'max:100'],
            'last_name' => [$existingUser ? 'nullable' : 'required', 'string', 'max:100'],
            'email' => [$existingUser ? 'nullable' : 'required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($existingUser?->id)],
            'password' => [$existingUser ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_selections' => ['required', 'array', 'min:1'],
            'service_selections.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'service_selections.*.package_id' => ['nullable', 'integer', 'exists:service_packages,id'],
        ]);

        if ($existingUser?->agent) {
            throw ValidationException::withMessages([
                'business_name' => ['This account already has an agent profile. Sign in to your agent dashboard to manage it.'],
            ]);
        }

        $selections = $data['service_selections'];
        $serviceIds = collect($selections)->pluck('service_id')->map(fn ($id) => (int) $id);
        $services = Service::query()
            ->whereIn('id', $serviceIds)
            ->where('status', 'active')
            ->with(['packages' => fn ($query) => $query->where('status', 'active')])
            ->get()
            ->keyBy('id');

        if ($services->count() !== $serviceIds->unique()->count()) {
            throw ValidationException::withMessages([
                'service_selections' => ['Choose services that are currently available.'],
            ]);
        }

        if ($requiresSubscription) {
            foreach ($selections as $index => $selection) {
                $service = $services->get((int) $selection['service_id']);
                $package = $service?->packages->firstWhere('id', (int) ($selection['package_id'] ?? 0));
                if (! $package) {
                    throw ValidationException::withMessages([
                        "service_selections.{$index}.package_id" => ['Choose an available plan for each selected service.'],
                    ]);
                }
            }
        }

        $autoApprove = (bool) PlatformSettings::value('agents.auto_approve', false);
        $invoice = DB::transaction(function () use ($data, $existingUser, $selections, $services, $billingMode, $requiresSubscription, $autoApprove, $subscriptions) {
            $user = $existingUser ?: User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
            ]);
            $user->assignRole('agent');

            $agent = Agent::create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'description' => $data['description'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $user->email,
                'address' => $data['address'] ?? null,
                'state' => $data['state'] ?? null,
                'status' => $autoApprove ? 'active' : 'pending',
                'verification_status' => $autoApprove ? 'verified' : 'pending',
                'approved_at' => $autoApprove ? now() : null,
            ]);

            $total = 0;
            $invoice = AgentInvoice::create([
                'agent_id' => $agent->id,
                'billing_mode' => $billingMode,
                'currency' => config('payments.default_currency', 'NGN'),
                'status' => $requiresSubscription ? 'unpaid' : 'free',
            ]);

            foreach ($selections as $selection) {
                $service = $services->get((int) $selection['service_id']);
                $package = $requiresSubscription
                    ? $service->packages->firstWhere('id', (int) $selection['package_id'])
                    : null;
                $subscription = $package ? $subscriptions->createPending($agent, $package) : null;
                $amount = $package ? (float) $package->price : 0;
                $total += $amount;

                AgentService::create(['agent_id' => $agent->id, 'service_id' => $service->id]);
                AgentInvoiceItem::create([
                    'agent_invoice_id' => $invoice->id,
                    'service_id' => $service->id,
                    'package_id' => $package?->id,
                    'subscription_id' => $subscription?->id,
                    'description' => $package ? "{$service->name} — {$package->name}" : "{$service->name} — Free listing",
                    'amount' => $amount,
                ]);
            }

            $invoice->update([
                'subtotal' => $total,
                'total' => $total,
                'paid_at' => $requiresSubscription ? null : now(),
            ]);

            AuditLogger::record('agent.registered', $agent, [], ['invoice_number' => $invoice->invoice_number]);

            return $invoice->load('agent.user');
        });

        if (! $existingUser) {
            Auth::login($invoice->agent->user);
            $request->session()->regenerate();
        }

        $emails->sendRegistration($invoice->agent->user, $invoice->agent, $invoice);
        $invoice->agent->forceFill(['welcome_email_sent_at' => now()])->save();
        if ($autoApprove) {
            $emails->sendVerificationApproved($invoice->agent->user, $invoice->agent);
        }

        return redirect()->route('agent-invoices.show', $invoice)
            ->with('success', $requiresSubscription
                ? 'Your account has been created. Review and pay the invoice while your application is being verified.'
                : 'Your free-listing account and invoice have been created. Your application is awaiting verification.');
    }

    public function show(Request $request, AgentInvoice $invoice): Response
    {
        $invoice = $this->ownedInvoice($request, $invoice);
        $invoice->load(['items.service', 'items.package']);
        $payment = $invoice->payments()->latest()->first();

        return Inertia::render('Storefront/AgentInvoice', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'subtotal' => (float) $invoice->subtotal,
                'total' => (float) $invoice->total,
                'paid_at' => optional($invoice->paid_at)->toIso8601String(),
                'items' => $invoice->items->map(fn ($item) => [
                    'description' => $item->description,
                    'service' => $item->service->name,
                    'plan' => $item->package?->name,
                    'amount' => (float) $item->amount,
                ])->values(),
            ],
            'payment' => $payment ? [
                'reference' => $payment->reference,
                'gateway' => $payment->gateway,
                'status' => $payment->status,
                'proof_uploaded' => (bool) $payment->proof_path,
                'instructions' => $payment->meta['instructions'] ?? null,
            ] : null,
            'paymentChannels' => array_values(array_filter(
                PaymentConfig::checkoutMethods(),
                fn ($method) => in_array($method, array_merge(PaymentConfig::GATEWAYS, ['bank_transfer']), true),
            )),
            'bankAccounts' => PaymentConfig::bankAccounts(),
        ]);
    }

    public function pay(Request $request, AgentInvoice $invoice, PaymentService $payments): HttpResponse
    {
        $invoice = $this->ownedInvoice($request, $invoice);
        $allowedMethods = array_values(array_filter(
            PaymentConfig::checkoutMethods(),
            fn ($method) => in_array($method, array_merge(PaymentConfig::GATEWAYS, ['bank_transfer']), true),
        ));
        $data = $request->validate(['gateway' => ['required', Rule::in($allowedMethods)]]);

        if ((float) $invoice->total <= 0 || in_array($invoice->status, ['paid', 'free'], true)) {
            throw ValidationException::withMessages(['gateway' => ['This invoice does not need payment.']]);
        }

        $openPayment = $invoice->payments()->whereIn('status', ['pending', 'processing', 'awaiting_verification'])->first();
        if ($openPayment) {
            throw ValidationException::withMessages([
                'gateway' => ['A payment for this invoice is already in progress. Complete it or contact support before trying again.'],
            ]);
        }

        $payment = $payments->createForAgentInvoice($invoice, $request->user(), $data['gateway']);
        $payment->update(['meta' => ['return_url' => url('/agent-invoices/payments/return')]]);
        try {
            $result = $payments->initialize($payment);
        } catch (PaymentGatewayException $exception) {
            return back()->withErrors(['gateway' => $exception->getMessage()])->withInput();
        }

        if ($payment->gateway === 'bank_transfer') {
            $invoice->update(['status' => 'awaiting_verification']);

            return redirect()->route('agent-invoices.show', $invoice)
                ->with('success', 'Transfer the invoice amount to the listed account and upload your payment receipt.');
        }

        $invoice->update(['status' => 'payment_pending']);

        return Inertia::location($result['checkout_url']);
    }

    public function returnFromGateway(Request $request, string $reference, PaymentService $payments): RedirectResponse
    {
        $payment = Payment::query()
            ->where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->where('purpose', 'agent_invoice')
            ->firstOrFail();
        $invoice = AgentInvoice::findOrFail($payment->payable_id);
        $payment = $payments->verifyAndSettle($payment);

        if ($payment->status === 'successful') {
            return redirect()->route('agent-invoices.show', $invoice)->with('success', 'Payment received. Your invoice is paid.');
        }

        if ($payment->status === 'failed') {
            $invoice->update(['status' => 'payment_failed']);

            return redirect()->route('agent-invoices.show', $invoice)->withErrors(['payment' => 'Payment could not be verified. Please try another available channel.']);
        }

        return redirect()->route('agent-invoices.show', $invoice)->with('success', 'Payment is still being verified.');
    }

    public function uploadProof(Request $request, AgentInvoice $invoice): RedirectResponse
    {
        $invoice = $this->ownedInvoice($request, $invoice);
        $payment = $invoice->payments()->where('gateway', 'bank_transfer')
            ->where('status', 'awaiting_verification')->latest()->firstOrFail();
        $data = $request->validate(['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);

        $payment->update(['proof_path' => $data['proof']->store('payment-proofs', 'public')]);

        return back()->with('success', 'Payment receipt uploaded. Our team will verify the transfer.');
    }

    private function ownedInvoice(Request $request, AgentInvoice $invoice): AgentInvoice
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 404);
        abort_unless((int) $invoice->agent_id === (int) $agent->id, 404);

        return $invoice;
    }
}
