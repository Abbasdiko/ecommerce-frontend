<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    use ApiResponse;

    private function isStaff(Request $request): bool
    {
        return $request->user()->can('dispute.view');
    }

    private function authorRole(Request $request): string
    {
        $u = $request->user();
        if ($u->can('dispute.view')) {
            return 'staff';
        }

        return $u->hasRole('agent') ? 'agent' : 'customer';
    }

    private function visibleTo(Request $request, Dispute $dispute): bool
    {
        if ($this->isStaff($request)) {
            return true;
        }
        $u = $request->user();
        if ($dispute->raised_by === $u->id) {
            return true;
        }

        return $u->agent && $dispute->agent_id === $u->agent->id;
    }

    public function index(Request $request)
    {
        $query = Dispute::query()
            ->with(['order:id,order_number', 'raiser:id,first_name,last_name', 'agent:id,business_name'])
            ->withCount('messages')
            ->latest();

        if ($this->isStaff($request)) {
            $query->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->category, fn ($q, $c) => $q->where('category', $c));
        } else {
            $u = $request->user();
            $query->where(function ($q) use ($u) {
                $q->where('raised_by', $u->id);
                if ($u->agent) {
                    $q->orWhere('agent_id', $u->agent->id);
                }
            });
        }

        return $this->ok($query->paginate($this->perPage($request)));
    }

    public function show(Request $request, Dispute $dispute)
    {
        abort_unless($this->visibleTo($request, $dispute), 403);

        return $this->ok($dispute->load([
            'messages.user:id,first_name,last_name',
            'order:id,order_number,total',
            'raiser:id,first_name,last_name',
            'agent:id,business_name',
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'agent_order_id' => ['nullable', 'exists:agent_orders,id'],
            'category' => ['required', Rule::in(['item_not_received', 'item_damaged', 'wrong_item', 'refund_delay', 'other'])],
            'subject' => ['required', 'string', 'max:150'],
            'detail' => ['required', 'string', 'max:4000'],
        ]);

        $order = Order::findOrFail($data['order_id']);
        abort_unless($order->customer_id === $request->user()->id, 403, 'You can only dispute your own orders.');

        $agentId = null;
        if (! empty($data['agent_order_id'])) {
            $agentId = $order->agentOrders()->whereKey($data['agent_order_id'])->value('agent_id');
        }

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'agent_order_id' => $data['agent_order_id'] ?? null,
            'agent_id' => $agentId,
            'raised_by' => $request->user()->id,
            'category' => $data['category'],
            'subject' => $data['subject'],
            'detail' => $data['detail'],
            'status' => 'open',
        ]);
        $dispute->messages()->create([
            'user_id' => $request->user()->id,
            'author_role' => 'customer',
            'body' => $data['detail'],
        ]);
        AuditLogger::record('dispute.opened', $dispute);

        return $this->created($dispute->load('messages.user:id,first_name,last_name'), 'Dispute opened');
    }

    public function reply(Request $request, Dispute $dispute)
    {
        abort_unless($this->visibleTo($request, $dispute), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $msg = $dispute->messages()->create([
            'user_id' => $request->user()->id,
            'author_role' => $this->authorRole($request),
            'body' => $data['body'],
        ]);
        if ($dispute->status === 'open' && $this->isStaff($request)) {
            $dispute->update(['status' => 'under_review']);
        }

        return $this->created($msg->load('user:id,first_name,last_name'), 'Message sent');
    }

    public function resolve(Request $request, Dispute $dispute)
    {
        $this->authorizePermission($request, 'dispute.manage');

        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'resolved', 'rejected', 'closed'])],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $dispute->update([
            'status' => $data['status'],
            'resolution' => $data['resolution'] ?? $dispute->resolution,
            'assigned_to' => $data['assigned_to'] ?? $dispute->assigned_to,
            'resolved_by' => in_array($data['status'], ['resolved', 'rejected'], true) ? $request->user()->id : $dispute->resolved_by,
            'resolved_at' => in_array($data['status'], ['resolved', 'rejected'], true) ? now() : $dispute->resolved_at,
        ]);
        AuditLogger::record('dispute.' . $data['status'], $dispute);

        return $this->ok($dispute->fresh(), 'Dispute updated');
    }
}
