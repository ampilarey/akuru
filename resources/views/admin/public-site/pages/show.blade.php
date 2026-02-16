@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.pages.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Pages
        </a>
        <h1 class="text-3xl font-bold text-gray-900 mt-2">{{ $page->title }}</h1>
        <p class="text-gray-500 mt-1"><code class="bg-gray-100 px-2 py-1 rounded">{{ $page->slug }}</code></p>
    </div>

    <div class="card">
        <div class="p-6 space-y-6">
            @if($page->excerpt)
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Excerpt</h3>
                    <p class="text-gray-700">{{ $page->excerpt }}</p>
                </div>
            @endif

            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-1">Content</h3>
                <div class="prose max-w-none text-gray-700">
                    {!! nl2br(e($page->body)) !!}
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t">
                <div class="text-sm text-gray-500">
                    @if($page->is_published)
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Published</span>
                    @else
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Draft</span>
                    @endif
                    · Updated {{ $page->updated_at->format('M d, Y') }}
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('public.page.show', $page->slug) }}" target="_blank"
                       class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        View Public
                    </a>
                    <a href="{{ route('admin.pages.edit', $page) }}" class="btn-primary">
                        Edit
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
