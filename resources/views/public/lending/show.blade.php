@extends('public.layouts.public')

{{-- LENDING_AND_USED_BOOKS_PLAN L1: one book to borrow. The request form is for signed-in people; a visitor
     is sent to sign in. The lender's phone is never on this page — it is shared once they accept. --}}
@section('title', $book['title'] . ' - ' . __('lending.shelf_title'))
@section('description', \Illuminate\Support\Str::limit((string) ($book['description'] ?? __('lending.shelf_intro')), 160))
@if($book['photo_large'])
    @section('og_image', $book['photo_large'])
@endif

@section('content')
<div class="container mx-auto max-w-4xl px-4 py-8" data-testid="lending-book" data-slug="{{ $book['slug'] }}" data-status="{{ $book['status'] }}">
    <nav class="mb-3 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a> ›
        <a href="{{ route('public.lending.index') }}" class="hover:text-brandMaroon-600">{{ __('lending.shelf_title') }}</a>
    </nav>
    @if($errors->any())
        <div class="mb-4 rounded bg-red-50 p-3 text-sm text-red-800" data-testid="lending-errors">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif
    <div class="grid gap-6 md:grid-cols-5">
        <div class="md:col-span-2">
            <div class="aspect-square overflow-hidden rounded-xl border bg-brandBeige-50">
                @if($book['photo_large'])
                    <img src="{{ $book['photo_large'] }}" alt="{{ $book['title'] }}" class="h-full w-full object-cover">
                @else
                    <span class="flex h-full w-full items-center justify-center text-brandBeige-300" aria-hidden="true">
                        <svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11"/></svg>
                    </span>
                @endif
            </div>
        </div>
        <div class="md:col-span-3">
            <div class="mb-2 flex flex-wrap gap-1 text-[11px] font-semibold text-white">
                <span class="rounded px-1.5 py-0.5 {{ $book['status'] === 'on_loan' ? 'bg-gray-600' : 'bg-green-700' }}" data-testid="book-status">{{ $book['status_label'] }}</span>
                <span class="rounded bg-amber-700 px-1.5 py-0.5">{{ __('lending.condition_label') }}: {{ $book['condition_label'] }}</span>
                @if($book['lender']['id_required'])<span class="rounded bg-brandMaroon-700 px-1.5 py-0.5" data-testid="id-required">{{ __('lending.id_required_badge') }}</span>@endif
            </div>
            <h1 class="text-2xl font-bold text-brandMaroon-900" dir="auto" data-testid="book-title">{{ $book['title'] }}</h1>
            @if($book['author'])<p class="text-gray-700" dir="auto">{{ $book['author'] }}</p>@endif
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                @foreach(['grade' => 'filter_grade', 'subject' => 'filter_subject', 'language' => 'filter_language'] as $key => $label)
                    @if($book[$key])<dt class="text-gray-500">{{ __('lending.'.$label) }}</dt><dd dir="auto">{{ $book[$key] }}</dd>@endif
                @endforeach
                <dt class="text-gray-500">{{ __('lending.book_max_days') }}</dt><dd>{{ $book['max_days'] }}</dd>
                <dt class="text-gray-500">{{ __('lending.deposit_label') }}</dt><dd dir="auto" data-testid="book-deposit">{{ $book['deposit'] ?: __('lending.deposit_none') }}</dd>
            </dl>
            @if($book['description'])<p class="mt-4 whitespace-pre-line text-sm text-gray-800" dir="auto">{{ $book['description'] }}</p>@endif

            <section class="mt-5 rounded-lg border bg-gray-50 p-4" data-testid="lender-card">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('lending.about_lender') }}</h2>
                <p class="font-medium" dir="auto">{{ $book['lender']['name'] }}@if($book['lender']['island']) <span class="font-normal text-gray-600">· {{ $book['lender']['island'] }}</span>@endif</p>
                @if($book['lender']['about'])<p class="mt-1 text-sm text-gray-700" dir="auto">{{ $book['lender']['about'] }}</p>@endif
                <p class="mt-1 text-xs text-gray-500">{{ __('lending.phone_after_accept') }}</p>
            </section>

            <section class="mt-5" data-testid="ask-section">
                @guest
                    <a href="{{ route('login') }}" class="btn-primary" data-testid="ask-sign-in">{{ __('lending.ask_sign_in') }}</a>
                @else
                    @if($book['status'] === 'on_loan')<p class="mb-2 text-sm text-gray-600">{{ __('lending.ask_on_loan') }}</p>@endif
                    <form method="POST" action="{{ route('public.lending.request', $book['slug']) }}" class="grid gap-2" data-testid="ask-form">
                        @csrf
                        <label class="text-sm">{{ __('lending.ask_message_label') }}
                            <textarea name="message" rows="2" maxlength="500" class="form-input w-full" dir="auto" data-testid="ask-message" placeholder="{{ __('lending.ask_message_hint') }}">{{ old('message') }}</textarea>
                        </label>
                        <div><button type="submit" class="btn-primary" data-testid="ask-submit">{{ __('lending.ask_to_borrow') }}</button></div>
                    </form>
                @endguest
            </section>
            <p class="mt-6 text-sm"><a href="{{ route('public.lending.index') }}" class="text-brandMaroon-700 underline">‹ {{ __('lending.back_to_shelf') }}</a></p>
        </div>
    </div>
</div>
@endsection
