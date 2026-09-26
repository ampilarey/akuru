@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 (slice B2): one order and its receipt — a tax line and
     the vendor's TIN only when the vendor is GST-registered (decision 4).
     B3: its progress and tracking, cancelling before dispatch, returns inside
     the window, refunds, and a message to the shop. --}}
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

    @if($errors->any())
        <div class="no-print mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" data-testid="order-errors">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif

    {{-- B3: where it is. --}}
    @if($order['status'] === 'cancelled')
        <div class="no-print mb-4 rounded-lg border border-gray-300 bg-gray-50 p-4" data-testid="order-cancelled">
            <p class="font-semibold">{{ $order['cancelled_by_customer'] ? __('shop.you_cancelled') : __('shop.shop_cancelled') }}</p>
            <p class="text-sm text-gray-700">{{ $order['cancel_reason'] }}</p>
        </div>
    @elseif(! in_array($order['status'], ['pending_payment', 'expired'], true))
        @php($stepKeys = ['paid', 'processing', $order['collection'] ? 'ready' : 'dispatched', 'delivered'])
        <ol class="no-print mb-4 grid grid-cols-4 gap-1 text-center text-xs" data-testid="order-progress">
            @foreach($stepKeys as $step)
                @php($at = $order['steps'][$step] ?? null)
                <li class="rounded px-1 py-2 {{ $at ? 'bg-green-100 text-green-900' : 'bg-gray-100 text-gray-500' }}" data-step="{{ $step }}" data-done="{{ $at ? '1' : '0' }}">
                    <span class="block font-semibold">{{ $step === 'delivered' && $order['collection'] ? __('shop.step_collected') : __('shop.progress_'.$step) }}</span>
                    @if($at)<span class="block">{{ \Illuminate\Support\Str::of($at)->before(' ') }}</span>@endif
                </li>
            @endforeach
        </ol>
        @if($order['carrier'] || $order['tracking_note'])
            <p class="mb-4 rounded-lg bg-brandBeige-50 p-3 text-sm" data-testid="tracking"><span class="font-semibold">{{ __('shop.tracking_note') }}:</span> {{ $order['carrier'] }} {{ $order['tracking_note'] }}</p>
        @endif
    @endif

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

    {{-- B3: money going back. --}}
    @if(count($order['refunds']) > 0)
        <section class="mb-4 rounded-lg border bg-white p-4 text-sm" data-testid="order-refunds">
            <h2 class="mb-1 font-semibold">{{ __('shop.refunds_heading') }}</h2>
            <ul>
                @foreach($order['refunds'] as $refund)
                    <li class="py-0.5" data-refund-status="{{ $refund['status'] }}">
                        {{ $order['currency'] }} {{ $refund['amount'] }} —
                        @if($refund['status'] === 'done')
                            {{ __($refund['destination'] === 'card' ? 'shop.refund_done_card' : 'shop.refund_done_wallet') }}
                        @else
                            {{ __('shop.refund_on_its_way') }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- B3: returns asked for, and asking for one. --}}
    @if(count($order['returns']) > 0)
        <section class="mb-4 rounded-lg border bg-white p-4 text-sm" data-testid="order-returns">
            <h2 class="mb-1 font-semibold">{{ __('shop.returns_heading') }}</h2>
            <ul>
                @foreach($order['returns'] as $return)
                    <li class="py-0.5" data-return-status="{{ $return['status'] }}">
                        {{ $return['quantity'] }} × {{ $return['title'] }} · {{ __('shop.reason_'.$return['reason']) }} ·
                        <span class="font-medium">{{ __('shop.return_status_'.$return['status']) }}</span>@if($return['decision_note']) — {{ $return['decision_note'] }}@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($order['returns_open'] && collect($order['items'])->sum('returnable') > 0)
        <section class="no-print mb-4 rounded-lg border bg-white p-4 text-sm" data-testid="return-form">
            <h2 class="mb-1 font-semibold">{{ __('shop.ask_return') }}</h2>
            <p class="mb-2 text-gray-600">{{ __('shop.returns_until', ['date' => $order['returns_until']]) }}</p>
            @if($order['return_conditions'])<p class="mb-2 text-gray-600" dir="auto">{{ $order['return_conditions'] }}</p>@endif
            <form method="POST" action="{{ route('public.shop.orders.return', $order['number']) }}" class="grid gap-2 md:grid-cols-4">
                @csrf
                <label class="md:col-span-2">{{ __('shop.product_title') }}
                    <select name="item_id" class="form-input block w-full" data-testid="return-item">
                        @foreach($order['items'] as $item)
                            @if($item['returnable'] > 0)<option value="{{ $item['id'] }}">{{ $item['title'] }}@if($item['variant']) ({{ $item['variant'] }})@endif — {{ __('shop.up_to', ['count' => $item['returnable']]) }}</option>@endif
                        @endforeach
                    </select>
                </label>
                <label>{{ __('shop.quantity') }}<input type="number" name="quantity" min="1" value="1" class="form-input block w-full" data-testid="return-quantity"></label>
                <label>{{ __('shop.return_reason') }}
                    <select name="reason" class="form-input block w-full" data-testid="return-reason">
                        @foreach($reasons as $reason)<option value="{{ $reason }}">{{ __('shop.reason_'.$reason) }}</option>@endforeach
                    </select>
                </label>
                <label class="md:col-span-3">{{ __('shop.slip_note') }}<input name="note" class="form-input block w-full" data-testid="return-note"></label>
                <div class="self-end"><button type="submit" class="btn-primary w-full" data-testid="request-return">{{ __('shop.ask_return') }}</button></div>
            </form>
        </section>
    @endif

    {{-- B3: cancel before it leaves the shop. --}}
    @if($order['can_cancel'])
        <details class="no-print mb-4 rounded-lg border bg-white p-4 text-sm" data-testid="cancel-order">
            <summary class="cursor-pointer font-semibold text-red-700">{{ __('shop.cancel_order') }}</summary>
            <p class="my-2 text-gray-600">{{ __('shop.cancel_intro') }}</p>
            <form method="POST" action="{{ route('public.shop.orders.cancel', $order['number']) }}" class="flex flex-wrap gap-2">
                @csrf
                <input name="reason" class="form-input flex-1" placeholder="{{ __('shop.cancel_reason') }}" required data-testid="cancel-reason">
                <button type="submit" class="rounded bg-red-600 px-3 py-2 text-white" data-testid="confirm-cancel">{{ __('shop.cancel_order_refund') }}</button>
            </form>
        </details>
    @endif

    {{-- B3: write to the shop. --}}
    @if(! in_array($order['status'], ['pending_payment', 'expired'], true))
        <section class="no-print mb-4 rounded-lg border bg-white p-4 text-sm" data-testid="message-shop">
            <h2 class="mb-1 font-semibold">{{ __('shop.message_shop', ['vendor' => $order['vendor']['name']]) }}</h2>
            <form method="POST" action="{{ route('public.shop.orders.message', $order['number']) }}" class="flex flex-wrap items-start gap-2">
                @csrf
                <textarea name="body" rows="2" class="form-input flex-1" required data-testid="message-body"></textarea>
                <button type="submit" class="btn-secondary" data-testid="send-message">{{ __('shop.send') }}</button>
            </form>
            @if($order['message_thread_id'])
                <a href="{{ url('portal/messages/'.$order['message_thread_id']) }}" class="mt-2 inline-block text-brandMaroon-700 underline" data-testid="open-thread">{{ __('shop.open_conversation') }}</a>
            @endif
        </section>
    @endif
</div>
@endsection
