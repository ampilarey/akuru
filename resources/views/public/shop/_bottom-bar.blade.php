{{-- STATUS §5lt: the Bookstore's own tab bar on a phone, as on iruali — Home · Categories · Deals ·
     Account · Cart — in place of the site's Home · Courses · Library bar, on every Bookstore page.
     On a shop's own pages every tab is that shop's. Drawn by the layout, once, with one spacer. --}}
@php
    $bar = app(\App\Domains\Bookshop\Actions\Shop\PresentShopBarAction::class)->execute(
        $shopVendor ?? null,
        auth()->id(),
        session(\App\Domains\Bookshop\Actions\Cart\ResolveCartAction::SESSION_KEY),
    );
    $inShop = $bar['shop'] !== null;
    $filtered = request()->filled('category') || request()->boolean('deals');
    $tabs = [
        'home' => $inShop ? (request()->routeIs('public.shop.vendor') && ! $filtered) : request()->routeIs('public.shop.index'),
        'categories' => request()->routeIs('public.shop.category', 'public.shop.vendor.collection') || ($inShop && request()->filled('category')),
        'deals' => request()->routeIs('public.shop.deals') || request()->boolean('deals'),
        'account' => request()->routeIs('public.shop.orders*', 'public.shop.wishlist*', 'public.shop.quotes*', 'public.shop.track', 'login'),
        'cart' => request()->routeIs('public.shop.cart', 'public.shop.checkout*'),
    ];
    $icons = [
        'home' => $inShop ? 'M4 10l1.5-5h13L20 10M4 10v10h16V10M4 10h16M9 20v-5h6v5' : 'M4 11l8-7 8 7v9h-5v-6H9v6H4v-9z',
        'categories' => 'M4 5h6v6H4V5zm10 0h6v6h-6V5zM4 15h6v6H4v-6zm10 0h6v6h-6v-6z',
        'deals' => 'M3 12V4h8l10 10-8 8L3 12zm5-4.5a1 1 0 100 2 1 1 0 000-2z',
        'account' => 'M12 12a4 4 0 100-8 4 4 0 000 8zm-8 9c1.5-4 4.5-6 8-6s6.5 2 8 6',
        'cart' => 'M3 4h2l2.4 11h11.2L21 7H6.3M9 20a1 1 0 100-2 1 1 0 000 2zm9 0a1 1 0 100-2 1 1 0 000 2z',
    ];
    $categoriesFallback = $inShop ? $bar['home_url'].'#shop-grid' : route('public.shop.index').'#categories';
@endphp
<div class="shop-bar-spacer sm:hidden" aria-hidden="true"></div>
<nav class="shop-bar fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white sm:hidden" data-testid="shop-bottom-bar" data-scope="{{ $inShop ? 'shop' : 'store' }}" aria-label="{{ $inShop ? $bar['shop']['name'] : __('shop.bookshop_title') }}">
    <div class="grid grid-cols-5">
        <a href="{{ $bar['home_url'] }}" class="shop-tab {{ $tabs['home'] ? 'is-active' : '' }}" data-testid="bar-home" @if($tabs['home']) aria-current="page" @endif>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons['home'] }}"/></svg>
            <span>{{ $inShop ? __('shop.bar_shop') : __('shop.bar_home') }}</span>
        </a>
        <a href="{{ $categoriesFallback }}" class="shop-tab {{ $tabs['categories'] ? 'is-active' : '' }}" data-testid="bar-categories" data-shop-sheet-open aria-haspopup="dialog" aria-controls="shop-sheet">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons['categories'] }}"/></svg>
            <span>{{ __('shop.bar_categories') }}</span>
        </a>
        <a href="{{ $bar['deals_url'] }}" class="shop-tab {{ $tabs['deals'] ? 'is-active' : '' }}" data-testid="bar-deals" @if($tabs['deals']) aria-current="page" @endif>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons['deals'] }}"/></svg>
            <span>{{ __('shop.bar_deals') }}</span>
        </a>
        <a href="{{ auth()->check() ? route('public.shop.orders') : route('phone.sign-in', ['next' => parse_url(route('public.shop.orders'), PHP_URL_PATH)]) }}" class="shop-tab {{ $tabs['account'] ? 'is-active' : '' }}" data-testid="bar-account" @if($tabs['account']) aria-current="page" @endif>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons['account'] }}"/></svg>
            <span>{{ auth()->check() ? __('shop.bar_account') : __('shop.bar_sign_in') }}</span>
        </a>
        <a href="{{ route('public.shop.cart') }}" class="shop-tab {{ $tabs['cart'] ? 'is-active' : '' }}" data-testid="bar-cart" @if($tabs['cart']) aria-current="page" @endif>
            <span class="relative">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons['cart'] }}"/></svg>
                @if($bar['cart_count'] > 0)<span class="absolute -top-1.5 -end-2.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brandGold-500 px-1 text-[11px] font-bold text-brandMaroon-900" data-testid="bar-cart-count">{{ $bar['cart_count'] }}</span>@endif
            </span>
            <span>{{ __('shop.bar_cart') }}</span>
        </a>
    </div>
</nav>

