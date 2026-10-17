@extends('public.layouts.public')

@section('title', __('public.blogs_title') . ' - ' . __('public.Digital Library') . ' - ' . config('app.name'))
@section('description', __('public.blogs_intro'))

@push('head_meta')
    <link rel="alternate" type="application/rss+xml" title="{{ __('public.blogs_title') }}" href="{{ route('public.library.blogs.feed') }}">
@endpush

@section('content')
{{-- BLOG_PLAN BL2b: the blogs' front — the latest published posts and the blogs that wrote them. --}}
<div class="container mx-auto px-4 py-10 max-w-6xl">
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.library.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Digital Library') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ __('public.blogs_title') }}</span>
    </nav>

    <header class="mb-8">
        <h1 class="text-4xl font-bold text-brandMaroon-900 mb-2">{{ __('public.blogs_title') }}</h1>
        <p class="text-gray-600">{{ __('public.blogs_intro') }}</p>
        <a href="{{ route('public.library.blogs.feed') }}" class="mt-2 inline-block text-sm text-brandMaroon-700 underline" data-testid="blogs-feed">{{ __('public.follow_feed') }}</a>
    </header>

    @if(count($featured ?? []) > 0)
        {{-- BLOG_PLAN BL7: the posts the office featured this month. --}}
        <section class="mb-10" data-testid="blogs-featured">
            <h2 class="text-2xl font-bold text-brandMaroon-900 mb-4">{{ __('public.blogs_featured') }}</h2>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach($featured as $post)
                    <a href="{{ route('public.library.show', $post['slug']) }}" class="block rounded-lg border-2 border-brandMaroon-200 bg-white p-4 hover:shadow-md" data-featured="{{ $post['slug'] }}" @if($post['language'] === 'dv' || $post['language'] === 'ar') dir="rtl" @endif>
                        <h3 class="font-semibold text-brandMaroon-900">{{ $post['title'] }}</h3>
                        @if($post['writer'])
                            <p class="text-sm text-gray-500">{{ __('public.blogs_by', ['name' => $post['writer']['display_name']]) }}</p>
                        @endif
                        @if($post['excerpt'] !== '')
                            <p class="mt-2 text-sm text-gray-700">{{ Str::limit($post['excerpt'], 120) }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid gap-10 lg:grid-cols-3">
        <section class="lg:col-span-2" data-testid="blogs-latest">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-2xl font-bold text-brandMaroon-900">{{ __('public.blogs_latest') }}</h2>
                <form method="get" action="{{ route('public.library.blogs') }}" class="flex items-center gap-2 text-sm">
                    <label for="blogs-language" class="text-gray-600">{{ __('public.blogs_language') }}</label>
                    <select id="blogs-language" name="language" class="rounded border-gray-300 text-sm">
                        <option value="">{{ __('public.blogs_all_languages') }}</option>
                        @foreach($languages as $code => $name)
                            <option value="{{ $code }}" @selected($language === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded bg-brandMaroon-600 px-3 py-1.5 text-white">{{ __('public.blogs_filter') }}</button>
                </form>
            </div>

            @if(count($posts) === 0)
                <p class="text-gray-500" data-testid="blogs-empty">{{ __('public.blogs_empty') }}</p>
            @endif
            <div class="grid gap-5">
                @foreach($posts as $post)
                    <article class="rounded-lg border bg-white p-5" data-post="{{ $post['slug'] }}" @if($post['language'] === 'dv' || $post['language'] === 'ar') dir="rtl" @endif>
                        <h3 class="text-xl font-semibold text-brandMaroon-900">
                            <a href="{{ route('public.library.show', $post['slug']) }}" class="hover:underline">{{ $post['title'] }}</a>
                        </h3>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                            @if($post['writer'])
                                <a href="{{ route('public.library.author', $post['writer']['slug']) }}" class="text-brandMaroon-700 hover:underline" rel="author">{{ __('public.blogs_by', ['name' => $post['writer']['display_name']]) }}</a>
                            @endif
                            @if($post['student'])
                                <span class="rounded bg-brandBeige-100 px-2 py-0.5 text-xs">{{ __('public.blogs_student') }}</span>
                            @endif
                            @if($post['published_at'])
                                <span>{{ $post['published_at'] }}</span>
                            @endif
                        </p>
                        @if($post['excerpt'] !== '')
                            <p class="mt-2 text-gray-700">{{ $post['excerpt'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <aside data-testid="blogs-list">
            <h2 class="text-2xl font-bold text-brandMaroon-900 mb-4">{{ __('public.blogs_all') }}</h2>
            <ul class="grid gap-3">
                @foreach($blogs as $blog)
                    <li class="rounded-lg border bg-white p-4">
                        <a href="{{ route('public.library.author', $blog['slug']) }}" class="font-semibold text-brandMaroon-900 hover:underline">{{ $blog['blog_title'] ?: $blog['display_name'] }}</a>
                        <p class="text-sm text-gray-500">
                            {{ __('public.blogs_by', ['name' => $blog['display_name']]) }}
                            · {{ trans_choice('public.blogs_count', $blog['posts'], ['count' => $blog['posts']]) }}
                            @if($blog['student'])
                                · {{ __('public.blogs_student') }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
</div>
@endsection
