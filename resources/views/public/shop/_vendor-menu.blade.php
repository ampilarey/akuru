{{-- A shop's own menu. On a phone it is one row — Home, Deals, Cart, More —
     so it does not take the screen. More opens the rest. A wider screen
     shows every link in a wrapping row. --}}
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
    $moreOpen = false;
    foreach ($menu['collections'] as $item) {
        if (url()->current() === $item['url']) {
            $moreOpen = true;
        }
    }
    foreach ($pages as $entry) {
        if (url()->current() === $entry['url']) {
            $moreOpen = true;
        }
    }
@endphp
<nav class="vendor-menu container mx-auto min-w-0 px-4 pb-3 {{ $storefront ? 'pt-4' : 'pt-3' }}" aria-label="{{ $menu['shop']['name'] ?? __('shop.storefront_menu') }}" data-testid="vendor-menu">
    <div class="vendor-menu-row" data-testid="vendor-menu-row">
        <a href="{{ $menu['home_url'] }}" @if($onHome) aria-current="page" @endif data-testid="vendor-menu-home">{{ __('shop.home') }}</a>
        <a href="{{ $menu['deals_url'] }}" @if($onDeals) aria-current="page" @endif data-testid="vendor-menu-deals">{{ __('shop.bar_deals') }}</a>
        <a href="{{ route('public.shop.cart') }}" data-testid="shop-link-cart">{{ __('site.cart') }}@if($menu['cart_count'] > 0)<span class="vendor-menu-count" data-testid="shop-link-cart-count">{{ $menu['cart_count'] }}</span>@endif</a>
        <details class="vendor-menu-fold" @if($moreOpen) open @endif>
            <summary data-testid="vendor-menu-more">{{ __('shop.sheet_more') }}</summary>
            <div class="vendor-menu-rest" data-testid="vendor-menu-rest">
                @foreach($menu['collections'] as $item)
                    <a href="{{ $item['url'] }}" @if(url()->current() === $item['url']) aria-current="page" @endif dir="auto">{{ $item['name'] }}</a>
                @endforeach
                @foreach($pages as $entry)
                    <a href="{{ $entry['url'] }}" @if(url()->current() === $entry['url']) aria-current="page" @endif dir="auto">{{ $entry['label'] }}</a>
                @endforeach
                <a href="{{ route('public.shop.orders') }}">{{ __('site.my_orders') }}</a>
                <a href="{{ route('public.shop.track') }}" data-testid="shop-link-track">{{ __('shop.track_title') }}</a>
                @if($comparing > 0)
                    <a href="{{ route('public.shop.compare') }}" data-testid="shop-link-compare">{{ __('shop.compare_heading') }} <span class="vendor-menu-count">{{ $comparing }}</span></a>
                @endif
                <a href="{{ route('public.shop.index') }}#shops" data-testid="vendor-menu-store">{{ __('shop.sheet_whole_store') }}</a>
            </div>
        </details>
    </div>
</nav>
