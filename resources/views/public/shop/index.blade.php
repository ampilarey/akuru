@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN B1b: the shop home, a category, a search, and a vendor's
     plain page — one catalogue in one view. --}}
@php($pageTitle = $heading ?? __('shop.bookshop_title'))
@php($storefront = $vendor['storefront'] ?? null)
@php($collection = $collection ?? null)
{{-- B5 (§6.7): a published storefront's own title, description and share image on its home. --}}
@php($seo = $storefront && ! $collection ? $storefront['seo'] : null)
@section('title', ($seo ? $seo['title'] : $pageTitle) . ' - ' . config('app.name'))
@section('description', $seo && $seo['description'] ? $seo['description'] : ($collection['description'] ?? $vendor['tagline'] ?? __('shop.shop_intro')))
@if($seo && $seo['image'])
    @section('og_image', $seo['image'])
@endif

@if($storefront)
    @include('public.shop._theme')
@endif

@section('content')
@if($storefront)
{{-- B4: the vendor's own look, inside the Akuru frame. --}}
<div class="storefront {{ $storefront['theme']['shape']['button'] === 'outlined' ? 'sf-outlined' : '' }}" data-testid="storefront" data-preview="{{ ($preview ?? false) ? '1' : '0' }}" data-preset="{{ $storefront['theme']['preset'] ?? 'custom' }}">
@if($preview ?? false)
    <p class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900" data-testid="preview-banner">{{ __('shop.preview_banner') }}</p>
@endif
@include('public.shop._storefront', ['part' => 'head'])
@include('public.shop._nav')
@if($collection)
    <section class="container mx-auto px-4 pt-8" data-testid="collection-head">
        <nav class="mb-2 text-sm opacity-70"><a href="{{ $storefront['home_url'] }}" class="hover:underline">{{ $storefront['name'] }}</a> › <span>{{ $collection['name'] }}</span></nav>
        <h2 class="text-2xl md:text-3xl font-bold" dir="auto" data-testid="collection-title">{{ $collection['name'] }}</h2>
        @if($collection['description'])<p class="mt-1 opacity-90" dir="auto">{{ $collection['description'] }}</p>@endif
    </section>
@else
    {{-- B5 (§6.3): the vendor's sections, then the story and contact block. --}}
    @if(count($storefront['sections']) > 0)
        @include('public.shop._sections', ['sections' => $storefront['sections'], 'preview' => $preview ?? false])
    @endif
    @include('public.shop._storefront', ['part' => 'about'])
@endif
@else
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-10">
    <div class="container mx-auto px-4">
        @if($vendor || $heading)
            <nav class="mb-3 text-sm text-gray-500">
                <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
                <span>›</span>
                @if($collection && $vendor)
                    <a href="{{ route('public.shop.vendor', $vendor['slug']) }}" class="hover:text-brandMaroon-600">{{ $vendor['name'] }}</a>
                    <span>›</span>
                @endif
                <span class="text-gray-700">{{ $heading }}</span>
            </nav>
        @endif
        <h1 class="text-3xl md:text-4xl font-bold text-brandMaroon-900" data-testid="{{ $collection ? 'collection-title' : 'shop-heading' }}" dir="auto">{{ $pageTitle }}</h1>
        @if($collection)
            @if($collection['description'])<p class="mt-1 text-lg text-brandGray-700" dir="auto">{{ $collection['description'] }}</p>@endif
            <p class="mt-1 text-sm text-brandGray-600" data-testid="at-akuru">{{ $vendor['name'] }} · {{ __('shop.at_akuru') }}</p>
        @elseif($vendor)
            @if($vendor['tagline'])
                <p class="mt-1 text-lg text-brandGray-700">{{ $vendor['tagline'] }}</p>
            @endif
            <p class="mt-1 text-sm text-brandGray-600" data-testid="at-akuru">{{ __('shop.at_akuru') }}</p>
            @if($vendor['holiday'] ?? null)
                <div class="mt-3 inline-block rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900" data-testid="holiday-notice">
                    <p class="font-semibold">{{ __('shop.back_on', ['date' => $vendor['holiday']['back_on']]) }}</p>
                    @if($vendor['holiday']['notice'])<p dir="auto">{{ $vendor['holiday']['notice'] }}</p>@endif
                </div>
            @endif
        @elseif(! $heading)
            <p class="mt-2 text-lg text-brandGray-700">{{ __('shop.shop_intro') }}</p>
        @endif
    </div>
</section>
@endif

{{-- On a phone the search stays in view and the rest folds under "Filter and sort",
     so the products are on the first screen (the owner's screenshot, STATUS §5kv). The
     fold is served open, so the filters are there without script; the script folds
     it on a phone unless a filter is already in use. --}}
@php($activeFilters = collect(['category', 'language', 'price_min', 'price_max', 'in_stock'])
    ->filter(fn ($key) => ! empty($filters[$key]) && ! (request()->routeIs('public.shop.category') && $key === 'category'))
    ->count() + ((($filters['sort'] ?? 'newest') !== 'newest') ? 1 : 0))
<section class="border-b py-4 {{ $storefront ? '' : 'bg-white' }}">
    <div class="container mx-auto px-4">
        <form method="GET" action="{{ url()->current() }}" data-testid="shop-filters">
            <div class="flex items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="shop-search" class="mb-1 block text-xs text-gray-500">{{ __('shop.search') }}</label>
                    {{-- B7 (§4 "suggestions as you type"): a listbox under the box, from shop/suggest. --}}
                    <div class="relative">
                        <input id="shop-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input w-full" placeholder="{{ __('shop.search_shop') }}"
                            autocomplete="off" role="combobox" aria-expanded="false" aria-controls="shop-suggest" aria-autocomplete="list" data-suggest-url="{{ route('public.shop.suggest') }}" data-testid="shop-search">
                        <ul id="shop-suggest" role="listbox" class="absolute z-30 mt-1 hidden max-h-96 w-full overflow-y-auto rounded border bg-white text-sm shadow-lg" data-testid="shop-suggest"></ul>
                    </div>
                </div>
                <button type="submit" class="btn-primary shrink-0" aria-label="{{ __('shop.search') }}" data-testid="shop-search-go">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
            </div>
            <details class="shop-more mt-3" open data-active="{{ $activeFilters }}" data-testid="shop-more">
                <summary class="shop-more-toggle md:hidden" data-testid="shop-more-toggle">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M6 12h12M10 19h4"/></svg>
                    {{ __('shop.filter_and_sort') }}
                    @if($activeFilters > 0)<span class="shop-more-count">{{ $activeFilters }}</span>@endif
                </summary>
                <div class="mt-3 grid grid-cols-2 items-end gap-3 md:mt-0 md:flex md:flex-wrap">
                    @if(! request()->routeIs('public.shop.category'))
                        <div class="col-span-2 md:col-span-1">
                            <label class="mb-1 block text-xs text-gray-500">{{ __('shop.category') }}</label>
                            <select name="category" class="form-input pe-9" data-testid="filter-category">
                                <option value="">{{ __('shop.all_categories') }}</option>
                                @foreach($options['categories'] as $category)
                                    <option value="{{ $category['slug'] }}" @selected(($filters['category'] ?? '') === $category['slug'])>{{ $category['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ __('shop.language') }}</label>
                        <select name="language" class="form-input pe-9">
                            <option value="">{{ __('shop.any_language') }}</option>
                            @foreach(['English' => 'lang_english', 'Dhivehi' => 'lang_dhivehi', 'Arabic' => 'lang_arabic'] as $value => $key)
                                <option value="{{ $value }}" @selected(($filters['language'] ?? '') === $value)>{{ __('shop.'.$key) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ __('shop.sort') }}</label>
                        <select name="sort" class="form-input pe-9" data-testid="filter-sort">
                            @foreach($options['sorts'] as $sort)
                                <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'newest') === $sort)>{{ __('shop.sort_'.$sort) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <label class="mb-1 block text-xs text-gray-500">{{ __('shop.price') }}</label>
                        <div class="flex gap-2">
                            <input type="number" name="price_min" min="0" step="1" value="{{ $filters['price_min'] ?? '' }}" class="form-input md:w-24" placeholder="{{ __('shop.price_from') }}" aria-label="{{ __('shop.price_from') }}">
                            <input type="number" name="price_max" min="0" step="1" value="{{ $filters['price_max'] ?? '' }}" class="form-input md:w-24" placeholder="{{ __('shop.price_to') }}" aria-label="{{ __('shop.price_to') }}">
                        </div>
                    </div>
                    <label class="col-span-2 flex min-h-[44px] items-center gap-2 text-sm md:col-span-1">
                        <input type="checkbox" name="in_stock" value="1" class="h-5 w-5 rounded border-gray-300" @checked(! empty($filters['in_stock'])) data-testid="filter-in-stock"> {{ __('shop.in_stock_only') }}
                    </label>
                    <div class="col-span-2 flex flex-wrap items-center gap-3 md:col-span-1">
                        <button type="submit" class="btn-primary">{{ __('shop.filter') }}</button>
                        <a href="{{ url()->current() }}" class="btn-secondary">{{ __('shop.clear_filters') }}</a>
                        {{-- The list as a spreadsheet (CLAUDE.md: every listing), kept small: most shoppers never want it. --}}
                        <a href="{{ route('public.shop.export', $filters) }}" class="text-sm text-gray-600 underline hover:text-brandMaroon-700" data-testid="shop-export">{{ __('shop.export_csv') }}</a>
                    </div>
                </div>
            </details>
        </form>
    </div>
</section>
@push('styles')
<style>
    .shop-more > summary { list-style: none; }
    .shop-more > summary::-webkit-details-marker { display: none; }
    .shop-more-toggle { display: inline-flex; align-items: center; gap: .5rem; min-height: 44px; padding: 0 1rem; border: 1px solid #DCCFBE; border-radius: .6rem; background: #fff; font-size: .875rem; font-weight: 600; color: #3F3A36; cursor: pointer; }
    .shop-more[open] > .shop-more-toggle { border-color: #7C2D37; color: #7C2D37; }
    .shop-more-count { display: inline-flex; align-items: center; justify-content: center; min-width: 1.25rem; height: 1.25rem; padding: 0 .3rem; border-radius: 999px; background: #7C2D37; color: #fff; font-size: .75rem; }
    @media (min-width: 768px) { .shop-more > summary { display: none; } }
</style>
@endpush
@push('scripts')
<script>
(() => {
    const more = document.querySelector('[data-testid="shop-more"]');
    if (more && !window.matchMedia('(min-width: 768px)').matches && more.dataset.active === '0') {
        more.open = false;
    }
})();
</script>
@endpush

@if($home)
    {{-- B7 (§7): the office's hero slides, featured products and collections; best sellers; recently viewed. --}}
    @if(count($home['hero']) > 0)
        <section class="shop-hero relative overflow-hidden bg-brandMaroon-900 text-white" data-testid="shop-hero">
            <div class="flex snap-x snap-mandatory overflow-x-auto">
                @foreach($home['hero'] as $slide)
                    <div class="relative w-full shrink-0 snap-start" data-testid="hero-slide">
                        @if($slide['image'])<img src="{{ $slide['image'] }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60" loading="{{ $loop->first ? 'eager' : 'lazy' }}">@endif
                        <div class="relative container mx-auto px-4 py-14 md:py-20">
                            <h2 class="text-3xl md:text-4xl font-bold" dir="auto">{{ $slide['heading'] }}</h2>
                            @if($slide['subheading'])<p class="mt-2 max-w-2xl text-lg" dir="auto">{{ $slide['subheading'] }}</p>@endif
                            @if($slide['url'])<a href="{{ $slide['url'] }}" class="btn-primary mt-5 inline-block" data-testid="hero-link">{{ __('shop.shop_now') }}</a>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @foreach([['featured', 'featured_heading', 'shop-featured'], ['best_sellers', 'best_sellers', 'shop-best-sellers'], ['recently_viewed', 'recently_viewed', 'shop-recently-viewed']] as [$key, $label, $testid])
        @if(count($home[$key] ?? []) > 0)
            <section class="py-8 {{ $key === 'featured' ? 'bg-brandBeige-50' : '' }}" data-testid="{{ $testid }}">
                <div class="container mx-auto px-4">
                    <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">{{ __('shop.'.$label) }}</h2>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        @foreach(array_slice($home[$key], 0, $key === 'featured' ? 12 : 8) as $card)
                            @include('public.shop._card', ['card' => $card])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endforeach

    @foreach($home['collections'] as $collection)
        <section class="py-8" data-testid="shop-collection">
            <div class="container mx-auto px-4">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-xl font-semibold text-brandMaroon-900" dir="auto">{{ $collection['name'] }} <span class="text-sm font-normal text-gray-500">· {{ $collection['vendor'] }}</span></h2>
                    <a href="{{ $collection['url'] }}" class="text-sm text-brandMaroon-700 underline">{{ __('shop.see_all') }} →</a>
                </div>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    @foreach($collection['cards'] as $card)
                        @include('public.shop._card', ['card' => $card])
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    @if(count($home['categories']) > 0)
        <section id="categories" class="py-8" data-testid="shop-categories">
            <div class="container mx-auto px-4">
                <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">{{ __('shop.shop_by_category') }}</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($home['categories'] as $category)
                        <a href="{{ route('public.shop.category', $category['slug']) }}" class="rounded-full border bg-white px-4 py-2 text-sm hover:border-brandMaroon-400">
                            {{ $category['name'] }} <span class="text-gray-500">({{ $category['count'] }})</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(count($home['new_arrivals']) > 0)
        <section class="bg-brandBeige-50 py-8" data-testid="new-arrivals">
            <div class="container mx-auto px-4">
                <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">{{ __('shop.new_arrivals') }}</h2>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    @foreach($home['new_arrivals'] as $card)
                        @include('public.shop._card', ['card' => $card])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(count($home['vendors']) > 0)
        <section class="py-8" data-testid="shop-vendors">
            <div class="container mx-auto px-4">
                <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">{{ __('shop.our_shops') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                    @foreach($home['vendors'] as $shop)
                        <a href="{{ route('public.shop.vendor', $shop['slug']) }}" class="flex items-center gap-3 rounded-lg border bg-white p-4 hover:shadow-sm" data-vendor="{{ $shop['slug'] }}">
                            @if($shop['logo'])
                                <img src="{{ $shop['logo'] }}" alt="" class="h-14 w-14 shrink-0 rounded-lg object-cover" loading="lazy" data-testid="shop-vendor-logo">
                            @else
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-brandBeige-100 text-xl font-bold text-brandMaroon-700" aria-hidden="true">{{ mb_substr($shop['display_name'], 0, 1) }}</span>
                            @endif
                            <span class="min-w-0">
                                <span class="block font-semibold text-brandMaroon-900" dir="auto">{{ $shop['display_name'] }}</span>
                                @if($shop['tagline'])<span class="block truncate text-sm text-gray-600" dir="auto">{{ $shop['tagline'] }}</span>@endif
                                <span class="mt-1 block text-xs {{ $shop['count'] > 0 ? 'text-gray-500' : 'text-brandGold-700' }}" data-testid="shop-vendor-count">
                                    {{ $shop['count'] > 0 ? __('shop.result_count', ['count' => $shop['count']]) : __('shop.opening_soon') }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
    {{-- B9a (§3 "apply → approve"): the way in for a new shop, while the office accepts applications. --}}
    @if($home['applications_open'] ?? false)
        <section class="py-8" data-testid="open-a-shop">
            <div class="container mx-auto px-4">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-brandMaroon-200 bg-brandBeige-50 p-5">
                    <div>
                        <h2 class="text-lg font-semibold text-brandMaroon-900">{{ __('shop.sell_here_heading') }}</h2>
                        <p class="text-sm text-gray-700">{{ __('shop.sell_here_body') }}</p>
                    </div>
                    <a href="{{ route('vendor.apply') }}" class="btn-primary" data-testid="open-a-shop-link">{{ __('shop.apply_title') }}</a>
                </div>
            </div>
        </section>
    @endif
@endif

<section class="py-8">
    <div class="container mx-auto px-4">
        <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">
            {{ $home || ($storefront && ! $collection && count($storefront['sections']) > 0) ? __('shop.all_products') : __('shop.results') }}
            <span class="text-sm font-normal text-gray-500" data-testid="result-count">{{ __('shop.result_count', ['count' => $products->total()]) }}</span>
        </h2>
        @if($products->total() === 0)
            {{-- A shop with nothing listed yet says so, rather than blaming the search. --}}
            <p class="text-gray-500" data-testid="shop-empty">{{ $vendor && ! $collection && $activeFilters === 0 && empty($filters['q']) ? __('shop.shop_nothing_listed') : __('shop.no_results') }}</p>
        @endif
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4" data-testid="shop-grid">
            @foreach($products as $card)
                @include('public.shop._card', ['card' => $card])
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    </div>
</section>
@if($storefront)
</div>
@endif

@include('public.shop._bottom-bar')
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('shop-search');
    const list = document.getElementById('shop-suggest');
    if (!input || !list) return;
    let timer = null, active = -1, items = [];
    const labels = @json(['products' => __('shop.suggest_products'), 'vendors' => __('shop.suggest_shops'), 'categories' => __('shop.suggest_categories')]);
    const close = () => { list.classList.add('hidden'); list.innerHTML = ''; input.setAttribute('aria-expanded', 'false'); active = -1; items = []; };
    const mark = () => items.forEach((el, i) => { el.setAttribute('aria-selected', i === active ? 'true' : 'false'); el.classList.toggle('bg-gray-100', i === active); });
    const render = (data) => {
        list.innerHTML = '';
        items = [];
        for (const kind of ['products', 'vendors', 'categories']) {
            if (!(data[kind] || []).length) continue;
            const head = document.createElement('li');
            head.className = 'px-3 pt-2 text-xs uppercase text-gray-500';
            head.setAttribute('role', 'presentation');
            head.textContent = labels[kind];
            list.appendChild(head);
            for (const row of data[kind]) {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.url = row.url;
                li.className = 'flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-gray-100';
                if (row.image) { const img = document.createElement('img'); img.src = row.image; img.alt = ''; img.className = 'h-8 w-8 rounded object-cover'; li.appendChild(img); }
                const text = document.createElement('span');
                text.dir = 'auto';
                text.textContent = row.title || row.name;
                li.appendChild(text);
                if (row.vendor) { const sub = document.createElement('span'); sub.className = 'ms-auto text-xs text-gray-500'; sub.textContent = row.vendor + ' · ' + row.price; li.appendChild(sub); }
                li.addEventListener('mousedown', (e) => { e.preventDefault(); window.location.href = row.url; });
                list.appendChild(li);
                items.push(li);
            }
        }
        if (items.length === 0) { close(); return; }
        list.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
    };
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { close(); return; }
        timer = setTimeout(() => {
            fetch(input.dataset.suggestUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null)).then((data) => { if (data && input.value.trim() === q) render(data); }).catch(() => {});
        }, 200);
    });
    input.addEventListener('keydown', (e) => {
        if (list.classList.contains('hidden')) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(items.length - 1, active + 1); mark(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(-1, active - 1); mark(); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); window.location.href = items[active].dataset.url; }
        else if (e.key === 'Escape') { close(); }
    });
    input.addEventListener('blur', () => setTimeout(close, 150));
})();
</script>
@endpush
