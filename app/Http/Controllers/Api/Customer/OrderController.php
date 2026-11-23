<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $orders = Order::where('customer_id', $request->user()->id)
            ->with('cancellationRequests')
            ->withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('order_status', $s))
            ->when($request->payment_status, fn ($q, $s) => $q->where('payment_status', $s))
            ->latest('placed_at')
            ->paginate($this->perPage($request));

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        $order->load([
            'agentOrders.agent:id,business_name',
            'agentOrders.items',
            'statusHistory',
            'shippingAddress',
            'billingAddress',
            'cancellationRequests',
            'returnRequests',
        ]);

        return $this->ok(new OrderResource($order));
    }

    public function requestCancellation(Request $request, Order $order)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        if (! in_array($order->order_status, Order::CUSTOMER_CANCELLABLE, true)) {
            return $this->fail('This order can no longer be cancelled.', null, 409);
        }
        if ($order->cancellationRequests()->where('status', 'pending')->exists()) {
            return $this->fail('A cancellation request is already pending for this order.', null, 409);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $req = $order->cancellationRequests()->create([
            'customer_id' => $request->user()->id,
            'reason' => $data['reason'],
        ]);
        AuditLogger::record('order.cancellation_requested', $order, [], ['reason' => $data['reason']]);

        return $this->created($req, 'Cancellation request submitted');
    }

    public function requestReturn(Request $request, Order $order)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        if (! in_array($order->order_status, Order::RETURNABLE, true)) {
            return $this->fail('Only delivered orders can be returned or refunded.', null, 409);
        }

        $data = $request->validate([
            'type' => ['required', 'in:return,refund'],
            'reason' => ['required', 'string', 'max:1000'],
            'order_item_id' => ['nullable', 'integer'],
        ]);

        $amount = $order->total;
        if (! empty($data['order_item_id'])) {
            $item = $order->items()->whereKey($data['order_item_id'])->first();
            abort_unless($item, 422, 'Order item not found for this order.');
            $amount = $item->total;
        }

        $req = $order->returnRequests()->create([
            'customer_id' => $request->user()->id,
            'order_item_id' => $data['order_item_id'] ?? null,
            'type' => $data['type'],
            'reason' => $data['reason'],
            'amount_requested' => $amount,
        ]);
        AuditLogger::record('order.return_requested', $order, [], $data);

        return $this->created($req, ucfirst($data['type']) . ' request submitted');
    }

    public function requests(Request $request)
    {
        $userId = $request->user()->id;

        return $this->ok([
            'cancellations' => \App\Models\CancellationRequest::where('customer_id', $userId)
                ->with('order:id,order_number')->latest()->get(),
            'returns' => \App\Models\ReturnRequest::where('customer_id', $userId)
                ->with('order:id,order_number')->latest()->get(),
        ]);
    }
}
