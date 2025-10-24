@extends('public.layouts.public')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brandMaroon-600 mb-4">{{ __('public.Latest News') }}</h1>
        <p class="text-brandGray-600">{{ __('public.Stay updated with the latest news and announcements from Akuru Institute') }}</p>
    </div>

    @if($posts->count() > 0)
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            @foreach($posts as $post)
                <article class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow">
                    @if($post->cover_image)
                        <div class="aspect-video bg-brandGray-100 rounded-t-lg overflow-hidden">
                            <img src="{{ asset('storage/' . $post->cover_image) }}" 
                                 alt="{{ $post->title }}"
                                 class="w-full h-full object-cover">
                        </div>
                    @endif
                    
                    <div class="p-6">
                        <div class="text-sm text-brandGray-500 mb-2">
                            {{ $post->published_at->format('M d, Y') }}
                            @if($post->author)
                                • {{ __('public.By') }} {{ $post->author->name }}
                            @endif
                        </div>
                        
                        <h2 class="text-xl font-semibold text-brandMaroon-600 mb-3">
                            <a href="{{ route('public.news.show', [app()->getLocale(), $post->slug]) }}" 
                               class="hover:text-brandMaroon-700">
                                {{ $post->title }}
                            </a>
                        </h2>
                        
                        <p class="text-brandGray-600 mb-4">{{ $post->summary }}</p>
                        
                        <a href="{{ route('public.news.show', [app()->getLocale(), $post->slug]) }}" 
                           class="text-brandMaroon-600 hover:text-brandMaroon-700 font-medium">
                            {{ __('public.Read More') }} →
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($posts->hasPages())
            <div class="flex justify-center">
                {{ $posts->links() }}
            </div>
        @endif
    @else
        <div class="text-center py-12">
            <div class="text-brandGray-400 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-medium text-brandGray-600 mb-2">{{ __('public.No News Available') }}</h3>
            <p class="text-brandGray-500">{{ __('public.Check back later for the latest updates') }}</p>
        </div>
    @endif
</div>
@endsection
