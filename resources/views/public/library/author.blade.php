@extends('public.layouts.public')

@section('title', $author['display_name'] . ' - ' . __('public.Digital Library') . ' - ' . config('app.name'))
@section('description', Str::limit($author['bio'] ?? $author['display_name'], 155))

@section('content')
<div class="container mx-auto px-4 py-10 max-w-5xl">
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.library.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Digital Library') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ $author['display_name'] }}</span>
    </nav>

    <header class="mb-10 flex flex-col gap-5 md:flex-row md:items-start" data-author="{{ $author['slug'] }}">
        @if($author['photo_url'])
            <img src="{{ $author['photo_url'] }}" alt="{{ $author['display_name'] }}" class="h-32 w-32 shrink-0 rounded-full object-cover">
        @else
            <div class="flex h-32 w-32 shrink-0 items-center justify-center rounded-full bg-brandBeige-100 text-4xl font-bold text-brandMaroon-700" aria-hidden="true">{{ Str::upper(Str::substr($author['display_name'], 0, 1)) }}</div>
        @endif
        <div class="min-w-0">
            <p class="text-sm uppercase tracking-wide text-gray-500">{{ __('public.Author') }}</p>
            <h1 class="text-4xl font-bold text-brandMaroon-900 mb-2">{{ $author['display_name'] }}</h1>
            @if($author['expertise'])
                <p class="text-brandMaroon-700 font-medium mb-1">{{ $author['expertise'] }}</p>
            @endif
            @if($author['qualifications'])
                <p class="text-gray-600 mb-3 whitespace-pre-line">{{ $author['qualifications'] }}</p>
            @endif
            @if($author['bio'])
                <p class="text-gray-700 whitespace-pre-line">{{ $author['bio'] }}</p>
            @endif
            <p class="mt-3 text-sm text-gray-500">
                {{ trans_choice('public.:count published work|:count published works', $author['totals']['published'], ['count' => $author['totals']['published']]) }}
                @if($author['writing_since'])
                    · {{ __('public.Writing with Akuru since :year', ['year' => $author['writing_since']]) }}
                @endif
            </p>
            @if(count($author['links']) > 0)
                {{-- B6 (§8.7): the writer's own addresses. Each opens elsewhere and is not vouched for. --}}
                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm" data-testid="author-links">
                    @foreach($author['links'] as $key => $url)
                        <li><a href="{{ $url }}" rel="nofollow noopener" target="_blank" class="text-brandMaroon-700 underline hover:text-brandMaroon-900">{{ $key === 'website' ? __('public.Website') : ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'telegram' => 'Telegram'][$key] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </header>

    @if(count($author['featured']) > 0)
        {{-- B6 (§8.7): up to three works the writer pinned, before the full shelf. --}}
        <h2 class="text-2xl font-bold text-brandMaroon-900 mb-4">{{ __('public.Featured works') }}</h2>
        <div class="mb-10 grid gap-6 md:grid-cols-3" data-testid="featured-works">
            @foreach($author['featured'] as $item)
                <a href="{{ route('public.library.show', $item['slug']) }}" class="block rounded-lg border-2 border-brandMaroon-200 bg-white p-5 hover:shadow-md transition">
                    @if($item['cover_url'])
                        <img src="{{ $item['cover_url'] }}" alt="" class="mb-3 aspect-[3/4] w-full rounded object-cover" loading="lazy">
                    @endif
                    <span class="rounded bg-brandBeige-100 px-2 py-0.5 text-xs text-gray-500">{{ __('public.'.$item['content_type']) }}</span>
                    <h3 class="mt-2 text-lg font-semibold text-brandMaroon-900">{{ $item['title'] }}</h3>
                    @if($item['subtitle'])
                        <p class="text-sm text-gray-600">{{ $item['subtitle'] }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    <h2 class="text-2xl font-bold text-brandMaroon-900 mb-4">{{ __('public.Published works') }}</h2>
    @if(count($author['items']) === 0)
        <p class="text-gray-500">{{ __('public.Nothing published yet.') }}</p>
    @endif
    <div class="grid gap-6 md:grid-cols-2">
        @foreach($author['items'] as $item)
            <a href="{{ route('public.library.show', $item['slug']) }}" class="block rounded-lg border bg-white p-5 hover:shadow-md transition">
                @if($item['cover_url'])
                    <img src="{{ $item['cover_url'] }}" alt="" class="mb-3 aspect-[3/4] w-full rounded object-cover" data-cover="{{ $item['slug'] }}" loading="lazy">
                @endif
                <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                    <span class="rounded bg-brandBeige-100 px-2 py-0.5">{{ __('public.'.$item['content_type']) }}</span>
                    @if($item['published_at'])
                        <span>{{ $item['published_at'] }}</span>
                    @endif
                </div>
                <h3 class="text-lg font-semibold text-brandMaroon-900 mb-1">{{ $item['title'] }}</h3>
                @if($item['subtitle'])
                    <p class="text-sm text-gray-600 mb-2">{{ $item['subtitle'] }}</p>
                @endif
                @if($item['abstract'])
                    <p class="text-sm text-gray-600">{{ Str::limit($item['abstract'], 140) }}</p>
                @endif
            </a>
        @endforeach
    </div>
</div>
@endsection
