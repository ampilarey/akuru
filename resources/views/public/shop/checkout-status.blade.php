@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 (slice B2): where a customer lands after placing an order,
     and where the bank sends them back. Display only — a card payment is
     confirmed by the webhook, a bank transfer by the office (rule 12). --}}
@section('title', __('shop.checkout_status_title', ['number' => $checkout['number']]) . ' - ' . __('shop.bookshop_title'))

@section('content')
<div class="container mx-auto max-w-3xl px-4 py-8">
    <nav class="mb-4 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
        <span>›</span>
        <a href="{{ route('public.shop.orders') }}" class="hover:text-brandMaroon-600">{{ __('shop.my_orders') }}</a>
    </nav>
    <h1 class="text-3xl font-bold text-brandMaroon-900" data-testid="checkout-number">{{ __('shop.checkout_status_title', ['number' => $checkout['number']]) }}</h1>
    <p class="mb-6 text-lg" data-testid="checkout-status" data-status="{{ $checkout['status'] }}">{{ __('shop.status_'.$checkout['status']) }} · {{ $checkout['currency'] }} {{ $checkout['total'] }}</p>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif

    @if($checkout['status'] === 'paid')
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900" data-testid="paid-thanks">{{ __('shop.paid_thanks') }}</div>
    @elseif($checkout['status'] === 'expired')
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900" data-testid="expired-note">{{ __('shop.expired_note') }}</div>
    @elseif($checkout['status'] === 'failed')
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">{{ __('shop.failed_note') }} <a href="{{ route('public.shop.cart') }}" class="underline">{{ __('shop.view_cart') }}</a></div>
    @elseif($checkout['payment_method'] === 'card')
        <div class="mb-6 rounded-lg border bg-white p-4" data-testid="card-pending">
            <p class="mb-3 text-gray-700">{{ __('shop.card_pending') }}</p>
            <a href="{{ route('public.shop.checkout.status', $checkout['number']) }}" class="btn-secondary">{{ __('shop.refresh') }}</a>
        </div>
    @elseif($checkout['payment_method'] === 'bank_transfer')
        <div class="mb-6 rounded-lg border bg-white p-4" data-testid="bank-transfer">
            <h2 class="mb-2 text-lg font-semibold">{{ __('shop.bank_details_heading') }}</h2>
            <dl class="mb-3 grid grid-cols-3 gap-y-1 text-sm">
                <dt class="text-gray-500">{{ __('shop.bank_name') }}</dt><dd class="col-span-2">{{ $checkout['bank']['bank'] }}</dd>
                <dt class="text-gray-500">{{ __('shop.account_name') }}</dt><dd class="col-span-2">{{ $checkout['bank']['account_name'] }}</dd>
                <dt class="text-gray-500">{{ __('shop.account_number') }}</dt><dd class="col-span-2 font-mono" data-testid="bank-account">{{ $checkout['bank']['account_number'] }}</dd>
                <dt class="text-gray-500">{{ __('shop.transfer_amount') }}</dt><dd class="col-span-2 font-semibold">{{ $checkout['currency'] }} {{ $checkout['total'] }}</dd>
            </dl>
            <p class="mb-4 text-sm text-gray-700">{{ __('shop.transfer_reference', ['number' => $checkout['number']]) }}</p>

            @if(count($checkout['slips']) > 0)
                <ul class="mb-4 divide-y rounded border text-sm" data-testid="slips">
                    @foreach($checkout['slips'] as $slip)
                        <li class="flex flex-wrap justify-between gap-2 p-2" data-slip-status="{{ $slip['status'] }}">
                            <span><a href="{{ route('public.shop.slip', $slip['id']) }}" class="underline" target="_blank" rel="noopener">{{ __('shop.view_slip') }}</a> · {{ $slip['uploaded_at'] }}@if($slip['reference']) · {{ $slip['reference'] }}@endif</span>
                            <span>{{ __('shop.slip_status_'.$slip['status']) }}@if($slip['decision_note']) — {{ $slip['decision_note'] }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($checkout['awaiting_slip'])
                <form method="POST" action="{{ route('public.shop.checkout.slip', $checkout['number']) }}" enctype="multipart/form-data" class="grid gap-2 md:grid-cols-2" data-testid="slip-form">
                    @csrf
                    <h3 class="text-sm font-semibold md:col-span-2">{{ count($checkout['slips']) > 0 ? __('shop.upload_another') : __('shop.upload_slip') }}</h3>
                    <label class="text-sm md:col-span-2">{{ __('shop.slip_file') }}<input type="file" name="slip" accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-sm" required data-testid="slip-file"></label>
                    <label class="text-sm">{{ __('shop.slip_reference') }}<input name="reference" class="form-input w-full" data-testid="slip-reference"></label>
                    <label class="text-sm">{{ __('shop.slip_note') }}<input name="note" class="form-input w-full"></label>
                    <div class="md:col-span-2"><button type="submit" class="btn-primary" data-testid="send-slip">{{ __('shop.send_slip') }}</button></div>
                </form>
            @endif
        </div>
    @endif

    <h2 class="mb-2 text-lg font-semibold">{{ __('shop.orders_in_checkout') }}</h2>
    <ul class="divide-y rounded-lg border bg-white" data-testid="checkout-orders">
        @foreach($checkout['orders'] as $order)
            <li class="flex flex-wrap items-center justify-between gap-2 p-3 text-sm" data-order="{{ $order['number'] }}">
                <span>
                    <span class="font-mono font-semibold">{{ $order['number'] }}</span> · {{ $order['vendor']['name'] }} · {{ __('shop.items_count', ['count' => $order['item_count']]) }}
                    <span class="block text-xs text-gray-500">{{ $order['delivery']['name'] }} · {{ __('shop.status_'.$order['status']) }}</span>
                </span>
                <span class="flex items-center gap-3">
                    <span class="font-semibold">{{ $order['currency'] }} {{ $order['total'] }}</span>
                    <a href="{{ route('public.shop.orders.show', $order['number']) }}" class="underline">{{ __('shop.view_order') }}</a>
                </span>
            </li>
        @endforeach
    </ul>

    <div class="mt-4 rounded-lg border bg-brandBeige-50 p-3 text-sm">
        <span class="font-semibold">{{ __('shop.deliver_to') }}:</span>
        {{ $checkout['address']['recipient_name'] ?? '' }} · {{ $checkout['address']['phone'] ?? '' }} · {{ $checkout['address']['street'] ?? '' }}, {{ $checkout['address']['island'] ?? '' }}, {{ $checkout['address']['atoll'] ?? '' }}
    </div>
</div>
@endsection
