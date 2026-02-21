@extends('public.layouts.public')

@section('title', $text['title'] ?? __('public.Welcome to Akuru Institute'))
@section('description', $text['desc'] ?? __('public.Learn Quran, Arabic, and Islamic Studies'))

@section('content')

{{-- ── HERO ─────────────────────────────────────────────────────── --}}
<section class="bg-gradient-to-r from-brandMaroon-600 to-brandMaroon-900 text-white py-12 sm:py-20">
    <div class="container mx-auto px-4">
        <div class="text-center">
            <h1 class="text-3xl sm:text-4xl md:text-6xl font-bold mb-4 sm:mb-6 leading-tight drop-shadow-lg">{{ $text['title'] ?? __('public.Welcome to Akuru Institute') }}</h1>
            <p class="text-lg sm:text-xl md:text-2xl mb-6 sm:mb-8 max-w-3xl mx-auto leading-relaxed drop-shadow-md text-white/90">{{ $text['desc'] ?? __('public.Learn Quran, Arabic, and Islamic Studies in the Maldives') }}</p>
            <div class="flex justify-center">
                <a href="{{ route('public.courses.index') }}"
                   class="bg-brandGold-600 text-brandMaroon-900 px-8 py-3 rounded-lg font-bold hover:bg-brandGold-500 transition-colors text-center text-base sm:text-lg shadow-lg">
                    {{ __('public.Enroll Now') }}
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ── STATS ────────────────────────────────────────────────────── --}}
@if(isset($stats) && ($stats['courses'] > 0 || $stats['students'] > 0 || $stats['teachers'] > 0))
<section class="py-10 bg-white border-b border-gray-100">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
            @if($stats['students'] > 0)
            <div>
                <div class="text-4xl font-bold text-brandMaroon-600">{{ number_format($stats['students']) }}+</div>
                <div class="text-gray-500 mt-1 text-sm">{{ __('public.Students Enrolled') }}</div>
            </div>
            @endif
            @if($stats['courses'] > 0)
            <div>
                <div class="text-4xl font-bold text-brandMaroon-600">{{ $stats['courses'] }}</div>
                <div class="text-gray-500 mt-1 text-sm">{{ __('public.Courses') }}</div>
            </div>
            @endif
            @if(!empty($stats['teachers']) && $stats['teachers'] > 0)
            <div>
                <div class="text-4xl font-bold text-brandMaroon-600">{{ $stats['teachers'] }}+</div>
                <div class="text-gray-500 mt-1 text-sm">{{ __('public.Qualified Teachers') }}</div>
            </div>
            @endif
            <div>
                <div class="text-4xl font-bold text-brandGold-600">5+</div>
                <div class="text-gray-500 mt-1 text-sm">Years of Service</div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ── OPEN COURSES ─────────────────────────────────────────────── --}}
