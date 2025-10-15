@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <article class="max-w-4xl mx-auto">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-4xl font-bold text-brandBlue-600 mb-4">{{ $page->title }}</h1>
            
            @if($page->excerpt)
                <p class="text-lg text-brandGray-600">{{ $page->excerpt }}</p>
            @endif
        </header>

        <!-- Featured Image -->
        @if($page->cover_image)
            <div class="mb-8">
                <img src="{{ asset('storage/' . $page->cover_image) }}" 
                     alt="{{ $page->title }}"
                     class="w-full max-h-96 object-cover rounded-lg shadow-sm">
            </div>
        @endif

        <!-- Content -->
        <div class="prose prose-lg max-w-none">
            {!! nl2br(e($page->body)) !!}
        </div>

        <!-- Footer -->
        <footer class="mt-12 pt-8 border-t border-brandGray-200">
            <div class="text-sm text-brandGray-500">
                {{ __('public.Last updated') }} {{ $page->updated_at->format('F j, Y') }}
            </div>
        </footer>
    </article>
</div>
@endsection
