@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 "My orders" (slice B2). Own only. --}}
@section('title', __('shop.my_orders_title') . ' - ' . __('shop.bookshop_title'))

@section('content')
<div class="container mx-auto max-w-4xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold text-brandMaroon-900" data-testid="orders-heading">{{ __('shop.my_orders_title') }}</h1>
        <div class="flex gap-3">
            <a href="{{ route('public.shop.orders.export') }}" class="btn-secondary" data-testid="export-orders">{{ __('shop.export_csv') }}</a>
            <a href="{{ route('public.shop.index') }}" class="btn-secondary">{{ __('shop.browse_shop') }}</a>
        </div>
    </div>

    @if(count($orders) === 0)
        <p class="rounded-lg border bg-white p-6 text-gray-600" data-testid="no-orders">{{ __('shop.no_orders') }}</p>
    @else
        <ul class="divide-y rounded-lg border bg-white" data-testid="orders-list">
            @foreach($orders as $order)
                <li data-order="{{ $order['number'] }}">
                    <a href="{{ route('public.shop.orders.show', $order['number']) }}" class="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-brandBeige-50">
                        <span>
                            <span class="font-mono font-semibold text-brandMaroon-900">{{ $order['number'] }}</span>
                            <span class="ms-2 rounded bg-gray-100 px-2 py-0.5 text-xs">{{ __('shop.status_'.$order['status']) }}</span>
                            <span class="block text-sm text-gray-600">{{ $order['vendor']['name'] }} · {{ __('shop.items_count', ['count' => $order['item_count']]) }} · {{ $order['delivery']['name'] }}</span>
                            <span class="block text-xs text-gray-500">{{ __('shop.order_placed') }} {{ $order['placed_at'] }}</span>
                        </span>
                        <span class="font-semibold">{{ $order['currency'] }} {{ $order['total'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
