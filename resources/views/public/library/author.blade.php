@extends('public.layouts.public')

@section('title', $author['display_name'] . ' - ' . __('public.Digital Library') . ' - ' . config('app.name'))
@section('description', Str::limit($author['design']['tagline'] ?? $author['bio'] ?? $author['display_name'], 155))

@if($author['blog'] && $author['design']['fonts_url'])
    @push('head_meta')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $author['design']['fonts_url'] }}">
    @endpush
@endif

@section('content')
{{-- BLOG_PLAN BL3a: a blog's approved design as CSS custom properties — normalised hex
     colours and words from fixed lists (BlogPage::cssVariables), escaped like any value. --}}
@php($blogStyle = $author['blog'] ? collect($author['design']['css'])->map(fn ($value, $name) => $name.': '.$value)->implode('; ').'; background: var(--blog-page); color: var(--blog-text);' : null)
<div @if($blogStyle) style="{{ $blogStyle }}" data-blog-theme="{{ $author['design']['theme'] }}" @endif>
<div class="container mx-auto px-4 py-10 max-w-5xl">
    @if($author['banner_url'])
        {{-- BLOG_PLAN BL3b: the banner, approved by the office, shown 4:1. --}}
        <img src="{{ $author['banner_url'] }}" alt="" class="mb-6 aspect-[4/1] w-full rounded-lg object-cover" @if($blogStyle) style="border-radius: var(--blog-radius);" @endif data-testid="blog-banner">
    @endif
    <nav class="text-sm mb-6 flex gap-2 items-center flex-wrap {{ $blogStyle ? 'opacity-80' : 'text-gray-500' }}">
        <a href="{{ route('public.library.index') }}" class="hover:underline">{{ __('public.Digital Library') }}</a>
        <span>›</span>
        @if($author['blog'])
            <a href="{{ route('public.library.blogs') }}" class="hover:underline">{{ __('public.blogs_title') }}</a>
            <span>›</span>
        @endif
        <span>{{ $author['display_name'] }}</span>
    </nav>

    <header class="mb-10 flex flex-col gap-5 md:flex-row md:items-start" data-author="{{ $author['slug'] }}">
        @if($author['photo_url'])
            <img src="{{ $author['photo_url'] }}" alt="{{ $author['display_name'] }}" class="h-32 w-32 shrink-0 rounded-full object-cover">
        @else
            <div class="flex h-32 w-32 shrink-0 items-center justify-center rounded-full text-4xl font-bold {{ $blogStyle ? '' : 'bg-brandBeige-100 text-brandMaroon-700' }}" @if($blogStyle) style="background: var(--blog-accent); color: var(--blog-on-accent);" @endif aria-hidden="true">{{ Str::upper(Str::substr($author['display_name'], 0, 1)) }}</div>
        @endif
        <div class="min-w-0">
            <p class="text-sm uppercase tracking-wide opacity-70">{{ $author['student'] ? __('public.blogs_student') : __('public.Author') }}</p>
            <h1 class="text-4xl font-bold mb-2 {{ $blogStyle ? '' : 'text-brandMaroon-900' }}" @if($blogStyle) style="color: var(--blog-accent); font-family: var(--blog-font-heading);" @endif>{{ $author['display_name'] }}</h1>
            @if($author['blog_title'])
                {{-- BLOG_PLAN BL2b: the blog's own name. --}}
                <p class="text-xl mb-2" data-testid="blog-title">{{ $author['blog_title'] }}</p>
            @endif
            @if($author['blog'] && $author['design']['tagline'])
                <p class="mb-2 italic" data-testid="blog-tagline">{{ $author['design']['tagline'] }}</p>
            @endif
            @if($author['expertise'])
                <p class="font-medium mb-1">{{ $author['expertise'] }}</p>
            @endif
            @if($author['qualifications'])
                <p class="opacity-80 mb-3 whitespace-pre-line">{{ $author['qualifications'] }}</p>
            @endif
            @if($author['bio'] && ! $author['blog'])
                <p class="text-gray-700 whitespace-pre-line">{{ $author['bio'] }}</p>
            @endif
            @if($author['blog'] && $author['design']['languages'] !== [])
                <ul class="mt-2 flex flex-wrap gap-2 text-xs" data-testid="blog-languages">
                    @foreach($author['design']['languages'] as $code)
                        <li class="rounded-full px-2 py-0.5" style="background: var(--blog-card);">{{ ['en' => 'English', 'dv' => 'ދިވެހި', 'ar' => 'العربية'][$code] }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-3 text-sm opacity-70">
                {{ trans_choice('public.:count published work|:count published works', $author['totals']['published'], ['count' => $author['totals']['published']]) }}
                @if($author['writing_since'])
                    · {{ __('public.Writing with Akuru since :year', ['year' => $author['writing_since']]) }}
                @endif
            </p>
            @if(count($author['links']) > 0)
                {{-- B6 (§8.7): the writer's own addresses. Each opens elsewhere and is not vouched for. --}}
                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm" data-testid="author-links">
                    @foreach($author['links'] as $key => $url)
                        <li><a href="{{ $url }}" rel="nofollow noopener" target="_blank" class="underline">{{ $key === 'website' ? __('public.Website') : ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'telegram' => 'Telegram'][$key] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </header>

    @if($author['blog'])
        {{-- BLOG_PLAN BL3a: the sections the writer switched on, in their order. --}}
        @foreach($author['design']['sections'] as $section)
            @continue(! $section['on'])
            @switch($section['key'])
                @case('pinned')
                @case('latest')
                    @if(count($author['blog'][$section['key']]) > 0)
                        <section class="mb-10" data-testid="blog-section-{{ $section['key'] }}">
                            <h2 class="text-2xl font-bold mb-4" style="color: var(--blog-accent); font-family: var(--blog-font-heading);">{{ $section['key'] === 'pinned' ? __('public.blogs_pinned') : __('public.blogs_posts_heading') }}</h2>
                            @foreach($author['blog'][$section['key']] as $post)
                                <article class="mb-4 p-5" style="background: var(--blog-card); border-radius: var(--blog-radius);" data-post="{{ $post['slug'] }}" @if($post['language'] === 'dv' || $post['language'] === 'ar') dir="rtl" @endif>
                                    @if($section['key'] === 'pinned')
                                        <span class="text-xs opacity-70" data-testid="pinned-post">{{ __('public.blogs_pinned') }}</span>
                                    @endif
                                    <h3 class="mt-1 text-xl font-semibold"><a href="{{ route('public.library.show', $post['slug']) }}" class="hover:underline" style="color: var(--blog-accent);">{{ $post['title'] }}</a></h3>
                                    @if($post['published_at'])
                                        <p class="text-sm opacity-70">{{ $post['published_at'] }}</p>
                                    @endif
                                    @if($post['excerpt'] !== '')
                                        <p class="mt-2">{{ $post['excerpt'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </section>
                    @endif
                    @break
                @case('series')
                    @if(count($author['blog']['series']) > 0)
                        <section class="mb-10" data-testid="blog-section-series">
                            <h2 class="text-2xl font-bold mb-4" style="color: var(--blog-accent); font-family: var(--blog-font-heading);">{{ __('public.blogs_series') }}</h2>
                            <div class="grid gap-4 md:grid-cols-2" data-testid="author-series">
                                @foreach($author['blog']['series'] as $series)
                                    <div class="p-4" style="background: var(--blog-card); border-radius: var(--blog-radius);">
                                        <p class="font-semibold">{{ $series['title'] }}</p>
                                        <ol class="mt-2 list-decimal list-inside text-sm">
                                            @foreach($series['posts'] as $part)
                                                <li><a href="{{ route('public.library.show', $part['slug']) }}" class="hover:underline" style="color: var(--blog-accent);">{{ $part['title'] }}</a></li>
                                            @endforeach
                                        </ol>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                    @break
                @case('works')
                    @if(count($author['items']) > 0)
                        <section class="mb-10" data-testid="blog-section-works">
                            <h2 class="text-2xl font-bold mb-4" style="color: var(--blog-accent); font-family: var(--blog-font-heading);">{{ __('public.Published works') }}</h2>
                            <div class="grid gap-6 md:grid-cols-2">
                                @foreach($author['items'] as $item)
                                    <a href="{{ route('public.library.show', $item['slug']) }}" class="block p-5 hover:shadow-md transition" style="background: var(--blog-card); border-radius: var(--blog-radius);">
                                        <span class="text-xs opacity-70">{{ __('public.'.$item['content_type']) }}</span>
                                        <h3 class="text-lg font-semibold mb-1">{{ $item['title'] }}</h3>
                                        @if($item['abstract'])
                                            <p class="text-sm opacity-80">{{ Str::limit($item['abstract'], 140) }}</p>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif
                    @break
                @case('about')
                    @if($author['bio'])
                        <section class="mb-10" data-testid="blog-section-about">
                            <h2 class="text-2xl font-bold mb-4" style="color: var(--blog-accent); font-family: var(--blog-font-heading);">{{ __('public.blogs_about') }}</h2>
                            <p class="whitespace-pre-line p-5" style="background: var(--blog-card); border-radius: var(--blog-radius);">{{ $author['bio'] }}</p>
                        </section>
                    @endif
                    @break
            @endswitch
        @endforeach
    @else
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
    @endif
</div>
</div>
@endsection
