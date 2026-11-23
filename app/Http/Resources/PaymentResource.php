<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'gateway' => $this->gateway,
            'purpose' => $this->purpose,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'gateway_reference' => $this->gateway_reference,
            'proof_path' => $this->proof_path ? asset('storage/'.$this->proof_path) : null,
            'paid_at' => $this->paid_at,
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
            'payable_type' => class_basename($this->payable_type),
            'payable_id' => $this->payable_id,
            'return_url' => match ($this->purpose) {
                'order', 'wallet_topup' => url('/payments/return'),
                'agent_invoice' => url('/agent-invoices/payments/return'),
                default => null,
            },
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
