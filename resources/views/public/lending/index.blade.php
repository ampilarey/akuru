@extends('public.layouts.public')

{{-- LENDING_AND_USED_BOOKS_PLAN L1: the public shelf of books to borrow — from active lenders whose ID card
     the office has checked (D5). Shows the lender's chosen name and island only. No money (D4). --}}
@section('title', __('lending.shelf_title') . ' - ' . config('app.name'))
@section('description', __('lending.shelf_intro'))

@section('content')
<div class="container mx-auto max-w-6xl px-4 py-4 md:py-8" data-testid="lending-shelf">
    <nav class="mb-3 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a> ›
        <span>{{ __('lending.borrow_heading') }}</span>
    </nav>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-brandMaroon-900" data-testid="lending-heading">{{ __('lending.shelf_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-600">{{ __('lending.shelf_intro') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('public.lending.free') }}" class="btn-secondary" data-testid="lending-free-chip">{{ __('lending.shelf_free_chip') }}</a>
            <a href="{{ route('public.lending.mine') }}" class="btn-primary" data-testid="lending-mine-link">{{ auth()->check() ? __('lending.my_lending') : __('lending.become_lender') }}</a>
        </div>
    </div>

    {{-- Phone-first (docs/MOBILE_FIRST.md rule 3, MF2): the search stays in view and
         the five choices fold under "Filter and sort" on a phone, so the books start on
         the first screen. --}}
    @php($lendingFilters = collect(['offer', 'grade', 'subject', 'language', 'island'])->filter(fn ($key) => filled($filters[$key] ?? null))->count())
    <form method="GET" action="{{ route('public.lending.index') }}" class="mb-6 rounded-lg border bg-white p-3" data-testid="lending-filters">
        <div class="flex gap-2">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('lending.search_placeholder') }}" class="form-input min-w-0 flex-1" dir="auto" aria-label="{{ __('lending.search_placeholder') }}" data-testid="lending-q">
            <button type="submit" class="btn-primary text-sm">{{ __('lending.apply_filters') }}</button>
        </div>
        <details class="filter-fold mt-2" open data-filter-fold data-testid="lending-filters-fold">
            <summary class="filter-fold-toggle" data-testid="lending-filters-toggle">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12M10 20h4"/></svg>
                {{ __('shop.filter_and_sort') }}
                @if($lendingFilters > 0)<span class="filter-fold-count">{{ $lendingFilters }}</span>@endif
            </summary>
            <div class="filter-fold-body grid gap-2 sm:grid-cols-3 md:mt-2 md:grid-cols-5">
                <select name="offer" class="form-input text-sm" data-testid="lending-filter-offer" aria-label="{{ __('lending.filter_offer') }}">
                    <option value="">{{ __('lending.filter_offer') }}: {{ __('lending.filter_offer_any') }}</option>
                    <option value="lend" @selected(($filters['offer'] ?? '') === 'lend')>{{ __('lending.offer_lend') }}</option>
                    <option value="give" @selected(($filters['offer'] ?? '') === 'give')>{{ __('lending.offer_give') }}</option>
                </select>
                @foreach(['grade', 'subject', 'language', 'island'] as $key)
                    <select name="{{ $key }}" class="form-input text-sm" data-testid="lending-filter-{{ $key }}" aria-label="{{ __('lending.filter_'.$key) }}">
                        <option value="">{{ __('lending.filter_'.$key) }}: {{ __('lending.filter_any') }}</option>
                        @foreach($shelf['filters'][$key] as $choice)
                            <option value="{{ $choice }}" @selected(($filters[$key] ?? '') === $choice)>{{ $choice }}</option>
                        @endforeach
                    </select>
                @endforeach
            </div>
        </details>
        <div class="mt-2 flex items-center gap-2">
            @if($filters !== [])<a href="{{ route('public.lending.index') }}" class="btn-secondary text-sm">{{ __('lending.clear_filters') }}</a>@endif
            <span class="ms-auto text-sm text-gray-500" data-testid="lending-count">{{ trans_choice('lending.shelf_count', $shelf['total'], ['count' => $shelf['total']]) }}</span>
        </div>
    </form>

    @if(count($shelf['books']) === 0)
        <p class="rounded border bg-white p-6 text-gray-600" data-testid="lending-empty" data-phone-main>{{ __('lending.shelf_empty') }}</p>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4" data-testid="lending-grid" data-phone-main>
            @foreach($shelf['books'] as $book)
                @include('public.lending._card', ['book' => $book])
            @endforeach
        </div>
    @endif
</div>
@endsection
