@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 (slice B2): one order and its receipt — a tax line and
     the vendor's TIN only when the vendor is GST-registered (decision 4). --}}
@section('title', __('shop.order_title', ['number' => $order['number']]) . ' - ' . __('shop.bookshop_title'))

@section('content')
<style media="print">header, footer, nav, .no-print { display: none !important; }</style>
<div class="container mx-auto max-w-3xl px-4 py-8">
    <nav class="no-print mb-4 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
        <span>›</span>
        <a href="{{ route('public.shop.orders') }}" class="hover:text-brandMaroon-600">{{ __('shop.my_orders') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ $order['number'] }}</span>
    </nav>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-brandMaroon-900" data-testid="order-number">{{ __('shop.order_title', ['number' => $order['number']]) }}</h1>
            <p class="text-gray-700" data-testid="order-status" data-status="{{ $order['status'] }}">{{ __('shop.status_'.$order['status']) }} · {{ __('shop.order_placed') }} {{ $order['placed_at'] }}</p>
        </div>
        <button type="button" class="btn-secondary no-print" onclick="window.print()">{{ __('shop.print') }}</button>
    </div>

    <section class="mb-4 rounded-lg border bg-white p-4" data-testid="receipt">
        <h2 class="mb-1 text-lg font-semibold">{{ __('shop.receipt') }}</h2>
        <p class="mb-3 text-sm text-gray-600">
            {{ __('shop.sold_by') }} <span class="font-medium">{{ $order['receipt']['vendor_legal_name'] }}</span> · {{ __('shop.at_akuru') }}
            @if($order['receipt']['tax_shown'] && $order['receipt']['vendor_tin'])<span class="block" data-testid="vendor-tin">{{ __('shop.vendor_tin_label') }}: {{ $order['receipt']['vendor_tin'] }}</span>@endif
        </p>
        <table class="mb-3 w-full text-sm">
            <thead class="border-b text-start text-gray-500">
                <tr><th class="py-1 text-start">{{ __('shop.product_title') }}</th><th class="py-1 text-end">{{ __('shop.quantity') }}</th><th class="py-1 text-end">{{ __('shop.price') }}</th><th class="py-1 text-end">{{ __('shop.line_total') }}</th></tr>
            </thead>
            <tbody>
                @foreach($order['items'] as $item)
                    <tr class="border-b" data-testid="order-item">
                        <td class="py-1" dir="auto">{{ $item['title'] }}@if($item['variant']) <span class="text-gray-500">({{ $item['variant'] }})</span>@endif @if($item['sku'])<span class="block text-xs text-gray-400">{{ $item['sku'] }}</span>@endif</td>
                        <td class="py-1 text-end">{{ $item['quantity'] }}</td>
                        <td class="py-1 text-end">{{ $item['unit_price'] }}</td>
                        <td class="py-1 text-end">{{ $item['line_total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <dl class="ms-auto grid max-w-xs grid-cols-2 gap-y-1 text-sm">
            <dt class="text-gray-500">{{ __('shop.goods') }}</dt><dd class="text-end">{{ $order['currency'] }} {{ $order['receipt']['goods'] }}</dd>
            @if((float) $order['receipt']['discount'] > 0)
                <dt class="text-gray-500">{{ __('shop.discount') }}</dt><dd class="text-end" data-testid="receipt-discount">− {{ $order['currency'] }} {{ $order['receipt']['discount'] }}</dd>
            @endif
            <dt class="text-gray-500">{{ __('shop.delivery_fee') }} ({{ $order['delivery']['name'] }})</dt><dd class="text-end">{{ $order['delivery']['carrier_paid'] ? __('shop.carrier_paid_note') : $order['currency'].' '.$order['receipt']['delivery'] }}</dd>
            <dt class="font-semibold">{{ __('shop.total') }}</dt><dd class="text-end font-semibold" data-testid="receipt-total">{{ $order['currency'] }} {{ $order['receipt']['total'] }}</dd>
            @if($order['receipt']['tax_shown'])
                <dt class="text-xs text-gray-500">{{ __('shop.tax_included', ['amount' => $order['currency'].' '.$order['receipt']['tax']]) }}</dt><dd data-testid="receipt-tax"></dd>
            @endif
        </dl>
        <p class="mt-2 text-xs text-gray-500">{{ __('shop.prices_include_tax') }} {{ __('shop.payment_method') }}: {{ $order['payment_method'] ? __('shop.pay_'.$order['payment_method']) : __('shop.none') }}</p>
    </section>

    <section class="mb-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-lg border bg-white p-4 text-sm" data-testid="order-address">
            <h2 class="mb-1 font-semibold">{{ __('shop.delivery_to') }}</h2>
            <p>{{ $order['delivery']['name'] }} · {{ __('shop.handling_note', ['days' => $order['delivery']['handling_days']]) }}</p>
            <p class="mt-1 text-gray-700">{{ $order['address']['recipient_name'] ?? '' }} · {{ $order['address']['phone'] ?? '' }}<br>{{ $order['address']['street'] ?? '' }}, {{ $order['address']['island'] ?? '' }}, {{ $order['address']['atoll'] ?? '' }}@if(! empty($order['address']['notes']))<br>{{ $order['address']['notes'] }}@endif</p>
            @if($order['notes'])<p class="mt-1 text-gray-600">{{ __('shop.order_notes') }}: {{ $order['notes'] }}</p>@endif
        </div>
        <div class="rounded-lg border bg-white p-4 text-sm" data-testid="order-history">
            <h2 class="mb-1 font-semibold">{{ __('shop.history') }}</h2>
            <ul>
                @foreach($order['events'] as $event)
                    <li class="flex justify-between gap-2 py-0.5"><span>{{ __('shop.event_'.$event['type']) }}@if($event['note']) <span class="text-gray-500">— {{ $event['note'] }}</span>@endif</span><span class="text-gray-500">{{ $event['at'] }}</span></li>
                @endforeach
            </ul>
            @if($order['status'] === 'pending_payment' && $order['checkout_number'])
                <a href="{{ route('public.shop.checkout.status', $order['checkout_number']) }}" class="btn-primary mt-3 inline-block no-print" data-testid="pay-now">{{ __('shop.pay_now') }}</a>
            @endif
        </div>
    </section>
</div>
@endsection
