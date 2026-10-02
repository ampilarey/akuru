{{-- A shop's own menu. The phone already has Shop, Deals, Cart and Account
     fixed at the bottom, so this row does not repeat them. What is left
     (collections, pages, tracking, compare, the whole bookstore) stays on
     one row, with More when that is more than three. More is a button, so
     a second tap closes it. A wider screen has no bottom bar, so it shows
     every link, including Home, Deals, Cart and orders, and hides More. --}}
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
    $link = function (array $item, bool $current): array {
        return [
            'url' => $item['url'],
            'label' => $item['label'],
            'current' => $current,
            'testid' => $item['testid'] ?? null,
            'count' => $item['count'] ?? null,
            'group' => $item['group'],
        ];
    };
    $before = [];
    foreach ($menu['collections'] as $item) {
        $before[] = $link(
            ['url' => $item['url'], 'label' => $item['name'], 'group' => 'before'],
            url()->current() === $item['url'],
        );
    }
    foreach ($pages as $entry) {
        $before[] = $link(
            ['url' => $entry['url'], 'label' => $entry['label'], 'group' => 'before'],
            url()->current() === $entry['url'],
        );
    }
    $after = [
        $link(
            ['url' => route('public.shop.track'), 'label' => __('shop.track_title'), 'testid' => 'shop-link-track', 'group' => 'after'],
            request()->routeIs('public.shop.track'),
        ),
    ];
    if ($comparing > 0) {
        $after[] = $link(
            ['url' => route('public.shop.compare'), 'label' => __('shop.compare_heading'), 'testid' => 'shop-link-compare', 'count' => $comparing, 'group' => 'after'],
            request()->routeIs('public.shop.compare'),
        );
    }
    $after[] = $link(
        ['url' => route('public.shop.index').'#shops', 'label' => __('shop.sheet_whole_store'), 'testid' => 'vendor-menu-store', 'group' => 'after'],
        false,
    );
    $phoneItems = [...$before, ...$after];
    $leadCount = count($phoneItems) > 3 ? 2 : count($phoneItems);
    $leads = array_slice($phoneItems, 0, $leadCount);
    $overflow = array_slice($phoneItems, $leadCount);
    $overflowBefore = array_values(array_filter($overflow, fn (array $item) => $item['group'] === 'before'));
    $overflowAfter = array_values(array_filter($overflow, fn (array $item) => $item['group'] === 'after'));
    // Orders sits with the row once every collection and page is already on
    // that row. Otherwise it stays in the fold, between those and tracking,
    // and the phone hides it either way.
    $ordersInRow = $leadCount >= count($before);
    $moreOpen = false;
    foreach ($overflow as $item) {
        if ($item['current']) {
            $moreOpen = true;
        }
    }
    $ordersCurrent = request()->routeIs('public.shop.orders*');
@endphp
<nav class="vendor-menu container mx-auto min-w-0 px-4 pb-3 {{ $storefront ? 'pt-4' : 'pt-3' }}" aria-label="{{ $menu['shop']['name'] ?? __('shop.storefront_menu') }}" data-testid="vendor-menu">
    <div class="vendor-menu-row" data-testid="vendor-menu-row">
        <a href="{{ $menu['home_url'] }}" class="vendor-menu-desk" @if($onHome) aria-current="page" @endif data-testid="vendor-menu-home">{{ __('shop.home') }}</a>
        <a href="{{ $menu['deals_url'] }}" class="vendor-menu-desk" @if($onDeals) aria-current="page" @endif data-testid="vendor-menu-deals">{{ __('shop.bar_deals') }}</a>
        <a href="{{ route('public.shop.cart') }}" class="vendor-menu-desk" data-testid="shop-link-cart">{{ __('site.cart') }}@if($menu['cart_count'] > 0)<span class="vendor-menu-count" data-testid="shop-link-cart-count">{{ $menu['cart_count'] }}</span>@endif</a>
        @php($ordersPlaced = false)
        @foreach($leads as $item)
            @if($ordersInRow && ! $ordersPlaced && $item['group'] === 'after')
                <a href="{{ route('public.shop.orders') }}" class="vendor-menu-desk" @if($ordersCurrent) aria-current="page" @endif data-testid="vendor-menu-orders">{{ __('site.my_orders') }}</a>
                @php($ordersPlaced = true)
            @endif
            <a href="{{ $item['url'] }}" @if($item['current']) aria-current="page" @endif @if($item['testid']) data-testid="{{ $item['testid'] }}" @endif dir="auto">{{ $item['label'] }}@if($item['count'] !== null) <span class="vendor-menu-count">{{ $item['count'] }}</span>@endif</a>
        @endforeach
        @if($ordersInRow && ! $ordersPlaced)
            <a href="{{ route('public.shop.orders') }}" class="vendor-menu-desk" @if($ordersCurrent) aria-current="page" @endif data-testid="vendor-menu-orders">{{ __('site.my_orders') }}</a>
        @endif
        <div class="vendor-menu-fold" @if(count($overflow) === 0) data-phone-empty @endif @if($moreOpen) data-open @endif>
            <button type="button" data-testid="vendor-menu-more" aria-expanded="{{ $moreOpen ? 'true' : 'false' }}" aria-controls="vendor-menu-rest">{{ __('shop.sheet_more') }}</button>
            <div class="vendor-menu-rest" id="vendor-menu-rest" data-testid="vendor-menu-rest">
                @foreach($overflowBefore as $item)
                    <a href="{{ $item['url'] }}" @if($item['current']) aria-current="page" @endif @if($item['testid']) data-testid="{{ $item['testid'] }}" @endif dir="auto">{{ $item['label'] }}@if($item['count'] !== null) <span class="vendor-menu-count">{{ $item['count'] }}</span>@endif</a>
                @endforeach
                @if(! $ordersInRow)
                    <a href="{{ route('public.shop.orders') }}" class="vendor-menu-desk" @if($ordersCurrent) aria-current="page" @endif data-testid="vendor-menu-orders">{{ __('site.my_orders') }}</a>
                @endif
                @foreach($overflowAfter as $item)
                    <a href="{{ $item['url'] }}" @if($item['current']) aria-current="page" @endif @if($item['testid']) data-testid="{{ $item['testid'] }}" @endif dir="auto">{{ $item['label'] }}@if($item['count'] !== null) <span class="vendor-menu-count">{{ $item['count'] }}</span>@endif</a>
                @endforeach
            </div>
        </div>
    </div>
</nav>
<script>
document.querySelectorAll('[data-testid="vendor-menu-more"]').forEach(function (button) {
    if (button.dataset.bound) return;
    button.dataset.bound = '1';
    button.addEventListener('click', function () {
        var fold = button.parentElement;
        var open = fold.hasAttribute('data-open');
        if (open) {
            fold.removeAttribute('data-open');
            button.setAttribute('aria-expanded', 'false');
        } else {
            fold.setAttribute('data-open', '');
            button.setAttribute('aria-expanded', 'true');
        }
    });
});
</script>
