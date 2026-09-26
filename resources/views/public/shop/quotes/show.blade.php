@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN B9 "bulk quotes for schools" (slice B9d): one quote — the
     lines at list and quoted price, how long the price holds, and accept
     (into the cart at that price) or withdraw. --}}
@section('title', __('shop.quote_title', ['number' => $quote['number']]) . ' - ' . __('shop.bookshop_title'))

@section('content')
<div class="container mx-auto max-w-3xl px-4 py-8">
    <nav class="mb-4 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
        <span>›</span>
        <a href="{{ route('public.shop.quotes') }}" class="hover:text-brandMaroon-600">{{ __('shop.my_quotes_title') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ $quote['number'] }}</span>
    </nav>
    <h1 class="text-3xl font-bold text-brandMaroon-900" data-testid="quote-number">{{ __('shop.quote_title', ['number' => $quote['number']]) }}</h1>
    <p class="mb-4 text-gray-700" data-testid="quote-status" data-status="{{ $quote['status'] }}">
        {{ __('shop.quote_status_'.$quote['status']) }} · {{ $quote['vendor']['name'] }} · <span dir="auto">{{ $quote['organisation'] }}</span>
    </p>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" data-testid="quote-errors">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif

    @if($quote['status'] === 'requested')
        <p class="mb-4 rounded-lg bg-brandBeige-50 p-3 text-sm" data-testid="quote-waiting">{{ __('shop.quote_waiting', ['vendor' => $quote['vendor']['name']]) }}</p>
    @elseif($quote['status'] === 'declined')
        <div class="mb-4 rounded-lg border border-gray-300 bg-gray-50 p-3 text-sm" data-testid="quote-declined">
            <p class="font-semibold">{{ __('shop.quote_declined_by', ['vendor' => $quote['vendor']['name']]) }}</p>
            <p dir="auto">{{ $quote['vendor_note'] }}</p>
        </div>
    @elseif(in_array($quote['status'], ['quoted', 'accepted'], true))
        <div class="mb-4 rounded-lg border {{ $quote['holds'] ? 'border-green-300 bg-green-50' : 'border-amber-300 bg-amber-50' }} p-3 text-sm" data-testid="quote-offer">
            <p class="font-semibold">{{ $quote['holds'] ? __('shop.quote_valid_until', ['date' => $quote['valid_until']]) : __('shop.quote_expired', ['date' => $quote['valid_until']]) }}</p>
            @if($quote['vendor_note'])<p dir="auto">{{ $quote['vendor_note'] }}</p>@endif
        </div>
    @endif

    <section class="mb-4 overflow-x-auto rounded-lg border bg-white">
        <table class="w-full text-sm" data-testid="quote-lines">
            <thead class="bg-gray-50 text-start text-xs uppercase text-gray-500">
                <tr><th class="p-3 text-start">{{ __('shop.quote_item') }}</th><th class="p-3 text-end">{{ __('shop.quantity') }}</th><th class="p-3 text-end">{{ __('shop.quote_list_price') }}</th><th class="p-3 text-end">{{ __('shop.quote_quoted_price') }}</th></tr>
            </thead>
            <tbody class="divide-y">
                @foreach($quote['items'] as $item)
                    <tr>
                        <td class="p-3" dir="auto">{{ $item['title'] }}@if($item['variant'])<span class="block text-xs text-gray-500">{{ $item['variant'] }}</span>@endif</td>
                        <td class="p-3 text-end">{{ $item['quantity'] }}</td>
                        <td class="p-3 text-end">{{ $quote['currency'] }} {{ $item['list_price'] }}</td>
                        <td class="p-3 text-end font-semibold">{{ $item['quoted_price'] ? $quote['currency'].' '.$item['quoted_price'] : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t bg-gray-50">
                <tr><td class="p-3 font-semibold" colspan="2">{{ __('shop.total') }}</td><td class="p-3 text-end">{{ $quote['currency'] }} {{ $quote['list_total'] }}</td><td class="p-3 text-end font-semibold" data-testid="quote-total">{{ $quote['quoted_total'] ? $quote['currency'].' '.$quote['quoted_total'] : '—' }}</td></tr>
                @if($quote['saving'] && (float) $quote['saving'] > 0)
                    <tr><td class="p-3 text-green-700" colspan="4" data-testid="quote-saving">{{ __('shop.quote_saving', ['amount' => $quote['currency'].' '.$quote['saving']]) }}</td></tr>
                @endif
            </tfoot>
        </table>
    </section>

    <div class="flex flex-wrap gap-3">
        @if($quote['holds'])
            <form method="POST" action="{{ route('public.shop.quotes.accept', $quote['number']) }}">
                @csrf
                <button type="submit" class="btn-primary" data-testid="quote-accept">{{ $quote['status'] === 'accepted' ? __('shop.quote_back_to_cart') : __('shop.quote_accept') }}</button>
            </form>
        @endif
        @if(in_array($quote['status'], ['requested', 'quoted', 'accepted'], true))
            <form method="POST" action="{{ route('public.shop.quotes.withdraw', $quote['number']) }}" onsubmit="return confirm(@js(__('shop.quote_withdraw_confirm')))">
                @csrf
                <button type="submit" class="btn-secondary" data-testid="quote-withdraw">{{ __('shop.quote_withdraw') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
