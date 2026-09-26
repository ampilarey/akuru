{{-- BOOKSHOP_PLAN §7 "shop on/off" (slice B11): what the public sees while the office has closed the shop.
     A customer's own orders, quotes and wishlist are still theirs to open. --}}
@extends('public.layouts.public')

@section('title', __('shop.closed_title') . ' - ' . __('shop.bookshop_title'))

@section('content')
<div class="container mx-auto max-w-xl px-4 py-16 text-center" data-testid="shop-closed">
    <h1 class="mb-3 text-3xl font-bold text-brandMaroon-900">{{ __('shop.closed_title') }}</h1>
    <p class="text-gray-700" dir="auto">{{ $message ?: __('shop.closed_default') }}</p>
    @auth
        <p class="mt-6 text-sm"><a href="{{ route('public.shop.orders') }}" class="text-brandMaroon-700 underline">{{ __('shop.my_orders_title') }}</a></p>
    @endauth
</div>
@endsection
