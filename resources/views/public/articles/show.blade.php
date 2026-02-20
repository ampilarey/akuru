@extends('public.layouts.public')

@section('title', $post->title . ' - ' . config('app.name'))
@section('description', $post->summary ?? Str::limit(strip_tags($post->body), 155))

@push('scripts')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": {{ json_encode($post->title) }},
  "description": {{ json_encode($post->summary ?? Str::limit(strip_tags($post->body), 200)) }},
  "datePublished": "{{ $post->published_at->toIso8601String() }}",
  "dateModified": "{{ $post->updated_at->toIso8601String() }}",
  "author": {"@type": "Organization", "name": "Akuru Institute"},
  "publisher": {"@type": "Organization", "name": "Akuru Institute", "url": "{{ config('app.url') }}"}
}
</script>
@endpush

@section('content')
<div class="container mx-auto px-4 py-10 max-w-4xl">

    <!-- Breadcrumb -->
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.articles.index') }}" class="hover:text-brandMaroon-600">Articles</a>
        <span>›</span>
        @if($post->category)
            <span class="text-gray-700">{{ $post->category->name }}</span>
            <span>›</span>
        @endif
        <span class="text-gray-700 truncate max-w-xs">{{ $post->title }}</span>
    </nav>

    <article>
        <!-- Header -->
        <header class="mb-8">
            @if($post->category)
                <span class="inline-block text-xs px-2 py-0.5 bg-brandMaroon-100 text-brandMaroon-700 rounded-full font-medium mb-3">
                    {{ $post->category->name }}
                </span>
            @endif

            <h1 class="text-4xl font-bold text-brandMaroon-900 mb-4 leading-tight">{{ $post->title }}</h1>

            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                <time datetime="{{ $post->published_at->toISOString() }}">
                    {{ $post->published_at->format('F j, Y') }}
                </time>
                @if($post->author)
                    <span>By {{ $post->author->name }}</span>
                @endif
                @if($post->reading_time)
                    <span>• {{ $post->reading_time }}</span>
                @endif
            </div>
        </header>

        <!-- Featured Image -->
        @if($post->cover_image)
            <div class="mb-8 rounded-xl overflow-hidden shadow-sm">
                <x-public.picture
                    :src="$post->cover_image"
                    :alt="$post->title"
                    class="w-full max-h-96 object-cover"
                />
            </div>
        @endif

        <!-- Summary / Lead -->
        @if($post->summary)
            <div class="text-lg text-gray-700 mb-8 p-5 bg-brandBeige-50 border-l-4 border-brandMaroon-500 rounded-r-lg">
                {{ $post->summary }}
            </div>
        @endif

        <!-- Body -->
        <div class="prose prose-lg max-w-none text-gray-800">
            {!! nl2br(e($post->body)) !!}
        </div>

        <!-- Tags -->
        @if($post->tags && count($post->tags) > 0)
            <div class="mt-8 pt-6 border-t border-gray-200">
                <span class="text-sm font-medium text-gray-600 mr-2">Tags:</span>
                @foreach($post->tags as $tag)
                    <a href="{{ route('public.articles.index', ['tag' => $tag]) }}"
                       class="inline-block text-xs px-2 py-0.5 bg-brandBeige-100 text-brandMaroon-700 rounded-full hover:bg-brandBeige-200 mr-1">
                        #{{ $tag }}
                    </a>
                @endforeach
            </div>
        @endif

        <!-- Share -->
        <div class="mt-6 pt-6 border-t border-gray-200">
            <span class="text-sm font-medium text-gray-700 mr-3">Share:</span>
            <div class="inline-flex gap-2 mt-1 flex-wrap">
                <a href="viber://forward?text={{ urlencode($post->title . ' ' . request()->url()) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-purple-600 text-white rounded text-sm hover:bg-purple-700">Viber</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Facebook</a>
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-sky-500 text-white rounded text-sm hover:bg-sky-600">X / Twitter</a>
            </div>
        </div>

        <!-- Back link -->
        <div class="mt-10">
            <a href="{{ route('public.articles.index') }}" class="btn-secondary">← Back to Articles</a>
        </div>
    </article>

    <!-- Related Articles -->
    @if($relatedPosts->count() > 0)
    <div class="mt-12 pt-8 border-t border-gray-200">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Articles</h2>
        <div class="grid sm:grid-cols-3 gap-6">
            @foreach($relatedPosts as $related)
            <a href="{{ route('public.articles.show', $related->slug) }}" class="group block">
                <p class="font-semibold text-gray-800 group-hover:text-brandMaroon-600 transition-colors leading-snug mb-1">{{ $related->title }}</p>
                <p class="text-xs text-gray-500">{{ $related->published_at->format('d M Y') }}</p>
            </a>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
