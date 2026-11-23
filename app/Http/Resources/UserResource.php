<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'email_verified_at' => $this->email_verified_at,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'customer_profile' => $this->whenLoaded('customerProfile', fn () => $this->customerProfile ? [
                'date_of_birth' => $this->customerProfile->date_of_birth?->toDateString(),
                'gender' => $this->customerProfile->gender,
                'avatar' => $this->customerProfile->avatar,
            ] : null),
            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn ($address) => [
                'id' => $address->id,
                'label' => $address->label,
                'type' => $address->type,
                'contact_name' => $address->contact_name,
                'contact_phone' => $address->contact_phone,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'lga' => $address->lga,
                'country' => $address->country,
                'is_default' => $address->is_default,
            ])),
        ];
    }
}
