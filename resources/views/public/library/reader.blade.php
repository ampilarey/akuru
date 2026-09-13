@extends('public.layouts.public')

@section('title', $reader['title'] . ' - ' . config('app.name'))

@section('content')
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <nav class="text-sm text-gray-500 mb-4 flex items-center justify-between">
        <a href="{{ route('public.library.show', $reader['slug']) }}" class="hover:text-brandMaroon-600">‹ {{ $reader['title'] }}</a>
        <span>{{ __('public.Page') }} {{ $reader['page'] }} / {{ max($reader['total_pages'], 1) }}</span>
    </nav>

    @if($reader['is_preview'] ?? false)
        {{-- §9.4. Said before the pages, not after them: a reader who does not
             know this is a sample will read to the cap and conclude the book
             is broken. --}}
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
            {{ __('public.Free preview of :count pages', ['count' => $reader['readable_pages']]) }}
            <a class="underline" href="{{ route('public.library.show', $reader['slug']) }}">{{ __('public.Get the full item') }}</a>
        </div>
    @endif

    <div class="relative rounded-lg border bg-white p-8 select-none" style="print-color-adjust: exact;">
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden" aria-hidden="true">
            <span class="rotate-[-30deg] text-2xl text-gray-300/60 whitespace-nowrap">{{ $reader['watermark'] }}</span>
        </div>
        @if($reader['content'])
            <div class="prose max-w-none relative">{!! $reader['content'] !!}</div>
        @else
            <p class="text-gray-500 relative">{{ __('public.This item has no reader pages yet.') }}</p>
        @endif
    </div>

    @auth
        {{-- §9.1 private notes. Everything behind this box already existed —
             the column, the action argument, the controller's validation, and
             My Library's rendering of it. There was simply nowhere to type. --}}
        <form method="POST" action="{{ route('public.library.note', $reader['slug']) }}" class="mt-4">
            @csrf
            <input type="hidden" name="page" value="{{ $reader['page'] }}">
            <label class="block text-sm text-gray-600 mb-1" for="reader-note">
                {{ __('public.Your private note on this page') }}
            </label>
            <textarea id="reader-note" name="note" rows="2" maxlength="500"
                      class="w-full rounded border px-3 py-2 text-sm">{{ $reader['note'] }}</textarea>
            <button type="submit" class="btn-secondary mt-2 text-sm">{{ __('public.Save note') }}</button>
        </form>
    @endauth

    <div class="mt-4 flex items-center justify-between">
        <div>
            @if($reader['page'] > 1)
                <a class="btn-secondary" href="{{ route('public.library.read', ['slug' => $reader['slug'], 'page' => $reader['page'] - 1]) }}">‹ {{ __('public.Previous') }}</a>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @auth
                <form method="POST" action="{{ route('public.library.bookmark', $reader['slug']) }}">
                    @csrf
                    <input type="hidden" name="page" value="{{ $reader['page'] }}">
                    <button type="submit" class="btn-secondary">{{ $reader['bookmarked'] ? __('public.Remove bookmark') : __('public.Bookmark this page') }}</button>
                </form>
            @endauth
            {{-- `readable_pages`, not `total_pages`: at the end of a sample the
                 next step is buying it, not a page that will be clamped back. --}}
            @if($reader['page'] < $reader['readable_pages'])
                <a class="btn-primary" href="{{ route('public.library.read', ['slug' => $reader['slug'], 'page' => $reader['page'] + 1]) }}">{{ __('public.Next') }} ›</a>
            @elseif($reader['is_preview'] ?? false)
                <a class="btn-primary" href="{{ route('public.library.show', $reader['slug']) }}">{{ __('public.Get the full item') }}</a>
            @endif
        </div>
    </div>
</div>
@endsection
