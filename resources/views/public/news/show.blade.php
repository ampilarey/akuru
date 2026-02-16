@extends('public.layouts.public')

@section('title', $post->title . ' - ' . config('app.name'))
@section('description', $post->summary ?? Str::limit(strip_tags($post->body), 155))

@push('scripts')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "NewsArticle",
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
<div class="container mx-auto px-4 py-8">
    <article class="max-w-4xl mx-auto">
        <!-- Header -->
        <header class="mb-8">
            <div class="text-sm text-brandGray-500 mb-4">
                <a href="{{ route('public.news.index', app()->getLocale()) }}" 
                   class="text-brandMaroon-600 hover:text-brandMaroon-700">
                    ← {{ __('public.Back to News') }}
                </a>
            </div>
            
            <h1 class="text-4xl font-bold text-brandMaroon-600 mb-4">{{ $post->title }}</h1>
            
            <div class="flex items-center text-brandGray-500 text-sm">
                <time datetime="{{ $post->published_at->toISOString() }}">
                    {{ $post->published_at->format('F j, Y') }}
                </time>
                @if($post->author)
                    <span class="mx-2">•</span>
                    <span>{{ __('public.By') }} {{ $post->author->name }}</span>
                @endif
            </div>
        </header>

        <!-- Featured Image -->
        @if($post->cover_image)
            <div class="mb-8">
                <x-public.picture
                    :src="$post->cover_image"
                    :alt="$post->title"
                    class="w-full max-h-96 object-cover rounded-lg shadow-sm"
                    loading="lazy"
                />
            </div>
        @endif

        <!-- Summary -->
        @if($post->summary)
            <div class="text-lg text-brandGray-700 mb-8 p-6 bg-brandMaroon-50 rounded-lg border-l-4 border-brandMaroon-600">
                {{ $post->summary }}
            </div>
        @endif

        <!-- Content -->
        <div class="prose prose-lg max-w-none">
            {!! nl2br(e($post->body)) !!}
        </div>

        <!-- Social Share -->
        <div class="mt-8 pt-6 border-t border-brandGray-200">
            <span class="text-sm font-medium text-brandGray-700 mr-3">{{ __('public.Share this article') }}:</span>
            <div class="flex gap-2 mt-2">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm" aria-label="Share on Facebook">Facebook</a>
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-2 bg-sky-500 text-white rounded hover:bg-sky-600 text-sm" aria-label="Share on Twitter">Twitter</a>
                <a href="https://wa.me/?text={{ urlencode($post->title . ' ' . request()->url()) }}" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-2 bg-green-500 text-white rounded hover:bg-green-600 text-sm" aria-label="Share on WhatsApp">WhatsApp</a>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-12 pt-8 border-t border-brandGray-200">
            <div class="flex justify-between items-center">
                <a href="{{ route('public.news.index', app()->getLocale()) }}" 
                   class="btn-secondary">
                    ← {{ __('public.Back to News') }}
                </a>
                
                <div class="text-sm text-brandGray-500">
                    {{ __('public.Published') }} {{ $post->published_at->diffForHumans() }}
                </div>
            </div>
        </footer>
    </article>
</div>
@endsection
