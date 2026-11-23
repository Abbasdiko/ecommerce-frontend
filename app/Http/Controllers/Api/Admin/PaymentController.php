<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private PaymentService $payments) {}

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'payment.view');

        $payments = Payment::query()
            ->with('user:id,first_name,last_name,email')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->gateway, fn ($q, $g) => $q->where('gateway', $g))
            ->when($request->boolean('bank_transfers'), fn ($q) => $q->where('gateway', 'bank_transfer'))
            ->latest()
            ->paginate($this->perPage($request));

        return PaymentResource::collection($payments);
    }

    public function confirm(Request $request, Payment $payment)
    {
        $this->authorizePermission($request, 'payment.verify');
        abort_unless($payment->gateway === 'bank_transfer', 422, 'Only bank transfers are confirmed manually.');

        if ($payment->status === 'successful') {
            return $this->fail('Payment is already confirmed.', null, 409);
        }

        $payment = $this->payments->confirmBankTransfer($payment, $request->user());
        AuditLogger::record('payment.bank_transfer_confirmed', $payment);

        return $this->ok(new PaymentResource($payment), 'Bank transfer confirmed');
    }

    public function reject(Request $request, Payment $payment)
    {
        $this->authorizePermission($request, 'payment.verify');
        abort_unless($payment->gateway === 'bank_transfer', 422, 'Only bank transfers can be rejected manually.');
        abort_if($payment->status === 'successful', 409, 'A confirmed payment cannot be rejected.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $payment->update([
            'status' => 'failed',
            'meta' => array_merge($payment->meta ?? [], ['rejection_reason' => $data['reason']]),
        ]);
        if ($payment->purpose === 'order') {
            \App\Models\Order::whereKey($payment->payable_id)
                ->update(['payment_status' => 'failed']);
        }
        AuditLogger::record('payment.bank_transfer_rejected', $payment, [], $data);

        return $this->ok(new PaymentResource($payment), 'Payment rejected');
    }
}
