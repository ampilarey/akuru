@extends('public.layouts.public')

@section('title', $item['title'] . ' - ' . config('app.name'))
@section('description', $item['abstract'] ?? Str::limit(strip_tags($item['body'] ?? ''), 155))

@section('content')
<div class="container mx-auto px-4 py-10 max-w-4xl">
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.library.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Library') }}</a>
        <span>›</span>
        <span class="text-gray-700 truncate max-w-xs">{{ $item['title'] }}</span>
    </nav>

    <article>
        <header class="mb-8">
            <h1 class="text-4xl font-bold text-brandMaroon-900 mb-2 leading-tight">{{ $item['title'] }}</h1>
            @if($item['subtitle'])
                <p class="text-xl text-gray-600 mb-3">{{ $item['subtitle'] }}</p>
            @endif
            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
                <span class="rounded bg-brandBeige-100 px-2 py-0.5">{{ __('public.'.$item['content_type']) }}</span>
                @if($item['category'])
                    <span>{{ $item['category']['name'] }}</span>
                @endif
                @foreach($item['authors'] as $author)
                    <span>{{ $author }}</span>
                @endforeach
                @if($item['published_at'])
                    <span>{{ $item['published_at'] }}</span>
                @endif
                @if($item['reading_time'])
                    <span>{{ $item['reading_time'] }} {{ __('public.min read') }}</span>
                @endif
            </div>
        </header>

        @if($item['abstract'])
            <p class="text-lg text-gray-700 mb-6">{{ $item['abstract'] }}</p>
        @endif

        @if($item['can_read'] && $item['body'])
            <div class="prose max-w-none">{!! $item['body'] !!}</div>
        @elseif($item['requires_login'])
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                <p class="mb-3 text-gray-700">{{ __('public.Sign in to read this item for free.') }}</p>
                <a href="{{ route('login') }}" class="btn-primary">{{ __('public.Sign in') }}</a>
            </div>
        @elseif($item['locked'])
            <div class="rounded-lg border bg-brandBeige-50 p-6 text-center">
                <p class="text-gray-700">{{ __('public.This item is not yet available for online reading.') }}</p>
            </div>
        @endif

        @if(count($item['tags']))
            <div class="mt-8 flex flex-wrap gap-2">
                @foreach($item['tags'] as $tag)
                    <a href="{{ route('public.library.index', ['tag' => $tag['slug']]) }}" class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600 hover:bg-gray-200">#{{ $tag['name'] }}</a>
                @endforeach
            </div>
        @endif
    </article>
</div>
@endsection
