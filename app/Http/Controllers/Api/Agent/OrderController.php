<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentOrder;
use App\Services\AgentOrderService;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private AgentOrderService $workflow)
    {
    }

    private function agentId(Request $request): int
    {
        abort_unless($request->user()->agent, 403, 'Complete your business profile first.');

        return $request->user()->agent->id;
    }

    public function index(Request $request)
    {
        $orders = AgentOrder::where('agent_id', $this->agentId($request))
            ->with(['order:id,order_number,customer_id,placed_at,order_status', 'order.customer:id,first_name,last_name'])
            ->withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($orders);
    }

    public function show(Request $request, AgentOrder $agentOrder)
    {
        abort_unless($agentOrder->agent_id === $this->agentId($request), 403);

        $agentOrder->load([
            'items',
            'order.customer:id,first_name,last_name,email',
            'order.shippingAddress',
        ]);

        return $this->ok([
            'agent_order' => $agentOrder,
            'allowed_transitions' => AgentOrderService::FLOW[$agentOrder->status] ?? [],
        ]);
    }

    public function updateStatus(Request $request, AgentOrder $agentOrder)
    {
        abort_unless($agentOrder->agent_id === $this->agentId($request), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['processing', 'ready_for_dispatch', 'dispatched', 'out_for_delivery', 'delivered', 'cancelled'])],
            'tracking_number' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $agentOrder = $this->workflow->transition($agentOrder, $data['status'], $data['tracking_number'] ?? null, $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), null, 409);
        }

        AuditLogger::record('agent_order.status_changed', $agentOrder, [], ['status' => $data['status']]);

        return $this->ok($agentOrder->fresh('items'), 'Order updated');
    }
}
