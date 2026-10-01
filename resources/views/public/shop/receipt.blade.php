{{-- COMMERCE_PARITY_PLAN P8: the receipt a paid checkout's SMS and email link to, opened without
     signing in. It carries what a paper receipt carries — who sold each order, its lines and totals,
     the GST line and TIN where the shop is registered — and never the address, the phone or the messages. --}}
@extends('public.layouts.public')

@section('title', __('shop.receipt_link_title', ['number' => $receipt['number']]) . ' - ' . __('shop.bookshop_title'))

@section('content')
<style media="print">header, footer, nav, .no-print { display: none !important; }</style>
<div class="container mx-auto max-w-3xl px-4 py-8" data-testid="checkout-receipt">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold text-brandMaroon-900">{{ __('shop.receipt_link_title', ['number' => $receipt['number']]) }}</h1>
            <p class="text-sm text-gray-600">{{ __('shop.receipt_link_paid', ['date' => $receipt['paid_at']]) }} · {{ __('shop.pay_'.$receipt['payment_method']) }}</p>
        </div>
        <button type="button" class="btn-secondary no-print" onclick="window.print()">{{ __('shop.print') }}</button>
    </div>

    @foreach($receipt['orders'] as $order)
        <section class="mb-4 rounded-lg border bg-white p-4" data-testid="receipt-order" data-order="{{ $order['number'] }}">
            <p class="mb-2 text-sm text-gray-600">
                <span class="font-mono font-semibold text-gray-900">{{ $order['number'] }}</span> · {{ __('shop.sold_by') }} <span class="font-medium">{{ $order['shop'] }}</span> · {{ __('shop.at_akuru') }}
                @if($order['tax_shown'] && $order['vendor_tin'])<span class="block" data-testid="receipt-tin">{{ __('shop.vendor_tin_label') }}: {{ $order['vendor_tin'] }}</span>@endif
                @if($order['status'] === 'cancelled')<span class="block font-medium text-red-700">{{ __('shop.status_cancelled') }}</span>@endif
            </p>
            <table class="mb-3 w-full text-sm">
                <thead class="border-b text-gray-500">
                    <tr><th class="py-1 text-start">{{ __('shop.product_title') }}</th><th class="py-1 text-end">{{ __('shop.quantity') }}</th><th class="py-1 text-end">{{ __('shop.price') }}</th><th class="py-1 text-end">{{ __('shop.line_total') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($order['items'] as $item)
                        <tr class="border-b"><td class="py-1" dir="auto">{{ $item['title'] }}@if($item['variant']) <span class="text-gray-500">({{ $item['variant'] }})</span>@endif</td><td class="py-1 text-end">{{ $item['quantity'] }}</td><td class="py-1 text-end">{{ $item['unit_price'] }}</td><td class="py-1 text-end">{{ $item['line_total'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <dl class="ms-auto grid max-w-xs grid-cols-2 gap-y-1 text-sm">
                <dt class="text-gray-500">{{ __('shop.goods') }}</dt><dd class="text-end">{{ $receipt['currency'] }} {{ $order['goods'] }}</dd>
                @if((float) $order['discount'] > 0)<dt class="text-gray-500">{{ __('shop.discount') }}</dt><dd class="text-end">− {{ $receipt['currency'] }} {{ $order['discount'] }}</dd>@endif
                <dt class="text-gray-500">{{ __('shop.delivery_fee') }} ({{ $order['delivery_name'] }})</dt><dd class="text-end">{{ $order['carrier_paid'] ? __('shop.carrier_paid_note') : $receipt['currency'].' '.$order['delivery'] }}</dd>
                <dt class="font-semibold">{{ __('shop.total') }}</dt><dd class="text-end font-semibold">{{ $receipt['currency'] }} {{ $order['total'] }}</dd>
                @if($order['tax_shown'])<dt class="col-span-2 text-xs text-gray-500" data-testid="receipt-gst">{{ __('shop.tax_included', ['amount' => $receipt['currency'].' '.$order['tax']]) }}</dt>@endif
            </dl>
            <p class="no-print mt-2 text-sm"><a href="{{ route('public.shop.track', ['number' => $order['number']]) }}" class="text-brandMaroon-700 underline">{{ __('shop.receipt_link_track') }}</a></p>
        </section>
    @endforeach

    <p class="rounded-lg border bg-gray-50 p-4 text-end text-lg font-semibold" data-testid="receipt-paid-total">{{ __('shop.receipt_link_total', ['amount' => $receipt['currency'].' '.$receipt['total']]) }}</p>
    <p class="no-print mt-4 text-sm text-gray-600">{{ __('shop.receipt_link_private') }} <a href="{{ route('public.shop.orders') }}" class="text-brandMaroon-700 underline">{{ __('shop.track_sign_in_for_more') }}</a></p>
</div>
@endsection
