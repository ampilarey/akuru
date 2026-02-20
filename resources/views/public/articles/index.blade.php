@extends('public.layouts.public')

@section('title', __('public.Educational Articles') . ' - ' . config('app.name'))
@section('description', __('public.Read educational articles on Quran, Arabic, and Islamic studies'))

@section('content')
<!-- Header -->
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Educational Articles') }}</h1>
        <p class="text-xl text-brandGray-700">{{ __('public.Insights and knowledge on Quran, Arabic language, and Islamic studies') }}</p>
    </div>
</section>

<!-- Search + Filters -->
<section class="bg-white border-b py-4">
    <div class="container mx-auto px-4">
        <form method="GET" action="{{ route('public.articles.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('public.Search articles...') }}"
                       class="form-input w-full">
            </div>
            @if($categories->count() > 0)
            <div>
                <select name="category" class="form-input">
                    <option value="">{{ __('public.All Categories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }} ({{ $cat->published_posts_count }})
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <button type="submit" class="btn-primary">{{ __('public.Search') }}</button>
            @if(request()->hasAny(['search','category','tag']))
                <a href="{{ route('public.articles.index') }}" class="btn-secondary">{{ __('public.Clear') }}</a>
            @endif
        </form>

        <!-- Popular tags -->
        @if($popularTags->count() > 0)
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach($popularTags as $tag)
                <a href="{{ route('public.articles.index', ['tag' => $tag]) }}"
                   class="text-xs px-2 py-1 rounded-full {{ request('tag') == $tag ? 'bg-brandMaroon-600 text-white' : 'bg-brandBeige-100 text-brandMaroon-700 hover:bg-brandBeige-200' }}">
                    #{{ $tag }}
                </a>
            @endforeach
        </div>
        @endif
    </div>
</section>

<!-- Articles Grid -->
<section class="py-12">
    <div class="container mx-auto px-4">
        @if($posts->count() > 0)
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                @foreach($posts as $post)
                <article class="card overflow-hidden hover:shadow-lg transition-all group">
                    @if($post->cover_image)
                        <div class="aspect-video bg-brandGray-100 overflow-hidden">
                            <x-public.picture
                                :src="$post->cover_image"
                                :alt="$post->title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                loading="lazy"
                            />
                        </div>
                    @else
                        <div class="aspect-video bg-gradient-to-br from-brandBeige-100 to-brandBeige-200 flex items-center justify-center">
                            <svg class="w-12 h-12 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    @endif

                    <div class="p-5">
                        @if($post->category)
                            <span class="text-xs px-2 py-0.5 bg-brandMaroon-100 text-brandMaroon-700 rounded-full font-medium">
                                {{ $post->category->name }}
                            </span>
                        @endif

                        <h2 class="text-lg font-bold text-gray-900 mt-2 mb-2 group-hover:text-brandMaroon-600 transition-colors leading-snug">
                            <a href="{{ route('public.articles.show', $post->slug) }}">{{ $post->title }}</a>
                        </h2>

                        <p class="text-gray-600 text-sm line-clamp-3 mb-3">{{ $post->excerpt }}</p>

                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <time>{{ $post->published_at->format('d M Y') }}</time>
                            @if($post->reading_time)
                                <span>{{ $post->reading_time }}</span>
                            @endif
                        </div>

                        <a href="{{ route('public.articles.show', $post->slug) }}"
                           class="mt-3 inline-block text-sm text-brandMaroon-600 hover:underline font-medium">
                            {{ __('public.Read Article') }} →
                        </a>
                    </div>
                </article>
                @endforeach
            </div>

            {{ $posts->links() }}
        @else
            <div class="text-center py-16 text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-lg font-medium">{{ __('public.No articles found') }}</p>
                <a href="{{ route('public.articles.index') }}" class="mt-3 inline-block text-brandMaroon-600 hover:underline text-sm">Clear filters</a>
            </div>
        @endif
    </div>
</section>
@endsection
