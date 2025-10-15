@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brandBlue-600 mb-4">{{ __('public.Photo Gallery') }}</h1>
        <p class="text-brandGray-600">{{ __('public.Explore moments and memories from our institute') }}</p>
    </div>

    @if($galleries->count() > 0)
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            @foreach($galleries as $gallery)
                <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow">
                    <div class="aspect-video bg-brandGray-100 rounded-t-lg overflow-hidden">
                        @if($gallery->items->first())
                            <img src="{{ asset('storage/' . $gallery->items->first()->file_path) }}" 
                                 alt="{{ $gallery->title }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-brandGray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        @endif
                    </div>
                    
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-brandBlue-600 mb-3">
                            <a href="{{ route('public.gallery.show', [app()->getLocale(), $gallery->id]) }}" 
                               class="hover:text-brandBlue-700">
                                {{ $gallery->title }}
                            </a>
                        </h2>
                        
                        @if(isset($gallery->description))
                            <p class="text-brandGray-600 mb-4">{{ Str::limit($gallery->description, 100) }}</p>
                        @endif
                        
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-brandGray-500">
                                {{ $gallery->items->count() }} {{ __('public.photos') }}
                            </div>
                            <a href="{{ route('public.gallery.show', [app()->getLocale(), $gallery->id]) }}" 
                               class="text-brandBlue-600 hover:text-brandBlue-700 font-medium text-sm">
                                {{ __('public.View Gallery') }} →
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if(method_exists($galleries, 'hasPages') && $galleries->hasPages())
            <div class="flex justify-center">
                {{ $galleries->links() }}
            </div>
        @endif
    @else
        <div class="text-center py-12">
            <div class="text-brandGray-400 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-medium text-brandGray-600 mb-2">{{ __('public.No Galleries Available') }}</h3>
            <p class="text-brandGray-500">{{ __('public.Check back later for photo galleries') }}</p>
        </div>
    @endif
</div>
@endsection
