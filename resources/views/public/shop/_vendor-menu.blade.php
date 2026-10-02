{{-- A shop's own menu. The bookstore's chip row (Shops, Deals, used books,
     borrowing) ran off the phone and scrolled the page sideways. These are
     this shop's doors, in a grid that wraps. --}}
@php
    $menu = app(\App\Domains\Bookshop\Actions\Shop\PresentShopBarAction::class)->execute(
        $vendor,
        auth()->id(),
        session(\App\Domains\Bookshop\Actions\Cart\ResolveCartAction::SESSION_KEY),
    );
    $onDeals = request()->boolean('deals');
    $onHome = request()->routeIs('public.shop.vendor') && ! $onDeals && ! request()->filled('category') && empty($collection);
    $taken = array_filter([$menu['home_url'], $menu['deals_url'], ...array_column($menu['collections'], 'url')]);
    $navigation = is_array($vendor['storefront'] ?? null) ? ($vendor['storefront']['navigation'] ?? []) : [];
    $pages = array_values(array_filter(
        $navigation,
        fn (array $entry) => isset($entry['url'], $entry['label']) && ! in_array($entry['url'], $taken, true),
    ));
    $comparing = count(app(\App\Domains\Bookshop\Actions\Shop\CompareProductsAction::class)->ids(session()->driver()));
@endphp
<nav class="vendor-menu container mx-auto min-w-0 px-4 pb-3 {{ $storefront ? 'pt-4' : 'pt-3' }}" aria-label="{{ $menu['shop']['name'] ?? __('shop.storefront_menu') }}" data-testid="vendor-menu">
    <ul>
        <li><a href="{{ $menu['home_url'] }}" @if($onHome) aria-current="page" @endif data-testid="vendor-menu-home">{{ __('shop.home') }}</a></li>
        <li><a href="{{ $menu['deals_url'] }}" @if($onDeals) aria-current="page" @endif data-testid="vendor-menu-deals">{{ __('shop.bar_deals') }}</a></li>
        @foreach($menu['collections'] as $item)
            <li><a href="{{ $item['url'] }}" @if(url()->current() === $item['url']) aria-current="page" @endif dir="auto">{{ $item['name'] }}</a></li>
        @endforeach
        @foreach($pages as $entry)
            <li><a href="{{ $entry['url'] }}" @if(url()->current() === $entry['url']) aria-current="page" @endif dir="auto">{{ $entry['label'] }}</a></li>
        @endforeach
        <li>
            <a href="{{ route('public.shop.cart') }}" data-testid="shop-link-cart">
                {{ __('site.cart') }}@if($menu['cart_count'] > 0)<span class="vendor-menu-count" data-testid="shop-link-cart-count">{{ $menu['cart_count'] }}</span>@endif
            </a>
        </li>
        <li><a href="{{ route('public.shop.orders') }}">{{ __('site.my_orders') }}</a></li>
        <li><a href="{{ route('public.shop.track') }}" data-testid="shop-link-track">{{ __('shop.track_title') }}</a></li>
        @if($comparing > 0)
            <li><a href="{{ route('public.shop.compare') }}" data-testid="shop-link-compare">{{ __('shop.compare_heading') }} <span class="vendor-menu-count">{{ $comparing }}</span></a></li>
        @endif
        <li class="is-wide"><a href="{{ route('public.shop.index') }}#shops" data-testid="vendor-menu-store">{{ __('shop.sheet_whole_store') }}</a></li>
    </ul>
</nav>
