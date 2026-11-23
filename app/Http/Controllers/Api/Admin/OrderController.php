<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\OrderWorkflow;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private OrderWorkflow $workflow)
    {
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'order.view');

        $orders = Order::query()
            ->with('customer:id,first_name,last_name,email')
            ->withCount('items')
            ->when($request->search, fn ($q, $s) => $q->where('order_number', 'like', "%$s%"))
            ->when($request->order_status, fn ($q, $s) => $q->where('order_status', $s))
            ->when($request->payment_status, fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->latest('placed_at')
            ->paginate($this->perPage($request));

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorizePermission($request, 'order.view');

        $order->load([
            'customer:id,first_name,last_name,email,phone',
            'agentOrders.agent:id,business_name',
            'agentOrders.items',
            'statusHistory.changedBy:id,first_name,last_name',
            'shippingAddress', 'billingAddress',
            'cancellationRequests', 'returnRequests', 'refunds', 'disputes',
        ]);

        return $this->ok(new OrderResource($order));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizePermission($request, 'order.update');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(OrderWorkflow::FLOW))],
            'note' => ['nullable', 'string', 'max:500'],
            'force' => ['boolean'],
        ]);

        if (! empty($data['force'])) {
            $old = $order->order_status;
            $order->update(['order_status' => $data['status']]);
            $this->workflow->recordHistory($order, $data['status'], ($data['note'] ?? '') . ' (admin override)');
            AuditLogger::record('order.status_forced', $order, ['status' => $old], ['status' => $data['status']]);

            return $this->ok(new OrderResource($order->fresh()), 'Order status set (override)');
        }

        try {
            $this->workflow->transition($order, $data['status'], $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), null, 409);
        }
        AuditLogger::record('order.status_changed', $order, [], ['status' => $data['status']]);

        return $this->ok(new OrderResource($order->fresh()), 'Order updated');
    }
}
