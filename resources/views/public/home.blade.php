@extends('public.layouts.public')

@section('title', $text['title'] ?? __('public.Welcome to Akuru Institute'))
@section('description', $text['desc'] ?? __('public.Learn Quran, Arabic, and Islamic Studies'))

@section('content')
<!-- Hero Section -->
<section class="bg-gradient-to-r from-brandMaroon-600 to-brandMaroon-900 text-white py-12 sm:py-20">
    <div class="container mx-auto px-4">
        <div class="text-center">
            <h1 class="text-3xl sm:text-4xl md:text-6xl font-bold mb-4 sm:mb-6 leading-tight drop-shadow-lg">{{ $text['title'] ?? __('public.Welcome to Akuru Institute') }}</h1>
            <p class="text-lg sm:text-xl md:text-2xl mb-6 sm:mb-8 max-w-3xl mx-auto leading-relaxed drop-shadow-md">{{ $text['desc'] ?? __('public.Learn Quran, Arabic, and Islamic Studies in the Maldives') }}</p>
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
                <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
                   class="w-full sm:w-auto bg-brandGold-600 text-brandMaroon-900 px-6 sm:px-8 py-3 rounded-lg font-bold hover:bg-brandGold-500 transition-colors text-center text-base sm:text-lg shadow-lg">
                    {{ __('public.Apply Now') }}
                </a>
                <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
                   class="w-full sm:w-auto border-2 border-brandGold-600 text-brandGold-600 bg-white/10 backdrop-blur-sm px-6 sm:px-8 py-3 rounded-lg font-semibold hover:bg-brandGold-600 hover:text-brandMaroon-900 transition-colors text-center text-base sm:text-lg">
                    {{ __('public.View Courses') }}
                </a>
            </div>
        </div>
    </div>
</section>

@if(isset($stats) && ($stats['courses'] > 0 || $stats['students'] > 0 || $stats['teachers'] > 0))
<!-- Stats Section -->
<section class="py-12 bg-white border-b">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
            @if($stats['students'] > 0)
            <div>
                <div class="text-4xl font-bold text-brandMaroon-600">{{ number_format($stats['students']) }}+</div>
                <div class="text-gray-600 mt-1">{{ __('public.Students Enrolled') }}</div>
            </div>
            @endif
            @if($stats['courses'] > 0)
            <div>
                <div class="text-4xl font-bold text-brandMaroon-600">{{ $stats['courses'] }}</div>
                <div class="text-gray-600 mt-1">{{ __('public.Courses') }}</div>
            </div>
            @endif
            @if($stats['teachers'] > 0)
            <div>
                <div class="text-4xl font-bold text-brandMaroon-600">{{ $stats['teachers'] }}+</div>
                <div class="text-gray-600 mt-1">{{ __('public.Qualified Teachers') }}</div>
            </div>
            @endif
        </div>
    </div>
</section>
@endif

