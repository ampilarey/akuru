{{-- BOOKSHOP_PLAN §4 (audit finding 19): most buyers arrive on a phone, from Instagram or Viber.
     Cart and orders join this bar with checkout (B2). --}}
<div class="h-16 md:hidden" aria-hidden="true"></div>
<nav class="fixed inset-x-0 bottom-0 z-40 border-t bg-white md:hidden" data-testid="shop-bottom-bar" aria-label="{{ __('shop.bookshop_title') }}">
    <ul class="grid grid-cols-4 text-center text-xs">
        <li><a href="{{ route('public.shop.index') }}" class="block py-3 text-brandMaroon-800">🛍️<span class="block">{{ __('shop.bar_shop') }}</span></a></li>
        <li><a href="{{ route('public.shop.index') }}#shop-search" class="block py-3 text-brandMaroon-800">🔎<span class="block">{{ __('shop.bar_search') }}</span></a></li>
        <li><a href="{{ route('public.shop.index') }}#categories" class="block py-3 text-brandMaroon-800">📚<span class="block">{{ __('shop.bar_categories') }}</span></a></li>
        <li>
            @auth
                <a href="{{ route('dashboard') }}" class="block py-3 text-brandMaroon-800">👤<span class="block">{{ __('shop.bar_account') }}</span></a>
            @else
                <a href="{{ route('login') }}" class="block py-3 text-brandMaroon-800">👤<span class="block">{{ __('shop.bar_sign_in') }}</span></a>
            @endauth
        </li>
    </ul>
</nav>
