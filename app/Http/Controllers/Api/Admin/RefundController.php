<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\AuditLogger;
use App\Services\OrderWorkflow;
use App\Services\RefundService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    use ApiResponse;

    public function __construct(
        private RefundService $refunds,
        private OrderWorkflow $orders,
    ) {
    }

    public function returns(Request $request)
    {
        $this->authorizePermission($request, 'refund.view');

        $items = ReturnRequest::query()
            ->with(['order:id,order_number,total', 'customer:id,first_name,last_name', 'orderItem:id,product_name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($items);
    }

    public function approveReturn(Request $request, ReturnRequest $returnRequest)
    {
        $this->authorizePermission($request, 'refund.approve');

        if ($returnRequest->status === 'completed') {
            return $this->fail('This request is already completed.', null, 409);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $refund = $this->refunds->approve($returnRequest, (float) $data['amount'], $data['note'] ?? null);
        AuditLogger::record('refund.processed', $refund);

        return $this->ok($refund->load('order:id,order_number'), 'Refund processed and credited to the customer wallet');
    }

    public function rejectReturn(Request $request, ReturnRequest $returnRequest)
    {
        $this->authorizePermission($request, 'refund.reject');

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->refunds->reject($returnRequest, $data['reason']);
        AuditLogger::record('refund.rejected', $returnRequest, [], $data);

        return $this->ok($returnRequest->fresh(), 'Return request rejected');
    }

    public function cancellations(Request $request)
    {
        $this->authorizePermission($request, 'cancellation.review');

        $items = CancellationRequest::query()
            ->with(['order:id,order_number,order_status,total', 'customer:id,first_name,last_name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($items);
    }

    public function resolveCancellation(Request $request, CancellationRequest $cancellationRequest)
    {
        $this->authorizePermission($request, 'cancellation.review');

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $cancellationRequest->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
            'admin_note' => $data['note'] ?? null,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        if ($data['decision'] === 'approve') {
            $order = $cancellationRequest->order;
            if ($order && in_array($order->order_status, Order::CUSTOMER_CANCELLABLE, true)) {
                $order->update(['order_status' => 'cancelled']);
                $order->agentOrders()->update(['status' => 'cancelled']);
                \App\Models\Commission::where('order_id', $order->id)->update(['status' => 'reversed']);
                $this->orders->recordHistory($order, 'cancelled', 'Cancellation approved by staff');
            }
        }
        AuditLogger::record('cancellation.' . $data['decision'], $cancellationRequest);

        return $this->ok($cancellationRequest->fresh('order'), 'Cancellation ' . $data['decision'] . 'd');
    }

    public function history(Request $request)
    {
        $this->authorizePermission($request, 'refund.view');

        return $this->ok(
            \App\Models\Refund::with(['order:id,order_number', 'customer:id,first_name,last_name', 'processedBy:id,first_name,last_name'])
                ->latest()->paginate($this->perPage($request))
        );
    }
}
