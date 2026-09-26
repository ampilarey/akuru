@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN B9 "bulk quotes for schools" (slice B9d): a customer's
     quotes, newest first. Own only. --}}
@section('title', __('shop.my_quotes_title') . ' - ' . __('shop.bookshop_title'))

@section('content')
<div class="container mx-auto max-w-4xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold text-brandMaroon-900" data-testid="quotes-heading">{{ __('shop.my_quotes_title') }}</h1>
        <div class="flex gap-3">
            <a href="{{ route('public.shop.quotes.export') }}" class="btn-secondary" data-testid="export-quotes">{{ __('shop.export_csv') }}</a>
            <a href="{{ route('public.shop.cart') }}" class="btn-secondary">{{ __('shop.cart_title') }}</a>
        </div>
    </div>
    <p class="mb-4 text-sm text-gray-600">{{ __('shop.my_quotes_intro', ['min' => (int) config('bookshop.quotes.min_quantity', 10)]) }}</p>

    @if(count($quotes) === 0)
        <p class="rounded-lg border bg-white p-6 text-gray-600" data-testid="no-quotes">{{ __('shop.no_quotes') }}</p>
    @else
        <ul class="divide-y rounded-lg border bg-white" data-testid="quotes-list">
            @foreach($quotes as $quote)
                <li data-quote="{{ $quote['number'] }}">
                    <a href="{{ route('public.shop.quotes.show', $quote['number']) }}" class="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-brandBeige-50">
                        <span>
                            <span class="font-mono font-semibold text-brandMaroon-900">{{ $quote['number'] }}</span>
                            <span class="ms-2 rounded bg-gray-100 px-2 py-0.5 text-xs" data-status="{{ $quote['status'] }}">{{ __('shop.quote_status_'.$quote['status']) }}</span>
                            <span class="block text-sm text-gray-600" dir="auto">{{ $quote['vendor']['name'] }} · {{ $quote['organisation'] }} · {{ __('shop.items_count', ['count' => array_sum(array_column($quote['items'], 'quantity'))]) }}</span>
                            @if($quote['valid_until'])<span class="block text-xs text-gray-500">{{ __('shop.quote_valid_until', ['date' => $quote['valid_until']]) }}</span>@endif
                        </span>
                        <span class="text-end">
                            @if($quote['quoted_total'])
                                <span class="block font-semibold">{{ $quote['currency'] }} {{ $quote['quoted_total'] }}</span>
                                <span class="block text-xs text-gray-500 line-through">{{ $quote['currency'] }} {{ $quote['list_total'] }}</span>
                            @else
                                <span class="block text-sm text-gray-500">{{ __('shop.quote_list_total') }} {{ $quote['currency'] }} {{ $quote['list_total'] }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
