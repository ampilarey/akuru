@extends('public.layouts.public')

{{-- LENDING_AND_USED_BOOKS_PLAN L1: the public shelf of books to borrow — from active lenders whose ID card
     the office has checked (D5). Shows the lender's chosen name and island only. No money (D4). --}}
@section('title', __('lending.shelf_title') . ' - ' . config('app.name'))
@section('description', __('lending.shelf_intro'))

@section('content')
<div class="container mx-auto max-w-6xl px-4 py-8" data-testid="lending-shelf">
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

    <form method="GET" action="{{ route('public.lending.index') }}" class="mb-6 grid gap-2 rounded-lg border bg-white p-3 sm:grid-cols-7" data-testid="lending-filters">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('lending.search_placeholder') }}" class="form-input sm:col-span-2" dir="auto" data-testid="lending-q">
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
        <div class="flex gap-2 sm:col-span-7">
            <button type="submit" class="btn-primary text-sm">{{ __('lending.apply_filters') }}</button>
            @if($filters !== [])<a href="{{ route('public.lending.index') }}" class="btn-secondary text-sm">{{ __('lending.clear_filters') }}</a>@endif
            <span class="ms-auto self-center text-sm text-gray-500" data-testid="lending-count">{{ trans_choice('lending.shelf_count', $shelf['total'], ['count' => $shelf['total']]) }}</span>
        </div>
    </form>

    @if(count($shelf['books']) === 0)
        <p class="rounded border bg-white p-6 text-gray-600" data-testid="lending-empty">{{ __('lending.shelf_empty') }}</p>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4" data-testid="lending-grid">
            @foreach($shelf['books'] as $book)
                @include('public.lending._card', ['book' => $book])
            @endforeach
        </div>
    @endif
</div>
@endsection
