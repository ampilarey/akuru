@extends('public.layouts.public')

@section('title', $reader['title'] . ' - ' . config('app.name'))

@section('content')
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <nav class="text-sm text-gray-500 mb-4 flex items-center justify-between">
        <a href="{{ route('public.library.show', $reader['slug']) }}" class="hover:text-brandMaroon-600">‹ {{ $reader['title'] }}</a>
        <span>{{ __('public.Page') }} {{ $reader['page'] }} / {{ max($reader['total_pages'], 1) }}</span>
    </nav>

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
            @if($reader['page'] < $reader['total_pages'])
                <a class="btn-primary" href="{{ route('public.library.read', ['slug' => $reader['slug'], 'page' => $reader['page'] + 1]) }}">{{ __('public.Next') }} ›</a>
            @endif
        </div>
    </div>
</div>
@endsection
