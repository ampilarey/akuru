@extends('public.layouts.public')

{{-- LENDING_AND_USED_BOOKS_PLAN L1: My lending — me as a lender (and my ID card), the books I lend and the requests
     on them, the books I borrow. Four anchored sections so a notice can land on the right one. --}}
@section('title', __('lending.mine_title') . ' - ' . config('app.name'))

@php($lender = $status['lender'])
@php($idStatus = $status['id']['status'] ?? 'none')
@php($statusTone = ['requested' => 'bg-amber-100 text-amber-800', 'accepted' => 'bg-blue-100 text-blue-800', 'declined' => 'bg-red-100 text-red-800', 'cancelled' => 'bg-gray-100 text-gray-700', 'out' => 'bg-indigo-100 text-indigo-800', 'returned' => 'bg-green-100 text-green-800'])

@section('content')
<div class="container mx-auto max-w-5xl px-4 py-8" data-testid="my-lending">
    <nav class="mb-3 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a> ›
        <a href="{{ route('public.lending.index') }}" class="hover:text-brandMaroon-600">{{ __('lending.shelf_title') }}</a>
    </nav>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-brandMaroon-900">{{ __('lending.mine_title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('lending.mine_intro') }}</p>
        </div>
        <nav class="flex flex-wrap gap-2 text-sm" data-testid="mine-nav">
            @foreach(['lender' => 'lender_section', 'books' => 'books_section', 'lending' => 'lending_section', 'borrowing' => 'borrowing_section'] as $anchor => $label)
                <a href="#{{ $anchor }}" class="rounded-full border border-brandMaroon-200 bg-white px-3 py-1 text-brandMaroon-800 hover:bg-brandMaroon-50">{{ __('lending.'.$label) }}</a>
            @endforeach
        </nav>
    </div>
    @if(session('success'))
        <p class="mb-4 rounded bg-green-50 p-3 text-green-800" data-testid="flash-success">{{ session('success') }}</p>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded bg-red-50 p-3 text-sm text-red-800" data-testid="lending-errors">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    {{-- 1. Me as a lender --}}
    <section id="lender" class="mb-6 rounded-lg border bg-white p-4" data-testid="lender-section" data-registered="{{ $lender ? '1' : '0' }}">
        <h2 class="mb-1 text-lg font-semibold">{{ __('lending.lender_section') }}
            @if($lender)<span class="ms-2 rounded px-2 py-0.5 text-xs font-semibold {{ $lender['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}" data-testid="lender-status">{{ __('lending.lender_status_'.$lender['status']) }}</span>@endif
        </h2>
        @unless($lender)<p class="mb-3 text-sm text-gray-600">{{ __('lending.lender_register_intro') }}</p>@endunless
        <form method="POST" action="{{ route('public.lending.register') }}" class="grid gap-3 sm:grid-cols-2" data-testid="lender-form">
            @csrf
            <label class="text-sm">{{ __('lending.display_name') }}
                <input type="text" name="display_name" value="{{ old('display_name', $lender['display_name'] ?? '') }}" maxlength="120" required class="form-input w-full" dir="auto" data-testid="lender-name">
            </label>
            <label class="text-sm">{{ __('lending.island') }}
                <input type="text" name="island" value="{{ old('island', $lender['island'] ?? '') }}" maxlength="120" class="form-input w-full" dir="auto" data-testid="lender-island">
            </label>
            <label class="text-sm sm:col-span-2">{{ __('lending.about') }}
                <textarea name="about" rows="2" maxlength="1000" class="form-input w-full" dir="auto" data-testid="lender-about">{{ old('about', $lender['about'] ?? '') }}</textarea>
            </label>
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input type="hidden" name="id_required" value="0">
                <input type="checkbox" name="id_required" value="1" class="rounded" @checked(old('id_required', $lender['id_required'] ?? false)) data-testid="lender-id-required">
                {{ __('lending.id_required') }}
            </label>
            <div class="sm:col-span-2"><button type="submit" class="btn-primary" data-testid="lender-save">{{ $lender ? __('lending.lender_save') : __('lending.lender_register') }}</button></div>
        </form>

        @if($lender)
            <div class="mt-4 rounded-lg border p-3 {{ $idStatus === 'verified' ? 'border-green-200 bg-green-50' : 'border-amber-300 bg-amber-50' }}" data-testid="lender-identity" data-id-status="{{ $idStatus }}">
                <h3 class="font-semibold">{{ __('lending.id_section') }} <span class="ms-1 rounded bg-white px-2 py-0.5 text-xs font-semibold">{{ __('account.id_status_'.$idStatus) }}</span></h3>
                <p class="mt-1 text-sm text-gray-700">{{ $idStatus === 'verified' ? __('lending.id_checked_note') : ($idStatus === 'pending' ? __('account.id_pending_body') : __('account.id_lender_blurb')) }}</p>
                @if($idStatus === 'rejected' && ($status['id']['note'] ?? null))<p class="mt-1 text-sm text-red-800">{{ $status['id']['note'] }}</p>@endif
                @if(! in_array($idStatus, ['verified', 'pending'], true))
                    <form method="POST" action="{{ route('public.lending.identity') }}" enctype="multipart/form-data" class="mt-2 grid gap-2 sm:grid-cols-3" data-testid="identity-form">
                        @csrf
                        <label class="text-sm">{{ __('account.id_front') }}<input type="file" name="id_front" accept="image/jpeg,image/png,image/webp,application/pdf" required class="form-input w-full text-sm" data-testid="id-front"></label>
                        <label class="text-sm">{{ __('account.id_back') }}<input type="file" name="id_back" accept="image/jpeg,image/png,image/webp,application/pdf" required class="form-input w-full text-sm" data-testid="id-back"></label>
                        <div class="self-end"><button type="submit" class="btn-primary text-sm" data-testid="identity-submit">{{ __('account.id_submit') }}</button></div>
                        <p class="text-xs text-gray-500 sm:col-span-3">{{ __('account.id_hint') }}</p>
                    </form>
                @endif
            </div>
        @endif
    </section>

    {{-- 2. Books I lend --}}
    <section id="books" class="mb-6 rounded-lg border bg-white p-4" data-testid="books-section">
        <h2 class="mb-2 text-lg font-semibold">{{ __('lending.books_section') }}</h2>
        @unless($lender)
            <p class="text-sm text-gray-600">{{ __('lending.register_first') }}</p>
        @else
            @if(count($books) === 0)<p class="mb-3 text-sm text-gray-600" data-testid="books-empty">{{ __('lending.books_empty') }}</p>@endif
            <ul class="mb-4 divide-y" data-testid="my-books">
                @foreach($books as $book)
                    <li class="flex flex-wrap items-center gap-3 py-2 text-sm" data-testid="my-book-{{ $book['slug'] }}" data-status="{{ $book['status'] }}">
                        @if($book['photo'])<img src="{{ $book['photo'] }}" alt="" class="h-12 w-12 rounded object-cover">@endif
                        <div class="min-w-0 flex-1">
                            <a href="{{ $book['url'] }}" class="font-medium text-brandMaroon-900 hover:underline" dir="auto">{{ $book['title'] }}</a>
                            <p class="text-xs text-gray-500">{{ $book['condition_label'] }} · {{ __('lending.max_days', ['days' => $book['max_days']]) }} · <span data-testid="book-status">{{ $book['status_label'] }}</span></p>
                        </div>
                        <details class="w-full sm:w-auto">
                            <summary class="cursor-pointer text-brandMaroon-700 underline">{{ __('lending.edit_book') }}</summary>
                            <form method="POST" action="{{ route('public.lending.books.update', $book['id']) }}" enctype="multipart/form-data" class="mt-2 grid gap-2 sm:grid-cols-2">
                                @csrf
                                @include('public.lending._book-fields', ['book' => $book, 'conditions' => $conditions, 'limits' => $limits])
                                <div class="sm:col-span-2"><button type="submit" class="btn-primary text-sm">{{ __('lending.save_book') }}</button></div>
                            </form>
                        </details>
                        @if($book['status'] !== 'on_loan')
                            <form method="POST" action="{{ route('public.lending.books.destroy', $book['id']) }}" onsubmit="return confirm(@js(__('lending.remove_book_confirm')))">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-700 underline" data-testid="remove-book-{{ $book['slug'] }}">{{ __('lending.remove_book') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            <details class="rounded-lg border bg-gray-50 p-3" data-testid="add-book" @if(count($books) === 0) open @endif>
                <summary class="cursor-pointer font-medium text-brandMaroon-800">{{ __('lending.add_book') }}</summary>
                <form method="POST" action="{{ route('public.lending.books.store') }}" enctype="multipart/form-data" class="mt-3 grid gap-2 sm:grid-cols-2" data-testid="book-form">
                    @csrf
                    @include('public.lending._book-fields', ['book' => null, 'conditions' => $conditions, 'limits' => $limits])
                    <div class="sm:col-span-2"><button type="submit" class="btn-primary" data-testid="save-book">{{ __('lending.save_book') }}</button></div>
                </form>
            </details>
        @endunless
    </section>

    {{-- 3. Requests on my books --}}
    <section id="lending" class="mb-6 rounded-lg border bg-white p-4" data-testid="lending-section">
        <h2 class="mb-2 text-lg font-semibold">{{ __('lending.lending_section') }}</h2>
        @if(count($lending) === 0)
            <p class="text-sm text-gray-600" data-testid="lending-empty">{{ __('lending.lending_empty') }}</p>
        @else
            <ul class="divide-y" data-testid="lending-loans">
                @foreach($lending as $loan)
                    <li class="py-3 text-sm" data-testid="loan-{{ $loan['id'] }}" data-status="{{ $loan['status'] }}">
                        <p class="font-medium" dir="auto">{{ $loan['book']['title'] }}
                            <span class="ms-1 rounded px-2 py-0.5 text-xs font-semibold {{ $statusTone[$loan['status']] ?? '' }}" data-testid="loan-status">{{ $loan['status_label'] }}</span>
                            @if($loan['overdue'])<span class="ms-1 rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">{{ __('lending.overdue') }}</span>@endif
                        </p>
                        <p class="text-gray-600">{{ __('lending.asked_by') }} <span class="font-medium text-gray-800" dir="auto">{{ $loan['borrower']['name'] }}</span>@if($loan['borrower']['id_verified']) <span class="rounded bg-green-100 px-1 text-xs text-green-800">{{ __('lending.id_verified_badge') }}</span>@endif · {{ __('lending.asked_on') }} {{ $loan['requested_at'] }}
                            @if($loan['borrower']['phone']) · {{ __('lending.phone_label') }} <span dir="ltr" data-testid="borrower-phone">{{ $loan['borrower']['phone'] }}</span>@endif
                            @if($loan['due_on']) · {{ __('lending.due_on') }} {{ $loan['due_on'] }}@endif
                        </p>
                        @if($loan['message'])<p class="mt-1 rounded bg-gray-50 p-2 text-gray-700" dir="auto">{{ __('lending.their_message') }}: {{ $loan['message'] }}</p>@endif
                        @if($loan['note'])<p class="mt-1 text-xs text-gray-500" dir="auto">{{ __('lending.lender_note') }}: {{ $loan['note'] }}</p>@endif
                        <div class="mt-2 flex flex-wrap items-end gap-2">
                            @if($loan['status'] === 'requested')
                                <form method="POST" action="{{ route('public.lending.loan', [$loan['id'], 'accept']) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <label class="text-xs text-gray-600">{{ __('lending.due_on') }}<br><input type="date" name="due_on" min="{{ now()->toDateString() }}" class="form-input text-sm" data-testid="accept-due-{{ $loan['id'] }}"></label>
                                    <button type="submit" class="btn-primary text-sm" data-testid="accept-{{ $loan['id'] }}">{{ __('lending.accept') }}</button>
                                    <span class="text-xs text-gray-500">{{ __('lending.due_on_hint', ['days' => $loan['book']['max_days']]) }}</span>
                                </form>
                                <form method="POST" action="{{ route('public.lending.loan', [$loan['id'], 'decline']) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="text" name="note" maxlength="500" required placeholder="{{ __('lending.decline_note') }}" class="form-input text-sm" dir="auto" data-testid="decline-note-{{ $loan['id'] }}">
                                    <button type="submit" class="btn-secondary text-sm" data-testid="decline-{{ $loan['id'] }}">{{ __('lending.decline') }}</button>
                                </form>
                            @elseif($loan['status'] === 'accepted')
                                <form method="POST" action="{{ route('public.lending.loan', [$loan['id'], 'handover']) }}">@csrf<button type="submit" class="btn-primary text-sm" data-testid="handover-{{ $loan['id'] }}">{{ __('lending.handover') }}</button></form>
                            @elseif($loan['status'] === 'out')
                                <form method="POST" action="{{ route('public.lending.loan', [$loan['id'], 'returned']) }}">@csrf<button type="submit" class="btn-primary text-sm" data-testid="returned-{{ $loan['id'] }}">{{ __('lending.returned') }}</button></form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 4. Books I borrow --}}
    <section id="borrowing" class="mb-6 rounded-lg border bg-white p-4" data-testid="borrowing-section">
        <h2 class="mb-2 text-lg font-semibold">{{ __('lending.borrowing_section') }}</h2>
        @if(count($borrowing) === 0)
            <p class="text-sm text-gray-600" data-testid="borrowing-empty">{{ __('lending.borrowing_empty') }} <a href="{{ route('public.lending.index') }}" class="text-brandMaroon-700 underline">{{ __('lending.browse_shelf') }}</a></p>
        @else
            <ul class="divide-y" data-testid="borrowing-loans">
                @foreach($borrowing as $loan)
                    <li class="py-3 text-sm" data-testid="borrow-{{ $loan['id'] }}" data-status="{{ $loan['status'] }}">
                        <p class="font-medium"><a href="{{ $loan['book']['url'] }}" class="text-brandMaroon-900 hover:underline" dir="auto">{{ $loan['book']['title'] }}</a>
                            <span class="ms-1 rounded px-2 py-0.5 text-xs font-semibold {{ $statusTone[$loan['status']] ?? '' }}" data-testid="borrow-status">{{ $loan['status_label'] }}</span>
                            @if($loan['overdue'])<span class="ms-1 rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">{{ __('lending.overdue') }}</span>@endif
                        </p>
                        <p class="text-gray-600">{{ __('lending.lent_by') }} <span class="font-medium text-gray-800" dir="auto">{{ $loan['lender']['name'] }}</span>@if($loan['lender']['island']) · {{ $loan['lender']['island'] }}@endif
                            @if($loan['lender']['phone']) · {{ __('lending.phone_label') }} <span dir="ltr" data-testid="lender-phone">{{ $loan['lender']['phone'] }}</span>@else · <span class="text-xs">{{ __('lending.phone_after_accept') }}</span>@endif
                            @if($loan['due_on']) · {{ __('lending.due_on') }} {{ $loan['due_on'] }}@endif
                            @if($loan['book']['deposit']) · {{ __('lending.deposit_label') }}: <span dir="auto">{{ $loan['book']['deposit'] }}</span>@endif
                        </p>
                        @if($loan['note'])<p class="mt-1 text-xs text-gray-600" dir="auto">{{ __('lending.lender_note') }}: {{ $loan['note'] }}</p>@endif
                        @if(in_array($loan['status'], ['requested', 'accepted'], true))
                            <form method="POST" action="{{ route('public.lending.loan', [$loan['id'], 'cancel']) }}" class="mt-2">@csrf<button type="submit" class="text-red-700 underline" data-testid="cancel-{{ $loan['id'] }}">{{ __('lending.cancel') }}</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
