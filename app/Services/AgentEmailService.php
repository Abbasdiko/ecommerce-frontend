<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AgentEmailService
{
    public function sendRegistration(User $user, Agent $agent, AgentInvoice $invoice): void
    {
        $status = $agent->verification_status === 'verified'
            ? 'Your account has been approved and is ready to use.'
            : 'Your application is awaiting verification. We will email you when it has been approved.';
        $payment = $invoice->total > 0
            ? "Invoice {$invoice->invoice_number} is ready. Sign in to review it and choose an available payment method: ".route('agent-invoices.show', $invoice)
            : "Your selected services are on a free listing plan. Invoice {$invoice->invoice_number} is marked as free.";

        Mail::raw(
            "Hello {$user->first_name},\n\nThank you for registering as an agent. {$status}\n\n{$payment}\n\nThe Marketplace team",
            fn ($message) => $message->to($user->email, $user->full_name)->subject('Your agent registration'),
        );
    }

    public function sendVerificationApproved(User $user, Agent $agent): void
    {
        if ($agent->verification_email_sent_at) {
            return;
        }

        Mail::raw(
            "Hello {$user->first_name},\n\nYour agent account for {$agent->business_name} has been verified. You can now sign in and continue setting up your services.\n\nThe Marketplace team",
            fn ($message) => $message->to($user->email, $user->full_name)->subject('Your agent account is approved'),
        );

        $agent->forceFill(['verification_email_sent_at' => now()])->save();
    }
}
