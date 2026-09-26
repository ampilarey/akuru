@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 "Wishlist (signed in)" (slice B7): what the customer saved for later. --}}
@section('title', __('shop.wishlist_title') . ' - ' . __('shop.bookshop_title'))

@section('content')
<div class="container mx-auto max-w-6xl px-4 py-8">
    <nav class="mb-3 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a> ›
        <a href="{{ route('public.shop.orders') }}" class="hover:text-brandMaroon-600">{{ __('nav.my_orders') }}</a>
    </nav>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-brandMaroon-900" data-testid="wishlist-heading">{{ __('shop.wishlist_title') }}</h1>
        @if(count($cards) > 0)<a href="{{ route('public.shop.wishlist.export') }}" class="btn-secondary">{{ __('shop.export_csv') }}</a>@endif
    </div>
    @if(session('success'))
        <p class="mb-4 rounded bg-green-50 p-3 text-green-800" data-testid="flash-success">{{ session('success') }}</p>
    @endif
    @if(count($cards) === 0)
        <p class="rounded border bg-white p-6 text-gray-600" data-testid="wishlist-empty">{{ __('shop.wishlist_empty') }} <a href="{{ route('public.shop.index') }}" class="text-brandMaroon-700 underline">{{ __('shop.bar_shop') }}</a></p>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4" data-testid="wishlist-grid">
            @foreach($cards as $card)
                <div class="flex flex-col gap-2">
                    @include('public.shop._card', ['card' => $card])
                    <form method="POST" action="{{ route('public.shop.wishlist.toggle', $card['slug']) }}">
                        @csrf
                        <button type="submit" class="text-sm text-red-700 underline" data-testid="wishlist-remove-{{ $card['slug'] }}">{{ __('shop.remove_from_wishlist') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>

@include('public.shop._bottom-bar')
@endsection
