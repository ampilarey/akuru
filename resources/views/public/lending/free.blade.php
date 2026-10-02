@extends('public.layouts.public')

{{-- LENDING_AND_USED_BOOKS_PLAN L5: the Free items page. What people give away now — Reserved once the giver
     has promised one to someone — and what was taken in the last few weeks, marked Taken, so the page shows
     the giving that happens. Only the giver's display name and island; never a phone or who took it. --}}
@section('title', __('lending.free_title') . ' - ' . config('app.name'))
@section('description', __('lending.free_intro'))

@section('content')
<div class="container mx-auto max-w-6xl px-4 py-8" data-testid="free-items">
    <nav class="mb-3 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a> ›
        <a href="{{ route('public.lending.index') }}" class="hover:text-brandMaroon-600">{{ __('lending.shelf_title') }}</a> ›
        <span>{{ __('lending.free_title') }}</span>
    </nav>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-brandMaroon-900" data-testid="free-heading">{{ __('lending.free_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-600">{{ __('lending.free_intro') }}</p>
        </div>
        <a href="{{ route('public.lending.mine', ['offer' => 'give']) }}#books" class="btn-primary" data-testid="free-give-cta">{{ __('lending.free_give_cta') }}</a>
    </div>

    <section class="mb-8" data-testid="free-available">
        <h2 class="mb-3 text-lg font-semibold text-gray-900">{{ __('lending.free_available_heading') }} <span class="text-sm font-normal text-gray-500">({{ count($free['available']) }})</span></h2>
        @if(count($free['available']) === 0)
            <p class="rounded border bg-white p-6 text-gray-600" data-testid="free-empty">{{ __('lending.free_empty') }}</p>
        @else
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach($free['available'] as $book)
                    @include('public.lending._card', ['book' => $book])
                @endforeach
            </div>
        @endif
    </section>

    @if(count($free['taken']) > 0)
        <section data-testid="free-taken">
            <h2 class="mb-1 text-lg font-semibold text-gray-900">{{ __('lending.free_taken_heading') }}</h2>
            <p class="mb-3 text-sm text-gray-500">{{ __('lending.free_taken_intro', ['days' => $free['taken_days']]) }}</p>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach($free['taken'] as $book)
                    @include('public.lending._card', ['book' => $book])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
