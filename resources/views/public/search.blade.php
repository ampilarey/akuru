@extends('public.layouts.public')

@section('title', $q ? "Search: {$q}" : 'Search')
@section('description', 'Search courses, news, and events at Akuru Institute')

@section('content')
<!-- Search Header -->
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-12">
    <div class="container mx-auto px-4 max-w-3xl">
        <h1 class="text-3xl font-bold text-brandMaroon-900 mb-6">Search</h1>
        <form method="GET" action="{{ route('public.search') }}" class="flex gap-2">
            <input type="text" name="q" value="{{ $q }}" autofocus
                   placeholder="Search courses, news, events…"
                   class="form-input flex-1 py-3 text-lg">
            <button type="submit" class="btn-primary px-6 py-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>
        </form>
        @if($q)
            <p class="text-sm text-gray-600 mt-3">
                @if($total > 0)
                    Found <strong>{{ $total }}</strong> result{{ $total == 1 ? '' : 's' }} for "<strong>{{ $q }}</strong>"
                @else
                    No results found for "<strong>{{ $q }}</strong>"
                @endif
            </p>
        @endif
    </div>
</section>

<section class="py-10">
    <div class="container mx-auto px-4 max-w-4xl">

        @if(!$q)
            <div class="text-center text-gray-500 py-10">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p>Enter a keyword above to search.</p>
            </div>
        @elseif($total === 0)
            <div class="text-center text-gray-500 py-10">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-lg font-medium mb-2">Nothing found for "{{ $q }}"</p>
                <p class="text-sm">Try different keywords or browse our <a href="{{ route('public.courses.index') }}" class="text-brandMaroon-600 hover:underline">courses</a>.</p>
            </div>
        @else

            {{-- Courses --}}
            @if($courses->count() > 0)
            <div class="mb-10">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brandMaroon-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    Courses <span class="text-sm font-normal text-gray-500">({{ $courses->count() }})</span>
                </h2>
                <div class="space-y-3">
                    @foreach($courses as $course)
                    <a href="{{ LaravelLocalization::localizeURL(route('public.courses.show', $course->slug)) }}"
                       class="flex items-start gap-4 p-4 card hover:shadow-md transition-shadow group">
                        @if($course->cover_image)
                            <x-public.picture :src="$course->cover_image" :alt="$course->title"
                                class="w-16 h-16 object-cover rounded shrink-0"/>
                        @else
                            <div class="w-16 h-16 bg-brandBeige-200 rounded flex items-center justify-center shrink-0">
                                <svg class="w-7 h-7 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/>
                                </svg>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-600 transition-colors">{{ $course->title }}</p>
                            <p class="text-sm text-gray-500 line-clamp-2 mt-0.5">{{ $course->short_desc }}</p>
                            <div class="flex gap-3 mt-1 text-xs text-gray-400">
                                @if($course->category)<span>{{ $course->category->name }}</span>@endif
                                <span class="capitalize">{{ $course->status }}</span>
                                @if($course->fee)<span>MVR {{ number_format($course->fee, 2) }}</span>@else<span class="text-green-600">Free</span>@endif
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Posts (news + articles) --}}
            @if($posts->count() > 0)
            <div class="mb-10">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brandMaroon-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                    </svg>
                    News &amp; Articles <span class="text-sm font-normal text-gray-500">({{ $posts->count() }})</span>
                </h2>
                <div class="space-y-3">
                    @foreach($posts as $post)
                    @php $postRoute = $post->type === 'article' ? route('public.articles.show', $post->slug) : route('public.news.show', $post->slug); @endphp
                    <a href="{{ $postRoute }}" class="flex items-start gap-4 p-4 card hover:shadow-md transition-shadow group">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $post->type === 'article' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                                    {{ ucfirst($post->type) }}
                                </span>
                                <time class="text-xs text-gray-400">{{ $post->published_at->format('d M Y') }}</time>
                            </div>
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-600 transition-colors">{{ $post->title }}</p>
                            <p class="text-sm text-gray-500 line-clamp-2 mt-0.5">{{ $post->excerpt }}</p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Events --}}
            @if($events->count() > 0)
            <div class="mb-10">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brandMaroon-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Events <span class="text-sm font-normal text-gray-500">({{ $events->count() }})</span>
                </h2>
                <div class="space-y-3">
                    @foreach($events as $event)
                    <a href="{{ route('public.events.show', $event->slug ?? $event->id) }}"
                       class="flex items-start gap-4 p-4 card hover:shadow-md transition-shadow group">
                        <div class="shrink-0 text-center bg-brandMaroon-50 rounded-lg px-3 py-2 min-w-14">
                            <p class="text-xs text-brandMaroon-600 font-medium uppercase">{{ $event->start_date->format('M') }}</p>
                            <p class="text-2xl font-bold text-brandMaroon-900 leading-none">{{ $event->start_date->format('d') }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-600 transition-colors">{{ $event->title }}</p>
                            <p class="text-sm text-gray-500 line-clamp-2 mt-0.5">{{ $event->short_description }}</p>
                            @if($event->location)
                                <p class="text-xs text-gray-400 mt-1">📍 {{ $event->location }}</p>
                            @endif
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

        @endif
    </div>
</section>
@endsection
