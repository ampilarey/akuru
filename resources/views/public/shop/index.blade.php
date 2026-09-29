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
{{-- STATUS §5kz: a shop's own pages end with the copyright line, not the Akuru footer. --}}
@if($vendor)
    @section('shop_footer', '1')
@endif

@section('content')
@push('shop_links')
{{-- STATUS §5ky, §5kz: the store's doors, on the store and on every shop's page
     (a shop with a storefront too) — every shop, the categories, the cart, the
     customer's orders, and the way in for a shop owner. On a shop's own page no
     bar is fixed to the foot of a phone, so the cart is here. --}}
@php($cartCount = app(\App\Domains\Bookshop\Actions\Cart\ResolveCartAction::class)->count(auth()->id(), session(\App\Domains\Bookshop\Actions\Cart\ResolveCartAction::SESSION_KEY)))
<div class="{{ $storefront ? '' : 'bg-brandBeige-50' }}">
    <nav class="shop-scroll container mx-auto flex gap-2 overflow-x-auto px-4 pb-3 sm:flex-wrap sm:overflow-visible {{ $storefront ? 'pt-4' : 'pt-3' }} text-sm" aria-label="{{ __('site.store_menu') }}" data-testid="shop-links">
        <a href="{{ $home ? '#shops' : route('public.shop.index').'#shops' }}" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 font-semibold text-brandMaroon-800 hover:bg-brandMaroon-50" data-testid="shop-link-shops">{{ __('site.shops') }}</a>
        <a href="{{ route('public.shop.deals') }}" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50" data-testid="shop-link-deals">{{ __('site.store_deals') }}</a>
        <a href="{{ $home ? '#book-lists' : route('public.shop.index').'#book-lists' }}" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50" data-testid="shop-link-book-lists">{{ __('site.store_book_lists') }}</a>
        <a href="{{ $home ? '#categories' : route('public.shop.index').'#categories' }}" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50">{{ __('site.shop_categories') }}</a>
        <a href="{{ route('public.shop.cart') }}" class="inline-flex shrink-0 items-center whitespace-nowrap gap-1 rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50" data-testid="shop-link-cart">{{ __('site.cart') }}@if($cartCount > 0)<span class="rounded-full bg-brandMaroon-600 px-1.5 text-xs font-semibold text-white" data-testid="shop-link-cart-count">{{ $cartCount }}</span>@endif</a>
        {{-- §5li: the comparison, once something is in it. --}}
        @php($comparing = count(app(\App\Domains\Bookshop\Actions\Shop\CompareProductsAction::class)->ids(session()->driver())))
        @if($comparing > 0)<a href="{{ route('public.shop.compare') }}" class="inline-flex shrink-0 items-center whitespace-nowrap gap-1 rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50" data-testid="shop-link-compare">{{ __('shop.compare_heading') }} <span class="rounded-full bg-brandMaroon-600 px-1.5 text-xs font-semibold text-white">{{ $comparing }}</span></a>@endif
        <a href="{{ route('public.shop.orders') }}" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50">{{ __('site.my_orders') }}</a>
        <a href="{{ route('public.shop.track') }}" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50" data-testid="shop-link-track">{{ __('shop.track_title') }}</a>
        <a href="{{ route('vendor.apply') }}" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50">{{ __('site.sell_on_akuru') }}</a>
        <a href="{{ route('vendor.index') }}" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-brandMaroon-200 bg-white px-3 py-1.5 text-brandMaroon-800 hover:bg-brandMaroon-50" data-testid="shop-link-owners">{{ __('site.shop_owner_signin') }}</a>
    </nav>
</div>
@endpush
@if($storefront)
{{-- B4: the vendor's own look, inside the Akuru frame. --}}
<div class="storefront {{ $storefront['theme']['shape']['button'] === 'outlined' ? 'sf-outlined' : '' }}" data-testid="storefront" data-preview="{{ ($preview ?? false) ? '1' : '0' }}" data-preset="{{ $storefront['theme']['preset'] ?? 'custom' }}">
@if($preview ?? false)
    <p class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900" data-testid="preview-banner">{{ __('shop.preview_banner') }}</p>
@endif
@include('public.shop._storefront', ['part' => 'head'])
@include('public.shop._nav')
@stack('shop_links')
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
{{-- STATUS §5lu, after iruali: the store's front opens on a hero card with two tiles beside it;
     a shop's page on a card with its name; the other listings on a plain heading. Each is
     followed by the store's links and, off the front, a strip of categories. --}}
@php($plainListing = empty($filters['q'] ?? null) && collect(['category', 'brand', 'language', 'price_min', 'price_max', 'in_stock', 'deals'])->every(fn ($k) => empty($filters[$k] ?? null)))
@if($home && ! $heading)
<section class="bg-brandBeige-50 pt-4 md:pt-6" data-testid="store-head">
    <div class="container mx-auto px-4">
        <div class="grid gap-3 lg:grid-cols-3 lg:gap-4">
            @if(count($home['hero']) > 0)
                {{-- B7 (§7): the office's hero slides take the big card. --}}
                <h1 class="sr-only" data-testid="shop-heading">{{ $pageTitle }}</h1>
                <div class="shop-hero relative overflow-hidden rounded-2xl bg-brandMaroon-900 text-white lg:col-span-2" data-testid="shop-hero">
                    <div class="shop-scroll flex h-full snap-x snap-mandatory overflow-x-auto">
                        @foreach($home['hero'] as $slide)
                            <div class="relative flex min-h-[15rem] w-full shrink-0 snap-start flex-col justify-end lg:min-h-[20rem]" data-testid="hero-slide">
                                @if($slide['image'])<img src="{{ $slide['image'] }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60" loading="{{ $loop->first ? 'eager' : 'lazy' }}">@endif
                                <div class="relative p-6 sm:p-10">
                                    <h2 class="text-3xl font-bold leading-tight md:text-4xl" dir="auto">{{ $slide['heading'] }}</h2>
                                    @if($slide['subheading'])<p class="mt-2 max-w-2xl text-lg text-white/90" dir="auto">{{ $slide['subheading'] }}</p>@endif
                                    @if($slide['url'])<a href="{{ $slide['url'] }}" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brandGold-500 px-6 py-3 font-semibold text-brandMaroon-900 hover:bg-brandGold-400" data-testid="hero-link">{{ __('shop.shop_now') }} <span class="rtl-flip" aria-hidden="true">→</span></a>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="relative flex min-h-[15rem] flex-col justify-end overflow-hidden rounded-2xl bg-brandMaroon-800 p-6 text-white sm:p-10 lg:col-span-2 lg:min-h-[20rem]" data-testid="store-hero">
                    <svg class="absolute -bottom-2 end-6 hidden h-56 w-56 text-brandGold-500 sm:block" viewBox="0 0 200 200" aria-hidden="true">
                        <rect x="30" y="120" width="140" height="22" rx="4" fill="currentColor"/>
                        <rect x="42" y="96" width="118" height="22" rx="4" fill="#E9DCC9"/>
                        <rect x="36" y="72" width="128" height="22" rx="4" fill="currentColor" opacity=".75"/>
                        <path d="M100 30c-14 0-28 6-40 14v22c12-8 26-14 40-14s28 6 40 14V44c-12-8-26-14-40-14z" fill="#E9DCC9"/>
                        <rect x="20" y="146" width="160" height="10" rx="5" fill="#E9DCC9" opacity=".6"/>
                    </svg>
                    <div class="relative max-w-lg">
                        <p class="mb-3 inline-block rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">{{ __('shop.hero_badge') }}</p>
                        <h1 class="text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl" data-testid="shop-heading">{{ $pageTitle }}</h1>
                        <p class="mt-3 text-white/85 sm:text-lg">{{ __('shop.shop_intro') }}</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="#shop-grid" class="inline-flex items-center gap-2 rounded-lg bg-brandGold-500 px-6 py-3 font-semibold text-brandMaroon-900 hover:bg-brandGold-400" data-testid="store-hero-shop">{{ __('shop.shop_now') }} <span class="rtl-flip" aria-hidden="true">→</span></a>
                            <a href="#categories" class="inline-flex items-center rounded-lg border border-white/30 bg-white/10 px-6 py-3 font-semibold hover:bg-white/20">{{ __('shop.browse_categories') }}</a>
                        </div>
                    </div>
                </div>
            @endif
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-1 lg:gap-4">
                <a href="{{ route('public.shop.deals') }}" class="group flex min-h-[8rem] flex-col justify-between rounded-2xl bg-brandGold-500 p-5 text-brandMaroon-900 hover:bg-brandGold-400" data-testid="tile-deals">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12V4h8l10 10-8 8L3 12zm5-4.5a1 1 0 100 2 1 1 0 000-2z"/></svg>
                    <span>
                        <span class="block text-xl font-bold lg:text-2xl">{{ __('shop.deals_heading') }}</span>
                        <span class="text-sm opacity-80">{{ __('shop.tile_deals_sub') }} <span class="rtl-flip" aria-hidden="true">›</span></span>
                    </span>
                </a>
                <a href="#book-lists" class="group flex min-h-[8rem] flex-col justify-between rounded-2xl bg-brandMaroon-900 p-5 text-white hover:bg-brandMaroon-800" data-testid="tile-book-lists">
                    <svg class="h-7 w-7 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11M9 8h6"/></svg>
                    <span>
                        <span class="block text-xl font-bold lg:text-2xl">{{ __('shop.book_lists_heading') }}</span>
                        <span class="text-sm text-white/80">{{ __('shop.tile_book_lists_sub') }} <span class="rtl-flip" aria-hidden="true">›</span></span>
                    </span>
                </a>
            </div>
        </div>
    </div>
</section>
@stack('shop_links')
@elseif($vendor)
{{-- A shop's page (no storefront): its card, as iruali's seller head. --}}
<section class="bg-brandBeige-50 pt-4 md:pt-6">
    <div class="container mx-auto px-4">
        <nav class="mb-3 text-sm text-gray-500" aria-label="{{ __('shop.breadcrumb') }}">
            <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
            <span class="rtl-flip" aria-hidden="true">›</span>
            @if($collection)
                <a href="{{ route('public.shop.vendor', $vendor['slug']) }}" class="hover:text-brandMaroon-600">{{ $vendor['name'] }}</a>
                <span class="rtl-flip" aria-hidden="true">›</span>
            @endif
            <span class="text-gray-700">{{ $heading }}</span>
        </nav>
        <div class="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 lg:p-6" data-testid="shop-head">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-brandMaroon-700 text-2xl font-bold text-white lg:h-16 lg:w-16" aria-hidden="true">{{ mb_strtoupper(mb_substr($vendor['name'], 0, 1)) }}</span>
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-brandMaroon-900 lg:text-3xl" data-testid="{{ $collection ? 'collection-title' : 'shop-heading' }}" dir="auto">{{ $pageTitle }}</h1>
                @if($collection)
                    @if($collection['description'])<p class="mt-1 text-brandGray-700" dir="auto">{{ $collection['description'] }}</p>@endif
                @elseif($vendor['tagline'])
                    <p class="mt-0.5 line-clamp-2 text-brandGray-700" dir="auto">{{ $vendor['tagline'] }}</p>
                @endif
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600">
                    <span class="inline-flex items-center gap-1 font-medium text-green-700" data-testid="at-akuru">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3zm-3 9l2 2 4-4"/></svg>
                        {{ $collection ? $vendor['name'].' · ' : '' }}{{ __('shop.at_akuru') }}
                    </span>
                    @if($plainListing && ! $collection)<span data-testid="shop-head-count">{{ __('shop.result_count', ['count' => $products->total()]) }}</span>@endif
                </p>
                @if(! $collection && ($vendor['holiday'] ?? null))
                    <div class="mt-3 inline-block rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900" data-testid="holiday-notice">
                        <p class="font-semibold">{{ __('shop.back_on', ['date' => $vendor['holiday']['back_on']]) }}</p>
                        @if($vendor['holiday']['notice'])<p dir="auto">{{ $vendor['holiday']['notice'] }}</p>@endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@stack('shop_links')
@else
<section class="bg-brandBeige-50 pt-6 pb-2">
    <div class="container mx-auto px-4">
        @if($heading)
            <nav class="mb-2 text-sm text-gray-500" aria-label="{{ __('shop.breadcrumb') }}">
                <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
                <span class="rtl-flip" aria-hidden="true">›</span>
                <span class="text-gray-700">{{ $heading }}</span>
            </nav>
        @endif
        <h1 class="text-2xl font-bold text-brandMaroon-900 md:text-3xl" data-testid="shop-heading" dir="auto">{{ $pageTitle }}</h1>
        @unless($heading)<p class="mt-1 text-brandGray-700">{{ __('shop.shop_intro') }}</p>@endunless
    </div>
</section>
@stack('shop_links')
@endif
{{-- The categories as one strip of chips, off the store's front (where they are tiles). --}}
@unless($home && ! $heading)
    @php($chips = app(\App\Domains\Bookshop\Actions\Shop\PresentShopBarAction::class)->categoryLinks($vendor['slug'] ?? null))
    @if(count($chips) > 0 && ! ($compare ?? null))
        @php($currentChip = $vendor ? request('category') : (request()->routeIs('public.shop.category') ? request()->route('slug') : null))
        <div class="bg-brandBeige-50">
            <nav class="shop-scroll container mx-auto flex gap-2 overflow-x-auto px-4 pb-4 text-sm" aria-label="{{ __('shop.bar_categories') }}" data-testid="category-chips">
                <a href="{{ $vendor ? route('public.shop.vendor', $vendor['slug']) : route('public.shop.index') }}" class="shop-chip {{ $currentChip ? '' : 'is-active' }}">{{ __('shop.all_categories') }}</a>
                @foreach($chips as $chip)
                    <a href="{{ $chip['url'] }}" class="shop-chip {{ $currentChip === $chip['slug'] ? 'is-active' : '' }}" dir="auto" data-chip="{{ $chip['slug'] }}">{{ $chip['name'] }}</a>
                @endforeach
            </nav>
        </div>
    @endif
@endunless
@endif

{{-- STATUS §5lc: a school's book list — what it asks for, what it comes to, and the whole list in one tap. --}}
@if($collection['book_list'] ?? null)
@php($bookList = $collection['book_list'])
<section class="container mx-auto px-4 pt-6" data-testid="book-list">
    <div class="rounded-lg border bg-white p-4 text-gray-900">
        <p class="text-sm font-semibold text-brandMaroon-700" dir="auto">{{ __('shop.book_list_badge') }} · {{ $bookList['school'] }} · {{ $bookList['grade'] }}</p>
        <table class="mt-3 w-full text-sm">
            <thead class="sr-only"><tr><th>{{ __('shop.quantity') }}</th><th>{{ __('shop.product') }}</th><th>{{ __('shop.price') }}</th></tr></thead>
            <tbody class="divide-y">
                @foreach($bookList['lines'] as $line)
                    <tr class="{{ $line['buyable'] ? '' : 'text-gray-400' }}" data-book-line="{{ $line['slug'] }}">
                        <td class="w-12 py-1.5 pe-2 font-semibold">{{ $line['quantity'] }} ×</td>
                        <td class="py-1.5" dir="auto"><a href="{{ route('public.shop.product', $line['slug']) }}" class="hover:underline">{{ $line['title'] }}</a>@unless($line['buyable']) <span class="text-xs">({{ __('shop.book_list_unavailable') }})</span>@endunless</td>
                        <td class="py-1.5 text-end whitespace-nowrap">{{ $bookList['currency'] }} {{ $line['line_total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t pt-3">
            <p class="font-semibold" data-testid="book-list-total">{{ __('shop.book_list_total', ['count' => $bookList['buyable']]) }} {{ $bookList['currency'] }} {{ $bookList['total'] }}</p>
            @if($bookList['buyable'] > 0)
                <form method="POST" action="{{ route('public.shop.book-list.add', [$vendor['slug'], $collection['slug']]) }}">
                    @csrf
                    <button type="submit" class="btn-primary" data-testid="book-list-add">{{ __('shop.book_list_add_all') }}</button>
                </form>
            @endif
        </div>
    </div>
</section>
@endif

@if(! ($compare ?? null))
{{-- On a phone the search stays in view and the rest folds under "Filter and sort",
     so the products are on the first screen (the owner's screenshot, STATUS §5kv). The
     fold is served open, so the filters are there without script; the script folds
     it on a phone unless a filter is already in use. --}}
@php($activeFilters = collect(['category', 'brand', 'language', 'price_min', 'price_max', 'in_stock'])
    ->filter(fn ($key) => ! empty($filters[$key]) && ! (request()->routeIs('public.shop.category') && $key === 'category') && ! (request()->routeIs('public.shop.brand') && $key === 'brand'))
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
            {{-- §5lu: on the store's front the filters fold at every width, so the shelves come first. --}}
            <details class="shop-more mt-3{{ $home ? ' shop-more-front' : '' }}" open data-active="{{ $activeFilters }}" data-testid="shop-more">
                <summary class="shop-more-toggle {{ $home ? '' : 'md:hidden' }}" data-testid="shop-more-toggle">
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
                    {{-- §5lh: by brand, when the office has named any. --}}
                    @if(count($options['brands'] ?? []) > 0 && ! request()->routeIs('public.shop.brand'))
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">{{ __('shop.brand') }}</label>
                            <select name="brand" class="form-input pe-9" data-testid="filter-brand">
                                <option value="">{{ __('shop.all_brands') }}</option>
                                @foreach($options['brands'] as $brand)
                                    <option value="{{ $brand['slug'] }}" @selected(($filters['brand'] ?? '') === $brand['slug'])>{{ $brand['name'] }}</option>
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
                            {{-- §5lb: Deals comes ending soonest first unless the visitor sorts. --}}
                            @if(! empty($filters['deals']))<option value="" @selected(! isset($filters['sort']))>{{ __('shop.sort_ending_soon') }}</option>@endif
                            @foreach($options['sorts'] as $sort)
                                <option value="{{ $sort }}" @selected(($filters['sort'] ?? (empty($filters['deals']) ? 'newest' : '')) === $sort)>{{ __('shop.sort_'.$sort) }}</option>
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
    /* STATUS §5lu: rows that swipe on a phone (iruali's product rows), and the strips of links and chips. */
    .shop-scroll { scrollbar-width: none; }
    .shop-scroll::-webkit-scrollbar { display: none; }
    .shop-row { display: flex; gap: .75rem; overflow-x: auto; scroll-snap-type: x mandatory; margin-inline: -1rem; padding: 0 1rem .5rem; }
    .shop-row-item { flex: 0 0 46%; scroll-snap-align: start; }
    @media (min-width: 640px) { .shop-row-item { flex-basis: 31%; } }
    @media (min-width: 768px) { .shop-row { margin-inline: 0; padding-inline: 0; } .shop-row-item { flex-basis: calc((100% - 2.25rem) / 4); } }
    @media (min-width: 1024px) { .shop-row { gap: 1rem; } .shop-row-item { flex-basis: calc((100% - 4rem) / 5); } }
    .shop-chip { flex-shrink: 0; white-space: nowrap; border-radius: 999px; border: 1px solid #E5E7EB; background: #fff; padding: .45rem 1rem; font-weight: 500; color: #3F3A36; }
    .shop-chip:hover { border-color: #7C2D37; color: #7C2D37; }
    .shop-chip.is-active { border-color: #7C2D37; background: #7C2D37; color: #fff; }
    .shop-more > summary { list-style: none; }
    .shop-more > summary::-webkit-details-marker { display: none; }
    .shop-more-toggle { display: inline-flex; align-items: center; gap: .5rem; min-height: 44px; padding: 0 1rem; border: 1px solid #DCCFBE; border-radius: .6rem; background: #fff; font-size: .875rem; font-weight: 600; color: #3F3A36; cursor: pointer; }
    .shop-more[open] > .shop-more-toggle { border-color: #7C2D37; color: #7C2D37; }
    .shop-more-count { display: inline-flex; align-items: center; justify-content: center; min-width: 1.25rem; height: 1.25rem; padding: 0 .3rem; border-radius: 999px; background: #7C2D37; color: #fff; font-size: .75rem; }
    @media (min-width: 768px) { .shop-more:not(.shop-more-front) > summary { display: none; } .shop-more-front[open] > div { margin-top: .75rem; } }
</style>
@endpush
@push('scripts')
<script>
(() => {
    // §5lu: the chosen category chip in view on a phone, without moving the page.
    const strip = document.querySelector('[data-testid="category-chips"]');
    const chip = strip?.querySelector('.is-active');
    if (strip && chip && strip.scrollWidth > strip.clientWidth) {
        const s = strip.getBoundingClientRect(), c = chip.getBoundingClientRect();
        strip.scrollLeft += (c.left + c.width / 2) - (s.left + s.width / 2);
    }
    const more = document.querySelector('[data-testid="shop-more"]');
    if (more && (more.classList.contains('shop-more-front') || !window.matchMedia('(min-width: 768px)').matches) && more.dataset.active === '0') {
        more.open = false;
    }
})();
</script>
@endpush
@endif

@if($home)
    {{-- B7 (§7): featured products and collections; best sellers; recently viewed (the hero slides are in the head card, §5lu). --}}
    {{-- §5lb: the deals ending soonest, before the featured shelf, with a way to all of them. --}}
    @php($seeAll = ['deals' => route('public.shop.deals'), 'best_sellers' => route('public.shop.index', ['sort' => 'best_selling']).'#shop-grid', 'new_arrivals' => route('public.shop.index', ['sort' => 'newest']).'#shop-grid'])
    @foreach([['deals', 'deals_heading', 'shop-deals'], ['featured', 'featured_heading', 'shop-featured'], ['best_sellers', 'best_sellers', 'shop-best-sellers'], ['recently_viewed', 'recently_viewed', 'shop-recently-viewed']] as [$key, $label, $testid])
        @if(count($home[$key] ?? []) > 0)
            <section class="pt-8" data-testid="{{ $testid }}">
                <div class="container mx-auto px-4">
                    <div class="mb-3 flex items-end justify-between gap-3">
                        <h2 class="text-xl font-bold text-brandMaroon-900 lg:text-2xl">{{ __('shop.'.$label) }}</h2>
                        @if(isset($seeAll[$key]))<a href="{{ $seeAll[$key] }}" class="shrink-0 whitespace-nowrap text-sm font-semibold text-brandMaroon-700 hover:underline" @if($key === 'deals') data-testid="shop-deals-all" @endif>{{ $key === 'deals' ? __('shop.all_deals') : __('shop.see_all') }} <span class="rtl-flip" aria-hidden="true">›</span></a>@endif
                    </div>
                    <div class="shop-row shop-scroll">
                        @foreach(array_slice($home[$key], 0, $key === 'featured' ? 12 : 10) as $card)
                            <div class="shop-row-item">@include('public.shop._card', ['card' => $card])</div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endforeach

    @foreach($home['collections'] as $collection)
        <section class="pt-8" data-testid="shop-collection">
            <div class="container mx-auto px-4">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <h2 class="text-xl font-bold text-brandMaroon-900 lg:text-2xl" dir="auto">{{ $collection['name'] }} <span class="text-sm font-normal text-gray-500">· {{ $collection['vendor'] }}</span></h2>
                    <a href="{{ $collection['url'] }}" class="text-sm text-brandMaroon-700 underline">{{ __('shop.see_all') }} <span class="rtl-flip" aria-hidden="true">→</span></a>
                </div>
                <div class="shop-row shop-scroll">
                    @foreach($collection['cards'] as $card)
                        <div class="shop-row-item">@include('public.shop._card', ['card' => $card])</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    @if(count($home['categories']) > 0)
        @php($categoryIcon = fn (string $slug) => match (true) {
            (bool) preg_match('/toy|game|puzzle|play/', $slug) => 'M10 4a2 2 0 114 0v2h4v4h-2a2 2 0 100 4h2v4h-4v-2a2 2 0 10-4 0v2H6v-4h2a2 2 0 100-4H6V6h4V4z',
            (bool) preg_match('/station|pen|pencil|art|craft|write/', $slug) => 'M4 20l4-1L19 8l-3-3L5 16l-1 4zM14 7l3 3',
            (bool) preg_match('/quran|islam|dua|prayer|arab/', $slug) => 'M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z',
            (bool) preg_match('/work|activity|exercise/', $slug) => 'M9 4h6l1 2h3v14H5V6h3l1-2zm0 9l2 2 4-4',
            default => 'M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11',
        })
        <section id="categories" class="scroll-mt-24 pt-8" data-testid="shop-categories">
            <div class="container mx-auto px-4">
                <h2 class="mb-3 text-xl font-bold text-brandMaroon-900 lg:text-2xl">{{ __('shop.shop_by_category') }}</h2>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6 lg:gap-3">
                    @foreach($home['categories'] as $category)
                        <a href="{{ route('public.shop.category', $category['slug']) }}" class="group flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-white p-3 text-center hover:border-brandMaroon-300 hover:shadow-sm lg:p-4">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brandBeige-100 text-brandMaroon-700 transition group-hover:bg-brandMaroon-700 group-hover:text-white lg:h-14 lg:w-14" aria-hidden="true">
                                <svg class="h-5 w-5 lg:h-7 lg:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $categoryIcon($category['slug']) }}"/></svg>
                            </span>
                            <span class="text-xs font-semibold leading-tight text-brandMaroon-900 sm:text-sm" dir="auto">{{ $category['name'] }}</span>
                            <span class="text-[11px] text-gray-500">{{ __('shop.result_count', ['count' => $category['count']]) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- STATUS §5lh: the brands with something for sale. --}}
    @if(count($home['brands'] ?? []) > 0)
        <section class="pt-8" data-testid="shop-brands">
            <div class="container mx-auto px-4">
                <h2 class="mb-3 text-xl font-bold text-brandMaroon-900 lg:text-2xl">{{ __('shop.shop_by_brand') }}</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($home['brands'] as $brand)
                        <a href="{{ route('public.shop.brand', $brand['slug']) }}" class="rounded-full border bg-white px-4 py-2 text-sm hover:border-brandMaroon-400" data-brand="{{ $brand['slug'] }}">{{ $brand['name'] }} <span class="text-gray-500">({{ $brand['count'] }})</span></a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- STATUS §5lc: the schools' book lists — find your school and grade, buy the list. --}}
    {{-- Shown even when empty: the Bookstore menu links here. --}}
    @if(isset($home['book_lists']))
        <section id="book-lists" class="scroll-mt-24 mt-8 bg-brandBeige-50 py-8" data-testid="shop-book-lists">
            <div class="container mx-auto px-4">
                <h2 class="mb-1 text-xl font-bold text-brandMaroon-900 lg:text-2xl">{{ __('shop.book_lists_heading') }}</h2>
                <p class="mb-3 text-sm text-gray-600">{{ count($home['book_lists']) > 0 ? __('shop.book_lists_intro') : __('shop.book_lists_none') }}</p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($home['book_lists'] as $list)
                        <a href="{{ $list['url'] }}" class="rounded-xl border border-gray-200 bg-white p-4 hover:border-brandMaroon-300 hover:shadow-sm" data-book-list="{{ $list['vendor_slug'] }}/{{ $list['slug'] }}">
                            <span class="block font-semibold text-brandMaroon-900" dir="auto">{{ $list['school'] }}</span>
                            <span class="block text-sm" dir="auto">{{ $list['grade'] }} · {{ $list['name'] }}</span>
                            <span class="block text-xs text-gray-500">{{ __('shop.sold_by') }} {{ $list['vendor'] }} · {{ __('shop.result_count', ['count' => $list['count']]) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(count($home['new_arrivals']) > 0)
        <section class="pt-8" data-testid="new-arrivals">
            <div class="container mx-auto px-4">
                <div class="mb-3 flex items-end justify-between gap-3">
                    <h2 class="text-xl font-bold text-brandMaroon-900 lg:text-2xl">{{ __('shop.new_arrivals') }}</h2>
                    <a href="{{ $seeAll['new_arrivals'] }}" class="shrink-0 whitespace-nowrap text-sm font-semibold text-brandMaroon-700 hover:underline">{{ __('shop.see_all') }} <span class="rtl-flip" aria-hidden="true">›</span></a>
                </div>
                <div class="shop-row shop-scroll">
                    @foreach($home['new_arrivals'] as $card)
                        <div class="shop-row-item">@include('public.shop._card', ['card' => $card])</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(count($home['vendors']) > 0)
        <section id="shops" class="scroll-mt-24 pt-8" data-testid="shop-vendors">
            <div class="container mx-auto px-4">
                <h2 class="mb-3 text-xl font-bold text-brandMaroon-900 lg:text-2xl">{{ __('shop.our_shops') }}</h2>
                <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                    @foreach($home['vendors'] as $shop)
                        <a href="{{ route('public.shop.vendor', $shop['slug']) }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 hover:border-brandMaroon-300 hover:shadow-sm lg:p-4" data-vendor="{{ $shop['slug'] }}">
                            @if($shop['logo'])
                                <img src="{{ $shop['logo'] }}" alt="" class="h-14 w-14 shrink-0 rounded-lg object-cover" loading="lazy" data-testid="shop-vendor-logo">
                            @else
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-brandMaroon-700 text-xl font-bold text-white" aria-hidden="true">{{ mb_substr($shop['display_name'], 0, 1) }}</span>
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-brandMaroon-900 group-hover:text-brandMaroon-600" dir="auto">{{ $shop['display_name'] }}</span>
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
        <section class="pt-8" data-testid="open-a-shop">
            <div class="container mx-auto px-4">
                <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-6 lg:flex-row lg:items-center lg:p-8">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brandGold-100 text-brandGold-800" aria-hidden="true">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 10l1.5-5h13L20 10M4 10v10h16V10M4 10h16M9 20v-5h6v5"/></svg>
                    </span>
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-brandMaroon-900">{{ __('shop.sell_here_heading') }}</h2>
                        <p class="mt-1 text-gray-600">{{ __('shop.sell_here_body') }}</p>
                    </div>
                    <a href="{{ route('vendor.apply') }}" class="btn-primary self-start lg:self-auto" data-testid="open-a-shop-link">{{ __('shop.apply_title') }}</a>
                </div>
            </div>
        </section>
    @endif
@endif

@if($compare ?? null)
{{-- STATUS §5li: the products this device is comparing, one column each. --}}
<section class="py-8" data-testid="compare">
    <div class="container mx-auto px-4">
        @if(count($compare['columns']) === 0)
            <p class="rounded-lg border bg-white p-6 text-gray-600" data-testid="compare-empty">{{ __('shop.compare_empty') }}</p>
        @else
            <div class="overflow-x-auto rounded-lg border bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="sticky start-0 z-10 w-28 bg-white p-3 sm:w-40"></th>
                            @foreach($compare['columns'] as $col)
                                <th class="min-w-[9rem] p-3 text-start align-top font-normal" data-compare-product="{{ $col['slug'] }}">
                                    <a href="{{ route('public.shop.product', $col['slug']) }}" class="block">
                                        @if($col['image'])<img src="{{ $col['image'] }}" alt="{{ $col['image_alt'] }}" class="mb-2 aspect-square w-full max-w-[10rem] rounded object-cover" loading="lazy">@endif
                                        <span class="font-semibold text-brandMaroon-900 hover:underline" dir="auto">{{ $col['title'] }}</span>
                                    </a>
                                    <form method="POST" action="{{ route('public.shop.compare.toggle', $col['slug']) }}" class="mt-1">
                                        @csrf
                                        <button type="submit" class="text-xs text-red-700 underline" data-testid="compare-remove-{{ $col['slug'] }}">{{ __('shop.remove') }}</button>
                                    </form>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($compare['rows'] as $row)
                            <tr data-compare-row="{{ $row['key'] }}">
                                <th class="sticky start-0 z-10 w-28 bg-gray-50 p-3 text-start font-medium text-gray-600 sm:w-40">{{ __('shop.'.$row['key']) }}</th>
                                @foreach($row['values'] as $value)
                                    <td class="p-3" dir="auto">{{ $value ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr>
                            <th class="sticky start-0 z-10 bg-white p-3"></th>
                            @foreach($compare['columns'] as $col)
                                <td class="p-3">
                                    @if($col['available'] && ! $col['has_options'])
                                        <form method="POST" action="{{ route('public.shop.cart.add') }}">
                                            @csrf
                                            <input type="hidden" name="product" value="{{ $col['slug'] }}">
                                            <button type="submit" class="btn-primary text-sm" data-testid="compare-add-{{ $col['slug'] }}">{{ __('shop.add_to_cart') }}</button>
                                        </form>
                                    @else
                                        <a href="{{ route('public.shop.product', $col['slug']) }}" class="text-sm text-brandMaroon-700 underline">{{ __('shop.view_product') }}</a>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@else
<section id="shop-grid" class="scroll-mt-24 py-8">
    <div class="container mx-auto px-4">
        <h2 class="mb-3 text-xl font-bold text-brandMaroon-900 lg:text-2xl">
            {{ $home || ($storefront && ! $collection && count($storefront['sections']) > 0) || ($vendor && ! $collection && ! $storefront && ($plainListing ?? false)) ? __('shop.all_products') : __('shop.results') }}
            <span class="text-sm font-normal text-gray-500" data-testid="result-count">{{ __('shop.result_count', ['count' => $products->total()]) }}</span>
        </h2>
        @if($products->total() === 0)
            {{-- A shop with nothing listed yet says so, rather than blaming the search. --}}
            <p class="text-gray-500" data-testid="shop-empty">{{ $vendor && ! $collection && $activeFilters === 0 && empty($filters['q']) ? __('shop.shop_nothing_listed') : (! empty($filters['deals']) && $activeFilters === 0 && empty($filters['q']) ? __('shop.no_deals_now') : __('shop.no_results')) }}</p>
        @endif
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4" data-testid="shop-grid">
            @foreach($products as $card)
                @include('public.shop._card', ['card' => $card])
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    </div>
</section>
@endif
@if($storefront)
</div>
@endif

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