{{-- The Categories tab opens this sheet; without script the tab is a plain link to the categories. --}}
<div id="shop-sheet" class="fixed inset-0 z-[60] hidden sm:hidden" role="dialog" aria-modal="true" aria-labelledby="shop-sheet-title" data-testid="shop-sheet" data-shop-sheet>
    <div class="absolute inset-0 bg-black/40" data-shop-sheet-close></div>
    <div class="shop-sheet-panel absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-2xl bg-white text-gray-900 shadow-2xl">
        <div class="sticky top-0 flex items-center justify-between gap-3 border-b bg-white px-4 py-3">
            <h2 id="shop-sheet-title" class="truncate text-lg font-semibold text-brandMaroon-900" dir="auto">{{ $inShop ? $bar['shop']['name'] : __('shop.shop_by_category') }}</h2>
            <button type="button" class="-me-1 p-1 text-gray-600" data-shop-sheet-close aria-label="{{ __('shop.sheet_close') }}" data-testid="shop-sheet-close">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="space-y-5 p-4">
            @if($inShop && count($bar['collections']) > 0)
                <section>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.sheet_collections') }}</h3>
                    <ul class="grid grid-cols-2 gap-2" data-testid="shop-sheet-collections">
                        @foreach($bar['collections'] as $c)
                            <li><a href="{{ $c['url'] }}" class="block rounded-xl border border-gray-200 px-3 py-2.5 text-sm font-medium hover:border-brandMaroon-400" dir="auto">{{ $c['name'] }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
            <section>
                @if($inShop)<h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.bar_categories') }}</h3>@endif
                @if(count($bar['categories']) > 0)
                    <ul class="divide-y rounded-xl border border-gray-200" data-testid="shop-sheet-categories">
                        @foreach($bar['categories'] as $c)
                            <li><a href="{{ $c['url'] }}" class="flex items-center justify-between gap-3 px-3 py-3 text-sm hover:bg-brandBeige-50"><span dir="auto">{{ $c['name'] }}</span><span class="text-xs text-gray-500">{{ $c['count'] }}</span></a></li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-600" data-testid="shop-sheet-empty">{{ __('shop.sheet_empty') }}</p>
                @endif
            </section>
            <section>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.sheet_more') }}</h3>
                <ul class="grid grid-cols-2 gap-2 text-sm">
                    @if($inShop)
                        <li class="col-span-2"><a href="{{ route('public.shop.index') }}" class="block rounded-xl bg-brandBeige-50 px-3 py-2.5 font-medium text-brandMaroon-800" data-testid="shop-sheet-whole-store">{{ __('shop.sheet_whole_store') }}</a></li>
                    @else
                        <li><a href="{{ route('public.shop.index') }}#shops" class="block rounded-xl bg-brandBeige-50 px-3 py-2.5 font-medium text-brandMaroon-800">{{ __('site.shops') }}</a></li>
                        <li><a href="{{ route('public.shop.index') }}#book-lists" class="block rounded-xl bg-brandBeige-50 px-3 py-2.5 font-medium text-brandMaroon-800">{{ __('site.store_book_lists') }}</a></li>
                    @endif
                    <li><a href="{{ route('public.shop.track') }}" class="block rounded-xl bg-brandBeige-50 px-3 py-2.5 font-medium text-brandMaroon-800">{{ __('shop.track_title') }}</a></li>
                    @auth
                        <li><a href="{{ route('public.shop.wishlist') }}" class="block rounded-xl bg-brandBeige-50 px-3 py-2.5 font-medium text-brandMaroon-800">{{ __('shop.wishlist_title') }}</a></li>
                    @else
                        <li><a href="{{ route('vendor.apply') }}" class="block rounded-xl bg-brandBeige-50 px-3 py-2.5 font-medium text-brandMaroon-800">{{ __('site.sell_on_akuru') }}</a></li>
                    @endauth
                </ul>
            </section>
        </div>
    </div>
</div>

<style>
    .shop-bar { padding-bottom: env(safe-area-inset-bottom); box-shadow: 0 -2px 12px rgba(63, 20, 28, .08); }
    .shop-bar-spacer { height: calc(4rem + 1px + env(safe-area-inset-bottom)); }
    .shop-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .25rem; min-height: 4rem; font-size: 11px; font-weight: 600; color: #5E5650; }
    .shop-tab:hover, .shop-tab.is-active { color: #7C2D37; }
    .shop-tab.is-active { font-weight: 700; box-shadow: inset 0 3px 0 #C9A227; }
    .shop-sheet-panel { padding-bottom: env(safe-area-inset-bottom); }
</style>
<script>
(() => {
    const sheet = document.querySelector('[data-shop-sheet]');
    if (!sheet) return;
    let opener = null;
    const set = (open) => {
        sheet.classList.toggle('hidden', !open);
        document.documentElement.classList.toggle('overflow-hidden', open);
        if (open) { sheet.querySelector('[data-testid="shop-sheet-close"]')?.focus(); } else { opener?.focus(); }
    };
    document.querySelectorAll('[data-shop-sheet-open]').forEach((el) => el.addEventListener('click', (e) => { e.preventDefault(); opener = el; set(true); }));
    sheet.querySelectorAll('[data-shop-sheet-close]').forEach((el) => el.addEventListener('click', () => set(false)));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !sheet.classList.contains('hidden')) set(false); });
})();
</script>
