@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN B1b: the shop home, a category, a search, and a vendor's
     plain page — one catalogue in one view. --}}
@php($pageTitle = $heading ?? __('shop.bookshop_title'))
@section('title', $pageTitle . ' - ' . config('app.name'))
@section('description', $vendor['tagline'] ?? __('shop.shop_intro'))

@section('content')
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-10">
    <div class="container mx-auto px-4">
        @if($vendor || $heading)
            <nav class="mb-3 text-sm text-gray-500">
                <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
                <span>›</span>
                <span class="text-gray-700">{{ $heading }}</span>
            </nav>
        @endif
        <h1 class="text-3xl md:text-4xl font-bold text-brandMaroon-900" data-testid="shop-heading">{{ $pageTitle }}</h1>
        @if($vendor)
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

<section class="border-b bg-white py-4">
    <div class="container mx-auto px-4">
        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-end gap-3" data-testid="shop-filters">
            <div class="min-w-48 flex-1">
                <label for="shop-search" class="mb-1 block text-xs text-gray-500">{{ __('shop.search') }}</label>
                <input id="shop-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input w-full" placeholder="{{ __('shop.search_shop') }}">
            </div>
            @if(! request()->routeIs('public.shop.category'))
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ __('shop.category') }}</label>
                    <select name="category" class="form-input" data-testid="filter-category">
                        <option value="">{{ __('shop.all_categories') }}</option>
                        @foreach($options['categories'] as $category)
                            <option value="{{ $category['slug'] }}" @selected(($filters['category'] ?? '') === $category['slug'])>{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ __('shop.language') }}</label>
                <select name="language" class="form-input">
                    <option value="">{{ __('shop.any_language') }}</option>
                    @foreach(['English' => 'lang_english', 'Dhivehi' => 'lang_dhivehi', 'Arabic' => 'lang_arabic'] as $value => $key)
                        <option value="{{ $value }}" @selected(($filters['language'] ?? '') === $value)>{{ __('shop.'.$key) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ __('shop.price') }}</label>
                <div class="flex gap-1">
                    <input type="number" name="price_min" min="0" step="1" value="{{ $filters['price_min'] ?? '' }}" class="form-input w-24" placeholder="{{ __('shop.price_from') }}" aria-label="{{ __('shop.price_from') }}">
                    <input type="number" name="price_max" min="0" step="1" value="{{ $filters['price_max'] ?? '' }}" class="form-input w-24" placeholder="{{ __('shop.price_to') }}" aria-label="{{ __('shop.price_to') }}">
                </div>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm">
                <input type="checkbox" name="in_stock" value="1" @checked(! empty($filters['in_stock']))> {{ __('shop.in_stock_only') }}
            </label>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ __('shop.sort') }}</label>
                <select name="sort" class="form-input" data-testid="filter-sort">
                    @foreach($options['sorts'] as $sort)
                        <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'newest') === $sort)>{{ __('shop.sort_'.$sort) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary">{{ __('shop.filter') }}</button>
            <a href="{{ url()->current() }}" class="btn-secondary">{{ __('shop.clear_filters') }}</a>
            <a href="{{ route('public.shop.export', $filters) }}" class="btn-secondary">{{ __('shop.export_csv') }}</a>
        </form>
    </div>
</section>

@if($home)
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
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach($home['vendors'] as $shop)
                        <a href="{{ route('public.shop.vendor', $shop['slug']) }}" class="rounded-lg border bg-white p-4 hover:shadow-sm" data-vendor="{{ $shop['slug'] }}">
                            <span class="block font-semibold text-brandMaroon-900">{{ $shop['name'] }}</span>
                            @if($shop['tagline'])<span class="block text-sm text-gray-600">{{ $shop['tagline'] }}</span>@endif
                            <span class="mt-1 block text-xs text-gray-500">{{ __('shop.result_count', ['count' => $shop['count']]) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endif

<section class="py-8">
    <div class="container mx-auto px-4">
        <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">
            {{ $home ? __('shop.all_products') : __('shop.results') }}
            <span class="text-sm font-normal text-gray-500" data-testid="result-count">{{ __('shop.result_count', ['count' => $products->total()]) }}</span>
        </h2>
        @if($products->total() === 0)
            <p class="text-gray-500">{{ __('shop.no_results') }}</p>
        @endif
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4" data-testid="shop-grid">
            @foreach($products as $card)
                @include('public.shop._card', ['card' => $card])
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    </div>
</section>

@include('public.shop._bottom-bar')
@endsection
