@extends('public.layouts.public')

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
                <img src="{{ asset('storage/' . $post->cover_image) }}" 
                     alt="{{ $post->title }}"
                     class="w-full max-h-96 object-cover rounded-lg shadow-sm">
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
