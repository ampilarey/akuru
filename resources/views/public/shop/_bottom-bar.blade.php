{{-- BOOKSHOP_PLAN §4 (audit finding 19): most buyers arrive on a phone, from Instagram or Viber.
     Since B2 the cart is on it, with how many things are in it. --}}
@php($cartCount = app(\App\Domains\Bookshop\Actions\Cart\ResolveCartAction::class)->count(auth()->id(), session(\App\Domains\Bookshop\Actions\Cart\ResolveCartAction::SESSION_KEY)))
<div class="h-16 md:hidden" aria-hidden="true"></div>
<nav class="fixed inset-x-0 bottom-0 z-40 border-t bg-white md:hidden" data-testid="shop-bottom-bar" aria-label="{{ __('shop.bookshop_title') }}">
    <ul class="grid grid-cols-4 text-center text-xs">
        <li><a href="{{ route('public.shop.index') }}" class="block py-3 text-brandMaroon-800">🛍️<span class="block">{{ __('shop.bar_shop') }}</span></a></li>
        <li><a href="{{ route('public.shop.index') }}#categories" class="block py-3 text-brandMaroon-800">📚<span class="block">{{ __('shop.bar_categories') }}</span></a></li>
        <li>
            <a href="{{ route('public.shop.cart') }}" class="relative block py-3 text-brandMaroon-800" data-testid="bar-cart">🛒<span class="block">{{ __('shop.bar_cart') }}</span>
                @if($cartCount > 0)<span class="absolute start-1/2 top-1 ms-2 rounded-full bg-brandMaroon-600 px-1.5 text-[10px] font-semibold text-white" data-testid="bar-cart-count">{{ $cartCount }}</span>@endif
            </a>
        </li>
        <li>
            @auth
                <a href="{{ route('public.shop.orders') }}" class="block py-3 text-brandMaroon-800">👤<span class="block">{{ __('shop.bar_account') }}</span></a>
            @else
                <a href="{{ route('login') }}" class="block py-3 text-brandMaroon-800">👤<span class="block">{{ __('shop.bar_sign_in') }}</span></a>
            @endauth
        </li>
    </ul>
</nav>
