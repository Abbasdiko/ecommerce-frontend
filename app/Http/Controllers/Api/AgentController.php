<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Services\AgentEmailService;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Agent::class);
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $agents = Agent::query()
            ->with('user')
            ->withCount(['listings', 'activeSubscriptions as active_subscriptions_count'])
            ->when($request->search, fn ($q, $s) => $q->where('business_name', 'like', "%$s%"))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->verification_status, fn ($q, $v) => $q->where('verification_status', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate($this->perPage($request));

        return AgentResource::collection($agents);
    }

    public function show(Request $request, Agent $agent)
    {
        $this->authorize('view', $agent);

        $agent->load(['user', 'documents', 'subscriptions.service', 'subscriptions.package'])
            ->loadCount([
                'listings',
                'activeSubscriptions as active_subscriptions_count',
                'agentOrders as paid_sales_count' => fn ($q) => $q->whereHas('order', fn ($order) => $order->where('payment_status', 'successful')),
            ])
            ->loadSum(['agentOrders as sales_total' => fn ($q) => $q->whereHas('order', fn ($order) => $order->where('payment_status', 'successful'))], 'total')
            ->loadSum(['agentOrders as company_earnings' => fn ($q) => $q->whereHas('order', fn ($order) => $order->where('payment_status', 'successful'))], 'commission');
        $agent->setRelation('paidAgentOrders', $agent->agentOrders()
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'successful'))
            ->with(['order:id,order_number,payment_status,placed_at', 'items:id,agent_order_id,product_name,quantity,total'])
            ->latest()
            ->limit(25)
            ->get());

        return $this->ok(new AgentResource($agent));
    }

    /**
     * Agent self-onboarding: create the business profile for the current agent user (spec §18).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('agent'), 403, 'Only agent accounts can create a business profile.');

        if ($user->agent) {
            return $this->fail('A business profile already exists for this account.', null, 409);
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:190'],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
        ]);

        $agent = Agent::create($data + [
            'user_id' => $user->id,
            'email' => $data['email'] ?? $user->email,
            'status' => 'pending',
            'verification_status' => 'pending',
        ]);

        AuditLogger::record('agent.onboarded', $agent);

        return $this->created(new AgentResource($agent->load('user')), 'Business profile submitted for review');
    }

    public function update(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $data = $request->validate([
            'business_name' => ['sometimes', 'string', 'max:190'],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
        ]);

        $agent->update($data);
        AuditLogger::record('agent.updated', $agent);

        return $this->ok(new AgentResource($agent->fresh('user')), 'Agent updated');
    }

    public function destroy(Request $request, Agent $agent)
    {
        $this->authorize('delete', $agent);

        $agent->delete();
        AuditLogger::record('agent.deleted', $agent);

        return $this->ok(null, 'Agent deleted');
    }

    public function approve(Request $request, Agent $agent, AgentEmailService $emails)
    {
        $this->authorize('approve', $agent);

        $agent->update([
            'status' => 'active',
            'verification_status' => 'verified',
            'rejection_reason' => null,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        AuditLogger::record('agent.approved', $agent);
        $emails->sendVerificationApproved($agent->user, $agent);

        return $this->ok(new AgentResource($agent->fresh('user')), 'Agent approved');
    }

    public function reject(Request $request, Agent $agent)
    {
        $this->authorize('reject', $agent);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $agent->update([
            'status' => 'rejected',
            'verification_status' => 'rejected',
            'rejection_reason' => $data['reason'],
        ]);

        AuditLogger::record('agent.rejected', $agent, [], ['reason' => $data['reason']]);

        return $this->ok(new AgentResource($agent->fresh('user')), 'Agent rejected');
    }

    public function kyc(Request $request, Agent $agent)
    {
        $this->authorize('view', $agent);

        return $this->ok([
            'agent_id' => $agent->id,
            'business_name' => $agent->business_name,
            'verification_status' => $agent->verification_status,
            'kyc_submitted_at' => $agent->kyc_submitted_at,
            'rejection_reason' => $agent->rejection_reason,
            'documents' => $agent->documents()->latest()->get()->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'status' => $d->status,
                'review_note' => $d->review_note,
                'file_url' => asset('storage/' . $d->file_path),
                'uploaded_at' => $d->created_at,
            ]),
        ]);
    }

    public function kycApprove(Request $request, Agent $agent, AgentEmailService $emails)
    {
        $this->authorize('approve', $agent);

        $agent->update([
            'verification_status' => 'verified',
            'rejection_reason' => null,
            'status' => $agent->status === 'pending' ? 'active' : $agent->status,
            'approved_at' => $agent->approved_at ?? now(),
            'approved_by' => $agent->approved_by ?? $request->user()->id,
        ]);
        $agent->documents()->update(['status' => 'approved']);
        AuditLogger::record('kyc.approved', $agent);
        $emails->sendVerificationApproved($agent->user, $agent);

        return $this->ok(new AgentResource($agent->fresh('user')), 'KYC approved');
    }

    public function kycReject(Request $request, Agent $agent)
    {
        $this->authorize('reject', $agent);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $agent->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $data['reason'],
        ]);
        $agent->documents()->update(['status' => 'rejected', 'review_note' => $data['reason']]);
        AuditLogger::record('kyc.rejected', $agent, [], $data);

        return $this->ok(new AgentResource($agent->fresh('user')), 'KYC rejected');
    }

    public function suspend(Request $request, Agent $agent)
    {
        $this->authorize('suspend', $agent);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'deactivated'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $agent->update(['status' => $data['status']]);
        AuditLogger::record('agent.status_changed', $agent, [], $data);

        return $this->ok(new AgentResource($agent->fresh('user')), 'Agent status updated');
    }
}