<section class="py-12 sm:py-16 bg-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="text-center mb-8 sm:mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ __('public.Our Courses') }}</h2>
            <p class="text-base sm:text-lg text-gray-600 max-w-2xl mx-auto">{{ __('public.Choose from our comprehensive range of Islamic education programs') }}</p>
        </div>

        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
            @forelse($courses as $course)
            @php
                $isModel   = is_object($course) && method_exists($course, 'getAttribute');
                $slug      = $isModel ? ($course->slug ?? '') : '';
                $title     = $isModel ? ($course->title ?? $course->name ?? '') : '';
                $shortDesc = $isModel ? ($course->short_desc ?? $course->description ?? '') : '';
                $fee       = $isModel ? ($course->fee ?? null) : null;
                $status    = $isModel ? ($course->status ?? 'open') : 'open';
                $startDate = $isModel && !empty($course->start_date) ? \Carbon\Carbon::parse($course->start_date) : null;
                $seats     = $isModel ? ($course->available_seats ?? null) : null;
                $image     = $isModel && !empty($course->cover_image) ? asset('storage/'.$course->cover_image) : null;
            @endphp
            <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow border border-gray-200">
                <div class="relative h-48 bg-gray-200 bg-cover bg-center" style="{{ $image ? 'background-image:url(\''.$image.'\')' : '' }}">
                    @if(!$image)
                    <div class="absolute inset-0 flex items-center justify-center bg-brandBeige-200">
                        <svg class="w-14 h-14 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/></svg>
                    </div>
                    @endif
                    {{-- Status badge --}}
                    <span class="absolute top-3 left-3 text-xs font-bold px-2.5 py-1 rounded-full {{ $status === 'open' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $status === 'open' ? '● Open' : '◷ Upcoming' }}
                    </span>
                    {{-- Seats badge --}}
                    @if($seats !== null && $seats <= 5 && $seats > 0)
                        <span class="absolute top-3 right-3 text-xs font-bold px-2.5 py-1 rounded-full bg-red-100 text-red-700">{{ $seats }} left!</span>
                    @elseif($seats === 0)
                        <span class="absolute top-3 right-3 text-xs font-bold px-2.5 py-1 rounded-full bg-gray-200 text-gray-600">Full</span>
                    @endif
                </div>
                <div class="p-5 sm:p-6">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">{{ $title }}</h3>
                    <p class="text-sm sm:text-base text-gray-600 mb-4 leading-relaxed">{{ Str::limit($shortDesc, 100) }}</p>
                    <div class="flex justify-between items-center pt-3 border-t border-gray-100">
                        <div class="text-sm text-gray-500">
                            @if($startDate)
                                <span class="text-brandMaroon-700 font-medium">{{ $startDate->format('d M Y') }}</span>
                            @elseif($fee && $fee > 0)
                                <span class="text-brandMaroon-700 font-bold">{{ number_format($fee, 0) }} MVR</span>
                            @else
                                <span class="text-green-600 font-bold">Free</span>
                            @endif
                        </div>
                        <a href="{{ $slug ? route('public.courses.show', $slug) : route('public.courses.index') }}"
                           class="text-sm sm:text-base text-brandMaroon-600 hover:text-brandGold-600 font-semibold">
                            {{ __('public.Learn More') }} →
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-3 text-center py-10 text-gray-400">
                No open courses right now. <a href="{{ route('public.admissions.create') }}" class="text-brandMaroon-600 hover:underline">Leave your details</a> and we'll notify you.
            </div>
            @endforelse
        </div>

        <div class="text-center mt-10">
            <a href="{{ route('public.courses.index') }}"
               class="inline-block bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white font-bold px-8 py-3 rounded-lg shadow transition-colors">
                {{ __('public.View All Courses') }}
            </a>
        </div>
    </div>
</section>

