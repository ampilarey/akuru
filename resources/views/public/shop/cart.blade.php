@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 "Cart" (slice B2): lines by shop, quantities that re-check
     stock on every change, and — for a guest — the two ways to sign in. --}}
@section('title', __('shop.cart_title') . ' - ' . __('shop.bookshop_title'))

@section('content')
<div class="container mx-auto max-w-4xl px-4 py-8">
    <nav class="mb-4 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ __('shop.cart_title') }}</span>
    </nav>
    <h1 class="mb-6 text-3xl font-bold text-brandMaroon-900" data-testid="cart-heading">{{ __('shop.cart_title') }}</h1>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" data-testid="cart-errors">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif

    @if($cart['empty'])
        <div class="rounded-lg border bg-white p-8 text-center" data-testid="cart-empty">
            <p class="mb-4 text-gray-600">{{ __('shop.cart_empty') }}</p>
            <a href="{{ route('public.shop.index') }}" class="btn-primary">{{ __('shop.browse_shop') }}</a>
        </div>
    @else
        @if(count($cart['problems']) > 0)
            <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" data-testid="cart-problems">
                <p class="font-semibold">{{ __('shop.fix_problems') }}</p>
                <ul class="list-disc ps-5">
                    @foreach($cart['problems'] as $problem)<li>{{ $problem }}</li>@endforeach
                </ul>
            </div>
        @endif

        @foreach($cart['groups'] as $group)
            <section class="mb-6 rounded-lg border bg-white" data-testid="cart-group-{{ $group['vendor']['slug'] }}">
                <h2 class="border-b px-4 py-3 text-sm font-semibold text-gray-700">
                    {{ __('shop.sold_by') }} <a href="{{ route('public.shop.vendor', $group['vendor']['slug']) }}" class="text-brandMaroon-700 hover:underline">{{ $group['vendor']['name'] }}</a>
                </h2>
                <ul class="divide-y">
                    @foreach($group['lines'] as $line)
                        <li class="flex flex-wrap items-center gap-4 p-4 {{ $line['sellable'] && ! $line['short'] ? '' : 'bg-amber-50' }}" data-testid="cart-line" data-cart-line="{{ $line['slug'] }}">
                            <a href="{{ route('public.shop.product', $line['slug']) }}" class="block h-16 w-16 shrink-0 overflow-hidden rounded border bg-brandBeige-50">
                                @if($line['image'])<img src="{{ $line['image'] }}" alt="" class="h-full w-full object-cover">@endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('public.shop.product', $line['slug']) }}" class="font-medium text-brandMaroon-900 hover:underline" dir="auto">{{ $line['title'] }}</a>
                                @if($line['variant'])<span class="block text-sm text-gray-600">{{ $line['variant'] }}</span>@endif
                                <span class="block text-sm text-gray-500">{{ $cart['currency'] }} {{ $line['unit_price'] }}</span>
                                @if($line['made_to_order'])<span class="block text-xs text-gray-500">{{ __('shop.made_to_order_note') }}</span>@endif
                                @if($line['short'])<span class="block text-xs text-amber-800">{{ __('shop.stock_short', ['count' => $line['available']]) }}</span>@endif
                            </div>
                            <form method="POST" action="{{ route('public.shop.cart.update', $line['id']) }}" class="flex items-center gap-2">
                                @csrf
                                <label class="sr-only" for="qty-{{ $line['id'] }}">{{ __('shop.quantity') }}</label>
                                <input id="qty-{{ $line['id'] }}" type="number" name="quantity" min="0" max="{{ config('bookshop.checkout.max_quantity_per_line', 50) }}" value="{{ $line['quantity'] }}" class="form-input w-20" data-testid="cart-qty">
                                <button type="submit" class="btn-secondary text-sm">{{ __('shop.update') }}</button>
                                <button type="submit" name="quantity" value="0" class="text-sm text-red-700 underline" data-testid="cart-remove">{{ __('shop.remove') }}</button>
                            </form>
                            <div class="w-28 text-end font-semibold" data-testid="cart-line-total">{{ $cart['currency'] }} {{ $line['line_total'] }}</div>
                        </li>
                    @endforeach
                </ul>
                <p class="border-t px-4 py-2 text-end text-sm text-gray-600">{{ __('shop.subtotal') }}: <span class="font-semibold">{{ $cart['currency'] }} {{ $group['subtotal'] }}</span></p>
                {{-- B7 (§6.5): how far from the shop's free delivery. --}}
                @if($group['vendor']['free_delivery_over'] ?? null)
                    @php($short = round((float) $group['vendor']['free_delivery_over'] - (float) $group['subtotal'], 2))
                    <p class="border-t px-4 py-2 text-sm {{ $short > 0 ? 'text-amber-800' : 'text-green-700' }}" data-testid="free-delivery-{{ $group['vendor']['slug'] }}">
                        {{ $short > 0 ? __('shop.free_delivery_nudge', ['amount' => $cart['currency'].' '.number_format($short, 2), 'vendor' => $group['vendor']['name']]) : __('shop.free_delivery_reached', ['vendor' => $group['vendor']['name']]) }}
                    </p>
                @endif
            </section>
        @endforeach

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-white p-4">
            <div>
                <p class="text-lg font-semibold" data-testid="cart-subtotal">{{ __('shop.subtotal') }}: {{ $cart['currency'] }} {{ $cart['subtotal'] }}</p>
                <p class="text-xs text-gray-500">{{ __('shop.delivery_at_checkout') }} {{ __('shop.prices_include_tax') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('public.shop.index') }}" class="text-sm text-brandMaroon-700 underline">{{ __('shop.continue_shopping') }}</a>
                @if($signed_in)
                    <a href="{{ route('public.shop.checkout') }}" class="btn-primary {{ count($cart['problems']) > 0 ? 'pointer-events-none opacity-50' : '' }}" data-testid="go-to-checkout">{{ __('shop.go_to_checkout') }}</a>
                @endif
            </div>
        </div>

        @unless($signed_in)
            <div class="mt-6 rounded-lg border border-brandMaroon-200 bg-brandBeige-50 p-4" data-testid="cart-sign-in">
                <p class="font-semibold text-brandMaroon-900">{{ __('shop.sign_in_to_checkout') }}</p>
                <p class="mb-3 text-sm text-gray-600">{{ __('shop.sign_in_intro') }}</p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="btn-primary">{{ __('shop.sign_in_password') }}</a>
                    <a href="{{ route('otp.login.form') }}" class="btn-secondary">{{ __('shop.sign_in_otp') }}</a>
                </div>
            </div>
        @endunless
    @endif
</div>

@include('public.shop._bottom-bar')
@endsection
