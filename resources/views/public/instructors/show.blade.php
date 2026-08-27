@extends('public.layouts.public')

@section('title', $instructor['name'] . ' - ' . config('app.name'))
@section('description', $instructor['bio'] ?? $instructor['name'])

@section('content')
<div class="container mx-auto px-4 py-10 max-w-4xl">
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.research.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Research') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ $instructor['name'] }}</span>
    </nav>

    <header class="mb-10">
        @if($instructor['photo_url'])
            <img src="{{ $instructor['photo_url'] }}" alt="{{ $instructor['name'] }}" class="w-28 h-28 rounded-full object-cover mb-4">
        @endif
        <h1 class="text-4xl font-bold text-brandMaroon-900 mb-2">{{ $instructor['name'] }}</h1>
        @if($instructor['qualification'])
            <p class="text-brandMaroon-700 font-medium mb-1">{{ $instructor['qualification'] }}</p>
        @endif
        @if($instructor['specialization'])
            <p class="text-gray-600 mb-3">{{ $instructor['specialization'] }}</p>
        @endif
        @if($instructor['bio'])
            <p class="text-gray-700">{{ $instructor['bio'] }}</p>
        @endif
    </header>

    <h2 class="text-2xl font-bold text-brandMaroon-900 mb-4">{{ __('public.Research') }}</h2>
    <div class="space-y-4">
        @forelse($posts as $post)
            <article class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-xl font-semibold text-brandMaroon-900">
                    <a href="{{ route('public.research.show', $post['slug']) }}" class="hover:underline">{{ $post['title'] }}</a>
                </h3>
                <p class="text-sm text-gray-500">{{ $post['year'] }}</p>
                @if($post['abstract'])
                    <p class="text-gray-700 mt-2">{{ $post['abstract'] }}</p>
                @endif
            </article>
        @empty
            <p class="text-gray-500">{{ __('public.No research posts yet.') }}</p>
        @endforelse
    </div>
</div>
@endsection