{{-- ── WHY AKURU ────────────────────────────────────────────────── --}}
<section class="py-12 sm:py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">Why Choose Akuru Institute?</h2>
            <p class="text-gray-500 max-w-xl mx-auto">Trusted by hundreds of families across the Maldives for quality Islamic education.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
            @foreach([
                ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title'=>'Qualified Instructors', 'desc'=>'All our teachers hold recognised Islamic education qualifications and bring years of teaching experience.'],
                ['icon'=>'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'title'=>'Structured Curriculum', 'desc'=>'Well-planned programmes for Quran, Arabic, and Islamic Studies — from beginners to advanced levels.'],
                ['icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'title'=>'Flexible Schedules', 'desc'=>'Morning, evening, and weekend classes designed to fit around school, work, and family life.'],
                ['icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'title'=>'All Ages Welcome', 'desc'=>'Dedicated classes for children, teenagers, and adults. Everyone learns at the right pace.'],
                ['icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'title'=>'Affordable Fees', 'desc'=>'Quality Islamic education should be accessible. Our fees are kept reasonable with flexible payment options.'],
                ['icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'title'=>'Recognised Certificates', 'desc'=>'Earn certificates upon completion that recognise your achievement and dedication to learning.'],
            ] as $f)
            <div class="flex gap-4 p-5 rounded-lg border border-gray-100 hover:border-brandMaroon-200 hover:shadow-sm transition-all">
                <div class="w-11 h-11 bg-brandMaroon-100 text-brandMaroon-600 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 mb-1">{{ $f['title'] }}</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">{{ $f['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── NEWS & EVENTS ────────────────────────────────────────────── --}}
<section class="py-16 bg-white border-t border-gray-100">
    <div class="container mx-auto px-4">
        <div class="grid md:grid-cols-2 gap-12">
            {{-- News --}}
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-6">{{ __('public.Latest News') }}</h2>
                <div class="space-y-4">
                    @forelse($posts as $post)
                    @php $postSlug = $post->slug ?? $post->id ?? null; @endphp
                    <a href="{{ $postSlug ? route('public.news.show', $postSlug) : route('public.news.index') }}"
                       class="block border-l-4 border-brandMaroon-600 pl-4 bg-brandBeige-100 p-4 rounded-r-lg hover:bg-brandBeige-200 transition-colors">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-1">{{ $post->title }}</h3>
                        <p class="text-sm text-gray-600 leading-relaxed line-clamp-2">{{ Str::limit($post->body ?? $post->content ?? '', 100) }}</p>
                        <span class="text-xs text-gray-500 font-medium mt-2 block">{{ \Carbon\Carbon::parse($post->published_at ?? now())->format('M d, Y') }}</span>
                    </a>
                    @empty
                    <p class="text-gray-400 text-sm py-4">No news yet.</p>
                    @endforelse
                </div>
                <a href="{{ route('public.news.index') }}"
                   class="inline-block mt-6 text-brandMaroon-600 hover:text-brandGold-600 font-semibold text-base">
                    {{ __('public.View All News') }} →
                </a>
            </div>

            {{-- Events --}}
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-6">{{ __('public.Upcoming Events') }}</h2>
                <div class="space-y-4">
                    @forelse($events as $event)
                    @php $evSlug = $event->slug ?? $event->id ?? null; @endphp
                    <a href="{{ $evSlug ? route('public.events.show', $evSlug) : route('public.events.index') }}"
                       class="block bg-brandGold-50 border border-brandGold-200 p-4 rounded-lg hover:bg-brandGold-100 transition-colors">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">{{ $event->title }}</h3>
                        <div class="flex flex-col sm:flex-row sm:items-center text-sm text-gray-600 gap-1">
                            <span class="font-medium">📅 {{ \Carbon\Carbon::parse($event->start_date ?? now())->format('M d, Y') }}</span>
                            <span class="hidden sm:inline text-gray-300 mx-1">·</span>
                            <span class="font-medium">📍 {{ $event->location ?? __('public.Main Campus') }}</span>
                        </div>
                    </a>
                    @empty
                    <p class="text-gray-400 text-sm py-4">No upcoming events.</p>
                    @endforelse
                </div>
                <a href="{{ route('public.events.index') }}"
                   class="inline-block mt-6 text-brandGold-700 hover:text-brandMaroon-600 font-semibold text-base">
                    {{ __('public.View All Events') }} →
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ── UPCOMING INTAKES ─────────────────────────────────────────── --}}
@php
    $upcomingCourses = $courses->filter(fn($c) => is_object($c) && method_exists($c,'getAttribute') && in_array($c->status ?? '', ['open','upcoming']) && !empty($c->start_date));
@endphp
@if($upcomingCourses->count() > 0)
<section class="py-10 bg-brandGold-50 border-t border-brandGold-200">
    <div class="container mx-auto px-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-5 gap-3">
            <h2 class="text-lg font-bold text-gray-900">📅 Upcoming Intakes</h2>
            <a href="{{ route('public.courses.index') }}" class="text-sm text-brandMaroon-600 hover:underline font-semibold">View all →</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($upcomingCourses->take(6) as $uc)
            @php $ucDate = \Carbon\Carbon::parse($uc->start_date); @endphp
            <a href="{{ $uc->slug ? route('public.courses.show', $uc->slug) : route('public.courses.index') }}"
               class="flex items-center gap-4 bg-white rounded-lg px-4 py-3 shadow-sm hover:shadow-md border border-brandGold-200 hover:border-brandMaroon-300 transition-all">
                <div class="text-center shrink-0 w-12">
                    <div class="bg-brandMaroon-600 text-white rounded-t text-xs font-bold uppercase py-0.5">{{ $ucDate->format('M') }}</div>
                    <div class="border border-t-0 border-gray-200 rounded-b text-xl font-bold text-gray-900 leading-tight py-1">{{ $ucDate->format('d') }}</div>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">{{ $uc->title }}</p>
                    @if($uc->fee > 0)
                        <p class="text-xs text-brandMaroon-600 font-medium">{{ number_format($uc->fee, 0) }} MVR</p>
                    @else
                        <p class="text-xs text-green-600 font-medium">Free</p>
                    @endif
                </div>
                <span class="ml-auto shrink-0 text-xs font-bold px-2 py-0.5 rounded-full {{ $uc->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ ucfirst($uc->status) }}
                </span>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ── EDUCATIONAL ARTICLES ─────────────────────────────────────── --}}
@if(isset($articles) && $articles->count() > 0)
<section class="py-16 bg-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Educational Articles</h2>
                <p class="text-gray-600 mt-1">Knowledge and insights on Quran, Arabic &amp; Islamic studies</p>
            </div>
            <a href="{{ route('public.articles.index') }}" class="hidden sm:inline-block text-brandMaroon-600 hover:underline text-sm font-medium">View all →</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($articles as $article)
            <a href="{{ route('public.articles.show', $article->slug) }}" class="group block bg-white rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                @if($article->cover_image)
                <div class="aspect-video overflow-hidden">
                    <img src="{{ asset('storage/'.$article->cover_image) }}" alt="{{ $article->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                @endif
                <div class="p-5">
                    <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-600 transition-colors leading-snug mb-1">{{ $article->title }}</p>
                    <p class="text-sm text-gray-500 line-clamp-2">{{ $article->excerpt }}</p>
                    <p class="text-xs text-gray-400 mt-2">{{ $article->published_at->format('d M Y') }}</p>
                </div>
            </a>
            @endforeach
        </div>
        <a href="{{ route('public.articles.index') }}" class="sm:hidden inline-block mt-6 text-brandMaroon-600 hover:underline text-sm font-medium">View all articles →</a>
    </div>
</section>
@endif

{{-- ── TESTIMONIALS ─────────────────────────────────────────────── --}}
@if(isset($testimonials) && $testimonials->isNotEmpty())
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8 text-center">{{ __('public.What Our Students Say') }}</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-5xl mx-auto">
            @foreach($testimonials as $testimonial)
            <div class="bg-brandBeige-200 p-6 rounded-lg shadow-sm border border-brandBeige-300">
                <p class="text-gray-700 italic mb-4">"{{ $testimonial->quote }}"</p>
                <div class="font-semibold text-brandMaroon-600">{{ $testimonial->name }}</div>
                <div class="text-sm text-gray-500">{{ $testimonial->role }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ── CTA ──────────────────────────────────────────────────────── --}}
<section class="bg-gradient-to-r from-brandMaroon-700 to-brandMaroon-900 text-white py-12 sm:py-16">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-4 sm:mb-6 drop-shadow-lg">{{ __('public.Ready to Start Your Journey?') }}</h2>
        <p class="text-base sm:text-lg md:text-xl mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed drop-shadow-md text-white/85">{{ __('public.Join thousands of students who have chosen Akuru Institute for their Islamic education') }}</p>
        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
            <a href="{{ route('public.courses.index') }}"
               class="w-full sm:w-auto bg-brandGold-600 text-brandMaroon-900 px-6 sm:px-8 py-3 rounded-lg font-bold hover:bg-brandGold-500 transition-colors text-center shadow-lg">
                {{ __('public.Enroll Now') }}
            </a>
            <a href="{{ route('public.contact.create') }}"
               class="w-full sm:w-auto border-2 border-brandGold-600 text-brandGold-600 bg-white/10 backdrop-blur-sm px-6 sm:px-8 py-3 rounded-lg font-semibold hover:bg-brandGold-600 hover:text-brandMaroon-900 transition-colors text-center">
                {{ __('public.Contact Us') }}
            </a>
        </div>
    </div>
</section>

@endsection
