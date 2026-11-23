<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $order->order_number }}</title>
    <style>
        body { color: #172033; font: 12px DejaVu Sans, sans-serif; line-height: 1.5; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        h2 { font-size: 16px; margin: 18px 0 4px; }
        .muted { color: #64748b; }
        .company-header { border-bottom: 2px solid #172033; padding-bottom: 12px; }
        .company-logo { height: 48px; margin-bottom: 8px; max-width: 180px; }
        .company-tagline { margin: 0 0 6px; }
        .company-details { color: #475569; font-size: 10px; white-space: pre-line; }
        table { border-collapse: collapse; margin-top: 20px; width: 100%; }
        th, td { border-bottom: 1px solid #dbe2ea; padding: 8px; text-align: left; }
        th:last-child, td:last-child { text-align: right; }
        .total { font-size: 15px; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <header class="company-header">
        @if ($company['logo'])
            <img class="company-logo" src="{{ $company['logo'] }}" alt="{{ $company['name'] }}">
        @endif
        <h1>{{ $company['name'] }}</h1>
        @if ($company['tagline'])
            <p class="company-tagline muted">{{ $company['tagline'] }}</p>
        @endif
        <div class="company-details">
            @if ($company['address']){{ $company['address'] }}@endif
            @if ($company['phone']){{ $company['address'] ? ' · ' : '' }}{{ $company['phone'] }}@endif
            @if ($company['email']){{ ($company['address'] || $company['phone']) ? ' · ' : '' }}{{ $company['email'] }}@endif
        </div>
    </header>
    <h2>Order receipt</h2>
    <p class="muted">Order {{ $order->order_number }} · {{ optional($order->placed_at)->format('d M Y, H:i') }}</p>
    <p>Customer: {{ $order->customer?->full_name ?? $order->customer?->email }}<br>
        Payment method: {{ str_replace('_', ' ', $order->payment_method) }}<br>
        Payment status: {{ str_replace('_', ' ', $order->payment_status) }}
        @if ($payment?->reference)
            <br>Payment reference: {{ $payment->reference }}
        @endif
    </p>
    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>Amount</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}@if ($item->selected_options) <br><span class="muted">{{ collect($item->selected_options)->map(fn ($value, $key) => ucfirst($key).': '.$value)->implode(' · ') }}</span>@endif</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $order->currency ?? 'NGN' }} {{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table>
        <tbody>
            <tr><td>Subtotal</td><td>{{ $order->currency ?? 'NGN' }} {{ number_format((float) $order->subtotal, 2) }}</td></tr>
            @if ((float) $order->discount > 0)
                <tr><td>Discount</td><td>-{{ $order->currency ?? 'NGN' }} {{ number_format((float) $order->discount, 2) }}</td></tr>
            @endif
            @if ((float) $order->delivery_fee > 0)
                <tr><td>Delivery</td><td>{{ $order->currency ?? 'NGN' }} {{ number_format((float) $order->delivery_fee, 2) }}</td></tr>
            @endif
            @if ((float) $order->tax > 0)
                <tr><td>Tax</td><td>{{ $order->currency ?? 'NGN' }} {{ number_format((float) $order->tax, 2) }}</td></tr>
            @endif
        </tbody>
    </table>
    <p class="total">Total: {{ $order->currency ?? 'NGN' }} {{ number_format((float) $order->total, 2) }}</p>
    @if ($order->payment_method === 'bank_transfer' && $order->payment_status !== 'successful')
        <p class="muted">This is an order receipt, not proof of received funds. Bank transfer remains pending until administrator confirmation.</p>
    @elseif ($order->payment_method === 'pay_on_delivery')
        <p class="muted">Payment is due to the delivery agent upon delivery.</p>
    @endif
</body>
</html>
