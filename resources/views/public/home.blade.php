@extends('public.layouts.public')

@section('title', $text['title'] ?? __('public.Welcome to Akuru Institute'))
@section('description', $text['desc'] ?? __('public.Learn Quran, Arabic, and Islamic Studies'))

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     1. HERO
══════════════════════════════════════════════════════════════ --}}
<section class="relative bg-gradient-to-br from-brandMaroon-900 via-brandMaroon-700 to-brandMaroon-800 text-white overflow-hidden">
    {{-- Decorative background pattern --}}
    <div class="absolute inset-0 opacity-10" style="background-image:url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")"></div>
    <div class="container mx-auto px-4 py-16 sm:py-24 relative">
        <div class="max-w-3xl mx-auto text-center">
            <span class="inline-block bg-brandGold-500/20 border border-brandGold-400/40 text-brandGold-300 text-sm font-semibold px-4 py-1.5 rounded-full mb-6">
                🕌 Islamic Education in the Maldives
            </span>
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold mb-6 leading-tight">
                {{ $text['title'] ?? 'Welcome to Akuru Institute' }}
            </h1>
            <p class="text-lg sm:text-xl md:text-2xl mb-10 text-white/85 max-w-2xl mx-auto leading-relaxed">
                {{ $text['desc'] ?? 'Learn Quran, Arabic, and Islamic Studies in the Maldives' }}
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('public.admissions.create') }}"
                   class="inline-flex items-center justify-center gap-2 bg-brandGold-500 hover:bg-brandGold-400 text-brandMaroon-900 font-bold px-8 py-4 rounded-xl text-lg shadow-lg transition-all hover:scale-105">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Apply Now
                </a>
                <a href="{{ route('public.courses.index') }}"
                   class="inline-flex items-center justify-center gap-2 border-2 border-white/50 text-white hover:bg-white/10 font-semibold px-8 py-4 rounded-xl text-lg transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/></svg>
                    Browse Courses
                </a>
            </div>
        </div>
    </div>
    {{-- Wave bottom --}}
    <div class="absolute bottom-0 left-0 right-0">
        <svg viewBox="0 0 1440 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 48h1440V24C1200 48 960 0 720 0S240 48 0 24v24z" fill="white"/></svg>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     2. OPEN COURSES (most important — right after hero)
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20 bg-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
            <div>
                <span class="text-brandMaroon-600 font-semibold text-sm uppercase tracking-wider">Enroll today</span>
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-1">Open Courses</h2>
                <p class="text-gray-500 mt-2">Secure your seat — limited places available</p>
            </div>
            <a href="{{ route('public.courses.index') }}" class="text-brandMaroon-600 hover:text-brandMaroon-800 font-semibold text-sm flex items-center gap-1 shrink-0">
                View all courses <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($courses as $course)
            @php
                $isModel   = is_object($course) && method_exists($course, 'getAttribute');
                $slug      = $isModel ? ($course->slug ?? '') : '';
                $title     = $isModel ? ($course->title ?? '') : '';
                $shortDesc = $isModel ? ($course->short_desc ?? $course->description ?? '') : '';
                $fee       = $isModel ? ($course->fee ?? null) : null;
                $status    = $isModel ? ($course->status ?? 'open') : 'open';
                $startDate = $isModel && $course->start_date ? \Carbon\Carbon::parse($course->start_date) : null;
                $seats     = $isModel ? ($course->available_seats ?? null) : null;
                $image     = $isModel && $course->cover_image ? asset('storage/'.$course->cover_image) : null;
                $statusBg  = $status === 'open' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800';
            @endphp
            <a href="{{ $slug ? route('public.courses.show', $slug) : route('public.courses.index') }}"
               class="group block bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                {{-- Cover image --}}
                <div class="relative h-44 bg-gradient-to-br from-brandBeige-200 to-brandGold-200 overflow-hidden">
                    @if($image)
                        <img src="{{ $image }}" alt="{{ $title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="w-16 h-16 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/></svg>
                        </div>
                    @endif
                    <span class="absolute top-3 left-3 text-xs font-bold px-2.5 py-1 rounded-full {{ $statusBg }}">
                        {{ $status === 'open' ? '● Open' : '◷ Upcoming' }}
                    </span>
                    @if($seats !== null && $seats <= 5 && $seats > 0)
                        <span class="absolute top-3 right-3 text-xs font-bold px-2.5 py-1 rounded-full bg-red-100 text-red-700">
                            {{ $seats }} left!
                        </span>
                    @elseif($seats === 0)
                        <span class="absolute top-3 right-3 text-xs font-bold px-2.5 py-1 rounded-full bg-gray-200 text-gray-600">
                            Full
                        </span>
                    @endif
                </div>
                {{-- Content --}}
                <div class="p-5">
                    <h3 class="font-bold text-gray-900 text-lg leading-snug mb-1 group-hover:text-brandMaroon-700 transition-colors">{{ $title }}</h3>
                    <p class="text-sm text-gray-500 line-clamp-2 mb-4">{{ $shortDesc }}</p>
                    {{-- Meta row --}}
                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <div class="text-sm text-gray-500">
                            @if($startDate)
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    {{ $startDate->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">Date TBC</span>
                            @endif
                        </div>
                        <div class="text-right">
                            @if($fee && $fee > 0)
                                <span class="font-bold text-brandMaroon-700">{{ number_format($fee, 0) }} <span class="text-xs font-normal text-gray-500">MVR</span></span>
                            @else
                                <span class="font-bold text-green-600 text-sm">Free</span>
                            @endif
                        </div>
                    </div>
                </div>
            </a>
            @empty
            <div class="col-span-3 text-center py-12 text-gray-400">
                <p>No open courses right now. <a href="{{ route('public.admissions.create') }}" class="text-brandMaroon-600 hover:underline">Leave your details</a> and we'll notify you.</p>
            </div>
            @endforelse
        </div>

        <div class="text-center mt-10">
            <a href="{{ route('public.admissions.create') }}"
               class="inline-flex items-center gap-2 bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white font-bold px-8 py-3.5 rounded-xl shadow transition-all hover:scale-105">
                Apply Now — It's Free
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     3. WHY AKURU?
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <span class="text-brandMaroon-600 font-semibold text-sm uppercase tracking-wider">Why choose us</span>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-1">Why Akuru Institute?</h2>
            <p class="text-gray-500 mt-3 max-w-xl mx-auto">Trusted by hundreds of families across the Maldives for quality Islamic education.</p>
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
            <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                <div class="w-12 h-12 bg-brandMaroon-100 text-brandMaroon-700 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                </div>
                <h3 class="font-bold text-gray-900 mb-2">{{ $f['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     4. STATS STRIP
══════════════════════════════════════════════════════════════ --}}
@if(isset($stats) && ($stats['courses'] > 0 || $stats['students'] > 0))
<section class="bg-brandMaroon-700 text-white py-12">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-8 text-center">
            @if($stats['students'] > 0)
            <div>
                <div class="text-4xl sm:text-5xl font-bold text-brandGold-400" x-data x-intersect.once="$el.textContent = '{{ number_format($stats['students']) }}+'">0</div>
                <div class="text-white/70 mt-1 text-sm">Students enrolled</div>
            </div>
            @endif
            @if($stats['courses'] > 0)
            <div>
                <div class="text-4xl sm:text-5xl font-bold text-brandGold-400">{{ $stats['courses'] }}</div>
                <div class="text-white/70 mt-1 text-sm">Courses offered</div>
            </div>
            @endif
            <div>
                <div class="text-4xl sm:text-5xl font-bold text-brandGold-400">5+</div>
                <div class="text-white/70 mt-1 text-sm">Years of service</div>
            </div>
            <div>
                <div class="text-4xl sm:text-5xl font-bold text-brandGold-400">100%</div>
                <div class="text-white/70 mt-1 text-sm">Qualified teachers</div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     5. NEWS & EVENTS
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20 bg-brandBeige-100">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-12">
            {{-- News --}}
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Latest News</h2>
                    <a href="{{ route('public.news.index') }}" class="text-sm text-brandMaroon-600 hover:underline font-medium">All news →</a>
                </div>
                <div class="space-y-4">
                    @forelse($posts as $post)
                    @php $postSlug = $post->slug ?? $post->id ?? null; @endphp
                    <a href="{{ $postSlug ? route('public.news.show', $postSlug) : route('public.news.index') }}" class="group flex gap-4 p-4 rounded-xl hover:bg-brandBeige-50 transition-colors border border-transparent hover:border-brandBeige-200">
                        <div class="w-16 h-16 rounded-lg bg-brandMaroon-100 shrink-0 overflow-hidden">
                            @if(isset($post->cover_image) && $post->cover_image)
                                <img src="{{ asset('storage/'.$post->cover_image) }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-brandMaroon-400">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 transition-colors leading-snug line-clamp-2">{{ $post->title }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($post->published_at ?? now())->format('d M Y') }}</p>
                        </div>
                    </a>
                    @empty
                    <p class="text-gray-400 text-sm py-4">No news yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Events --}}
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Upcoming Events</h2>
                    <a href="{{ route('public.events.index') }}" class="text-sm text-brandGold-700 hover:underline font-medium">All events →</a>
                </div>
                <div class="space-y-4">
                    @forelse($events as $event)
                    @php $evSlug = $event->slug ?? $event->id ?? null; @endphp
                    <a href="{{ $evSlug ? route('public.events.show', $evSlug) : route('public.events.index') }}" class="group flex gap-4 p-4 rounded-xl border border-gray-100 hover:border-brandGold-200 hover:bg-brandGold-50/50 transition-all">
                        @php $evDate = \Carbon\Carbon::parse($event->start_date ?? now()); @endphp
                        <div class="w-14 shrink-0 text-center">
                            <div class="bg-brandMaroon-600 text-white rounded-t-lg py-1 text-xs font-bold uppercase">{{ $evDate->format('M') }}</div>
                            <div class="border border-t-0 border-gray-200 rounded-b-lg py-1.5 text-2xl font-bold text-gray-900 leading-none">{{ $evDate->format('d') }}</div>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 transition-colors leading-snug">{{ $event->title }}</p>
                            <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                {{ $event->location ?? 'Akuru Institute' }}
                            </p>
                        </div>
                    </a>
                    @empty
                    <p class="text-gray-400 text-sm py-4">No upcoming events.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     6. EDUCATIONAL ARTICLES
══════════════════════════════════════════════════════════════ --}}
@if(isset($articles) && $articles->count() > 0)
<section class="py-14 bg-brandBeige-50 border-t border-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <span class="text-brandMaroon-600 font-semibold text-sm uppercase tracking-wider">Learn & grow</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">Educational Articles</h2>
            </div>
            <a href="{{ route('public.articles.index') }}" class="hidden sm:inline-block text-brandMaroon-600 hover:underline text-sm font-medium">View all →</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($articles as $article)
            <a href="{{ route('public.articles.show', $article->slug) }}" class="group block bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                @if($article->cover_image)
                <div class="aspect-video overflow-hidden">
                    <img src="{{ asset('storage/'.$article->cover_image) }}" alt="{{ $article->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                </div>
                @endif
                <div class="p-5">
                    <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 transition-colors leading-snug mb-1">{{ $article->title }}</p>
                    <p class="text-sm text-gray-500 line-clamp-2">{{ $article->excerpt }}</p>
                    <p class="text-xs text-gray-400 mt-3">{{ $article->published_at->format('d M Y') }}</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     7. TESTIMONIALS
