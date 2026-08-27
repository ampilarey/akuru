@extends('public.layouts.public')

@section('title', $item['title'] . ' - ' . config('app.name'))
@section('description', $item['abstract'] ?? Str::limit(strip_tags($item['body'] ?? ''), 155))

@push('scripts')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@type": "ScholarlyArticle",
  "headline": {{ json_encode($item['title']) }},
  "description": {{ json_encode($item['abstract'] ?? Str::limit(strip_tags($item['body'] ?? ''), 200)) }},
  "datePublished": {{ json_encode($item['published_at']) }},
  "author": {"@type": "Organization", "name": "Akuru Institute"},
  "publisher": {"@type": "Organization", "name": "Akuru Institute", "url": {{ json_encode(config('app.url')) }}}
}
</script>
@endpush

@section('content')
<div class="container mx-auto px-4 py-10 max-w-4xl">
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.research.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Research') }}</a>
        <span>›</span>
        <span class="text-gray-700 truncate max-w-xs">{{ $item['title'] }}</span>
    </nav>

    <article>
        <header class="mb-8">
            <h1 class="text-4xl font-bold text-brandMaroon-900 mb-4 leading-tight">{{ $item['title'] }}</h1>
            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
                @if($item['year'])
                    <span>{{ $item['year'] }}</span>
                @endif
                @foreach($item['authors'] as $author)
                    @if($author['url'])
                        <a href="{{ $author['url'] }}" class="text-brandMaroon-700 hover:underline">{{ $author['name'] }}</a>
                    @else
                        <span>{{ $author['name'] }}</span>
                    @endif
                @endforeach
            </div>
        </header>

        @if($item['abstract'])
            <div class="text-lg text-gray-700 mb-8 p-5 bg-brandBeige-50 border-l-4 border-brandMaroon-500 rounded-r-lg">
                {{ $item['abstract'] }}
            </div>
        @endif

        <div class="prose prose-lg max-w-none text-gray-800 mb-8">
            {!! $item['body'] !!}
        </div>

        @if($item['citation_note'])
            <p class="text-sm text-gray-600 mb-6"><strong>{{ __('public.Citation') }}:</strong> {{ $item['citation_note'] }}</p>
        @endif

        @if($item['pdf'])
            <p class="mb-6">
                <a href="{{ $item['pdf']['url'] }}" class="btn-primary" target="_blank" rel="noopener">{{ __('public.Download PDF') }}</a>
            </p>
        @endif
    </article>
</div>
@endsection