<!-- Courses Section -->
<section class="py-12 sm:py-16 bg-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="text-center mb-8 sm:mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">{{ __('public.Our Courses') }}</h2>
            <p class="text-base sm:text-lg text-gray-700 max-w-2xl mx-auto leading-relaxed">{{ __('public.Choose from our comprehensive range of Islamic education programs') }}</p>
        </div>
        
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
            @foreach($courses as $course)
            <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow border border-gray-200">
                @php
                    $courseImage = null;
                    if (is_object($course) && isset($course->cover_image) && $course->cover_image) {
                        $courseImage = asset('storage/' . $course->cover_image);
                    }
                    $courseTitle = is_object($course) ? ($course->title ?? $course->name ?? '') : '';
                    $courseDesc = is_object($course) ? ($course->short_desc ?? $course->description ?? '') : '';
                    $courseDuration = is_object($course) ? ($course->duration_text ?? $course->duration ?? '') : '';
                    $courseSlug = is_object($course) ? ($course->slug ?? $course->id ?? '') : '';
                @endphp
                <div class="h-48 bg-cover bg-center bg-gray-200" style="background-image: url('{{ $courseImage }}')"></div>
                <div class="p-5 sm:p-6">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">{{ $courseTitle }}</h3>
                    <p class="text-sm sm:text-base text-gray-700 mb-4 leading-relaxed">{{ Str::limit($courseDesc, 100) }}</p>
                    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                        <span class="text-xs sm:text-sm text-brandMaroon-700 font-bold bg-brandGold-100 px-3 py-1 rounded-full">{{ $courseDuration }}</span>
                        <a href="{{ $courseSlug ? LaravelLocalization::localizeURL(route('public.courses.show', $courseSlug)) : route('public.courses.index', app()->getLocale()) }}" 
                           class="text-sm sm:text-base text-brandMaroon-600 hover:text-brandGold-600 font-semibold">
                            {{ __('public.Learn More') }} →
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- News & Events Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid md:grid-cols-2 gap-12">
            <!-- News -->
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-6">{{ __('public.Latest News') }}</h2>
                <div class="space-y-6">
                    @foreach($posts as $post)
                    <div class="border-l-4 border-brandMaroon-600 pl-4 bg-brandBeige-100 p-4 rounded-r-lg">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">{{ $post->title }}</h3>
                        <p class="text-sm sm:text-base text-gray-700 mb-2 leading-relaxed">{{ Str::limit($post->body ?? $post->content ?? '', 100) }}</p>
                        <span class="text-xs sm:text-sm text-gray-600 font-medium">
                            {{ \Carbon\Carbon::parse($post->published_at ?? $post->date ?? now())->format('M d, Y') }}
                        </span>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('public.news.index', app()->getLocale()) }}" 
                   class="inline-block mt-6 text-brandMaroon-600 hover:text-brandGold-600 font-semibold text-base">
                    {{ __('public.View All News') }} →
                </a>
            </div>
            
            <!-- Events -->
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-6">{{ __('public.Upcoming Events') }}</h2>
                <div class="space-y-6">
                    @foreach($events as $event)
                    <div class="bg-brandGold-50 border border-brandGold-200 p-4 rounded-lg">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">{{ $event->title }}</h3>
                        <div class="flex flex-col sm:flex-row sm:items-center text-sm text-gray-700 gap-2">
                            <span class="font-medium">📅 {{ \Carbon\Carbon::parse($event->start_date ?? $event->date ?? now())->format('M d, Y') }}</span>
                            <span class="font-medium">📍 {{ $event->location ?? __('public.Main Campus') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('public.events.index', app()->getLocale()) }}" 
                   class="inline-block mt-6 text-brandGold-700 hover:text-brandMaroon-600 font-semibold text-base">
                    {{ __('public.View All Events') }} →
                </a>
            </div>
        </div>
    </div>
</section>

@if(isset($testimonials) && $testimonials->isNotEmpty())
<!-- Testimonials Section -->
<section class="py-16 bg-brandBeige-200">
    <div class="container mx-auto px-4">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8 text-center">{{ __('public.What Our Students Say') }}</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-5xl mx-auto">
            @foreach($testimonials as $testimonial)
            <div class="bg-white p-6 rounded-lg shadow-md">
                <p class="text-gray-700 italic mb-4">"{{ $testimonial->quote }}"</p>
                <div class="font-semibold text-brandMaroon-600">{{ $testimonial->name }}</div>
                <div class="text-sm text-gray-500">{{ $testimonial->role }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- CTA Section -->
<section class="bg-gradient-to-r from-brandMaroon-700 to-brandMaroon-900 text-white py-12 sm:py-16">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-4 sm:mb-6 drop-shadow-lg">{{ __('public.Ready to Start Your Journey?') }}</h2>
        <p class="text-base sm:text-lg md:text-xl mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed drop-shadow-md">{{ __('public.Join thousands of students who have chosen Akuru Institute for their Islamic education') }}</p>
        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
            <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
               class="w-full sm:w-auto bg-brandGold-600 text-brandMaroon-900 px-6 sm:px-8 py-3 rounded-lg font-bold hover:bg-brandGold-500 transition-colors text-center shadow-lg">
                {{ __('public.Apply Now') }}
            </a>
            <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
               class="w-full sm:w-auto border-2 border-brandGold-600 text-brandGold-600 bg-white/10 backdrop-blur-sm px-6 sm:px-8 py-3 rounded-lg font-semibold hover:bg-brandGold-600 hover:text-brandMaroon-900 transition-colors text-center">
                {{ __('public.Contact Us') }}
            </a>
        </div>
    </div>
</section>
@endsection
