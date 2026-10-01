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
        <a href="{{ route('public.lending.mine') }}" class="btn-primary" data-testid="lending-mine-link">{{ auth()->check() ? __('lending.my_lending') : __('lending.become_lender') }}</a>
    </div>

    <form method="GET" action="{{ route('public.lending.index') }}" class="mb-6 grid gap-2 rounded-lg border bg-white p-3 sm:grid-cols-6" data-testid="lending-filters">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('lending.search_placeholder') }}" class="form-input sm:col-span-2" dir="auto" data-testid="lending-q">
        @foreach(['grade', 'subject', 'language', 'island'] as $key)
            <select name="{{ $key }}" class="form-input text-sm" data-testid="lending-filter-{{ $key }}" aria-label="{{ __('lending.filter_'.$key) }}">
                <option value="">{{ __('lending.filter_'.$key) }}: {{ __('lending.filter_any') }}</option>
                @foreach($shelf['filters'][$key] as $choice)
                    <option value="{{ $choice }}" @selected(($filters[$key] ?? '') === $choice)>{{ $choice }}</option>
                @endforeach
            </select>
        @endforeach
        <div class="flex gap-2 sm:col-span-6">
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
                <a href="{{ $book['url'] }}" class="group flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-brandMaroon-300 hover:shadow-md" data-lending-book="{{ $book['slug'] }}" data-status="{{ $book['status'] }}">
                    <div class="relative aspect-square overflow-hidden bg-brandBeige-50">
                        @if($book['photo'])
                            <img src="{{ $book['photo'] }}" alt="{{ $book['title'] }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <span class="flex h-full w-full items-center justify-center text-brandBeige-300" aria-hidden="true">
                                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11"/></svg>
                            </span>
                        @endif
                        <span class="absolute top-2 start-2 flex flex-col items-start gap-1">
                            <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold text-white {{ $book['status'] === 'on_loan' ? 'bg-gray-600' : 'bg-green-700' }}" data-badge="status">{{ $book['status'] === 'on_loan' ? __('lending.on_loan_badge') : __('lending.available_badge') }}</span>
                            <span class="rounded bg-amber-700 px-1.5 py-0.5 text-[11px] font-semibold text-white" data-badge="condition">{{ $book['condition_label'] }}</span>
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col p-3">
                        <p class="truncate text-[11px] text-gray-500">{{ __('lending.lent_by') }} <span class="font-medium text-gray-700" dir="auto">{{ $book['lender']['name'] }}</span>@if($book['lender']['island']) · {{ $book['lender']['island'] }}@endif</p>
                        <h3 class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-brandMaroon-900 group-hover:underline" dir="auto">{{ $book['title'] }}</h3>
                        @if($book['author'])<p class="text-xs text-gray-600" dir="auto">{{ $book['author'] }}</p>@endif
                        <p class="mt-auto pt-2 text-xs text-gray-500">{{ __('lending.max_days', ['days' => $book['max_days']]) }}@if($book['grade']) · {{ __('lending.filter_grade') }} {{ $book['grade'] }}@endif</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
