<h1>{{ $event === 'paid' ? 'Payment confirmed' : 'Order received' }}</h1>
<p>Hello,</p>
<p>
    Order <strong>{{ $order->order_number }}</strong> has total
    <strong>{{ $order->currency ?? 'NGN' }} {{ number_format((float) $order->total, 2) }}</strong>.
</p>
<p>Order status: {{ str_replace('_', ' ', $order->order_status) }}.</p>
<p>Payment status: {{ str_replace('_', ' ', $order->payment_status) }}.</p>
@if ($event === 'placed' && $order->payment_method === 'bank_transfer')
    <p>Bank transfer is awaiting administrator confirmation. Transfer
        {{ $order->currency ?? 'NGN' }} {{ number_format((float) $order->total, 2) }}
        using reference <strong>{{ $payment?->reference }}</strong>.
    </p>
    @foreach (($payment?->meta['instructions']['accounts'] ?? []) as $account)
        <p>{{ $account['bank_name'] }} · {{ $account['account_name'] }} · {{ $account['account_number'] }}</p>
    @endforeach
    <p>Upload your transfer proof from your order page. Your order is not marked paid until an administrator confirms the transfer.</p>
@elseif ($event === 'placed' && $order->payment_method === 'pay_on_delivery')
    <p>Please pay the delivery agent when your order arrives.</p>
@elseif ($event === 'paid')
    <p>Your payment has been confirmed. You can download the order receipt from your account.</p>
@endif
<p>Thank you,<br>{{ config('app.name') }}</p>