══════════════════════════════════════════════════════════════ --}}
@if(isset($testimonials) && $testimonials->isNotEmpty())
<section class="py-14 sm:py-20 bg-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <span class="text-brandMaroon-600 font-semibold text-sm uppercase tracking-wider">Student voices</span>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-1">What Our Students Say</h2>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
            @foreach($testimonials as $t)
            <div class="bg-brandBeige-50 border border-brandBeige-200 p-6 rounded-2xl relative">
                <div class="text-brandGold-400 text-5xl leading-none font-serif absolute top-4 right-5 opacity-40">"</div>
                <p class="text-gray-700 leading-relaxed mb-5 relative z-10">"{{ $t->quote }}"</p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-brandMaroon-200 flex items-center justify-center text-brandMaroon-700 font-bold shrink-0">
                        {{ strtoupper(substr($t->name ?? 'A', 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 text-sm">{{ $t->name }}</p>
                        @if($t->role)<p class="text-xs text-gray-500">{{ $t->role }}</p>@endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     8. UPCOMING INTAKES
══════════════════════════════════════════════════════════════ --}}
@php
    $upcomingCourses = $courses->filter(fn($c) => is_object($c) && in_array($c->status ?? '', ['open','upcoming']) && ($c->start_date ?? null));
@endphp
@if($upcomingCourses->count() > 0)
<section class="py-12 bg-brandMaroon-50 border-t border-brandMaroon-100">
    <div class="container mx-auto px-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
            <h2 class="text-xl font-bold text-brandMaroon-900">📅 Upcoming Intakes</h2>
            <a href="{{ route('public.admissions.create') }}" class="text-sm font-semibold text-brandMaroon-700 hover:underline">Reserve your place →</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($upcomingCourses->take(6) as $uc)
            @php $ucDate = \Carbon\Carbon::parse($uc->start_date); @endphp
            <a href="{{ $uc->slug ? route('public.courses.show', $uc->slug) : route('public.courses.index') }}"
               class="flex items-center gap-4 bg-white rounded-xl px-4 py-3 shadow-sm hover:shadow-md border border-brandMaroon-100 hover:border-brandMaroon-300 transition-all">
                <div class="text-center shrink-0 w-12">
                    <div class="text-xs font-bold text-brandMaroon-600 uppercase">{{ $ucDate->format('M') }}</div>
                    <div class="text-2xl font-bold text-brandMaroon-900 leading-none">{{ $ucDate->format('d') }}</div>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">{{ $uc->title }}</p>
                    @if($uc->fee > 0)
                        <p class="text-xs text-gray-500">{{ number_format($uc->fee, 0) }} MVR</p>
                    @else
                        <p class="text-xs text-green-600">Free</p>
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

{{-- ══════════════════════════════════════════════════════════════
     9. FINAL CTA
══════════════════════════════════════════════════════════════ --}}
<section class="bg-gradient-to-br from-brandMaroon-800 to-brandMaroon-900 text-white py-16 sm:py-20">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold mb-4">Ready to Start Your Journey?</h2>
        <p class="text-white/75 text-lg mb-8 max-w-xl mx-auto">Join hundreds of students who chose Akuru Institute for their Islamic education. Apply today — it only takes a minute.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('public.admissions.create') }}"
               class="inline-flex items-center justify-center gap-2 bg-brandGold-500 hover:bg-brandGold-400 text-brandMaroon-900 font-bold px-8 py-4 rounded-xl text-lg shadow-lg transition-all hover:scale-105">
                Apply Now
            </a>
            <a href="{{ route('public.contact.create') }}"
               class="inline-flex items-center justify-center gap-2 border-2 border-white/40 text-white hover:bg-white/10 font-semibold px-8 py-4 rounded-xl text-lg transition-all">
                Contact Us
            </a>
        </div>
    </div>
</section>

@endsection
