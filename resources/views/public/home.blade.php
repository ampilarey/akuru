@extends('public.layouts.public')

@section('title', $title)
@section('description', $description)

@section('content')
<!-- Hero Section -->
<section class="bg-gradient-to-r from-blue-700 to-blue-900 text-white py-12 sm:py-20">
    <div class="container mx-auto px-4">
        <div class="text-center">
            <h1 class="text-3xl sm:text-4xl md:text-6xl font-bold mb-4 sm:mb-6 leading-tight drop-shadow-lg">{{ $title }}</h1>
            <p class="text-lg sm:text-xl md:text-2xl mb-6 sm:mb-8 max-w-3xl mx-auto leading-relaxed drop-shadow-md">{{ $description }}</p>
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
                <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
                   class="w-full sm:w-auto bg-white text-brandBlue-600 px-6 sm:px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors text-center text-base sm:text-lg">
                    {{ __('public.Apply Now') }}
                </a>
                <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
                   class="w-full sm:w-auto border-2 border-white text-white px-6 sm:px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-brandBlue-600 transition-colors text-center text-base sm:text-lg">
                    {{ __('public.View Courses') }}
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Courses Section -->
<section class="py-12 sm:py-16 bg-gray-100">
    <div class="container mx-auto px-4">
        <div class="text-center mb-8 sm:mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">{{ __('public.Our Courses') }}</h2>
            <p class="text-base sm:text-lg text-gray-700 max-w-2xl mx-auto leading-relaxed">{{ __('public.Choose from our comprehensive range of Islamic education programs') }}</p>
        </div>
        
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
            @foreach($courses as $course)
            <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow border border-gray-200">
                <div class="h-48 bg-cover bg-center bg-gray-200" style="background-image: url('{{ asset('storage/courses/' . $course->image) }}')"></div>
                <div class="p-5 sm:p-6">
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2">{{ $course->name }}</h3>
                    <p class="text-sm sm:text-base text-gray-700 mb-4 leading-relaxed">{{ $course->description }}</p>
                    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                        <span class="text-xs sm:text-sm text-blue-700 font-semibold bg-blue-50 px-3 py-1 rounded-full">{{ $course->duration }}</span>
                        <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
                           class="text-sm sm:text-base text-blue-700 hover:text-blue-800 font-semibold">
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
                    <div class="border-l-4 border-blue-600 pl-4 bg-gray-50 p-4 rounded-r-lg">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">{{ $post->title }}</h3>
                        <p class="text-sm sm:text-base text-gray-700 mb-2 leading-relaxed">{{ Str::limit($post->content, 100) }}</p>
                        <span class="text-xs sm:text-sm text-gray-600 font-medium">{{ \Carbon\Carbon::parse($post->date)->format('M d, Y') }}</span>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('public.news.index', app()->getLocale()) }}" 
                   class="inline-block mt-6 text-blue-700 hover:text-blue-800 font-semibold text-base">
                    {{ __('public.View All News') }} →
                </a>
            </div>
            
            <!-- Events -->
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-6">{{ __('public.Upcoming Events') }}</h2>
                <div class="space-y-6">
                    @foreach($events as $event)
                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">{{ $event->title }}</h3>
                        <div class="flex flex-col sm:flex-row sm:items-center text-sm text-gray-700 gap-2">
                            <span class="font-medium">📅 {{ \Carbon\Carbon::parse($event->date)->format('M d, Y') }}</span>
                            <span class="font-medium">📍 {{ $event->location }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('public.events.index', app()->getLocale()) }}" 
                   class="inline-block mt-6 text-blue-700 hover:text-blue-800 font-semibold text-base">
                    {{ __('public.View All Events') }} →
                </a>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="bg-gradient-to-r from-blue-700 to-blue-900 text-white py-12 sm:py-16">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-4 sm:mb-6 drop-shadow-lg">{{ __('public.Ready to Start Your Journey?') }}</h2>
        <p class="text-base sm:text-lg md:text-xl mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed drop-shadow-md">{{ __('public.Join thousands of students who have chosen Akuru Institute for their Islamic education') }}</p>
        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
            <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
               class="w-full sm:w-auto bg-white text-blue-800 px-6 sm:px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors text-center shadow-lg">
                {{ __('public.Apply Now') }}
            </a>
            <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
               class="w-full sm:w-auto border-2 border-white text-white px-6 sm:px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-800 transition-colors text-center">
                {{ __('public.Contact Us') }}
            </a>
        </div>
    </div>
</section>
@endsection