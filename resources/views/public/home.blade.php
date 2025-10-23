@extends('public.layouts.public')

@section('title', $title)
@section('description', $description)

@section('content')
<!-- Hero Section -->
<section class="bg-gradient-to-r from-brandBlue-600 to-brandBlue-800 text-white py-20">
    <div class="container mx-auto px-4">
        <div class="text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">{{ $title }}</h1>
            <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto">{{ $description }}</p>
            <div class="space-x-4">
                <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
                   class="bg-white text-brandBlue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    {{ __('public.Apply Now') }}
                </a>
                <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
                   class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-brandBlue-600 transition-colors">
                    {{ __('public.View Courses') }}
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Courses Section -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">{{ __('public.Our Courses') }}</h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">{{ __('public.Choose from our comprehensive range of Islamic education programs') }}</p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-8">
            @foreach($courses as $course)
            <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="h-48 bg-cover bg-center" style="background-image: url('{{ asset('storage/courses/' . $course->image) }}')"></div>
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">{{ $course->name }}</h3>
                    <p class="text-gray-600 mb-4">{{ $course->description }}</p>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-brandBlue-600 font-medium">{{ $course->duration }}</span>
                        <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
                           class="text-brandBlue-600 hover:text-brandBlue-700 font-medium">
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
<section class="py-16">
    <div class="container mx-auto px-4">
        <div class="grid md:grid-cols-2 gap-12">
            <!-- News -->
            <div>
                <h2 class="text-3xl font-bold text-gray-800 mb-6">{{ __('public.Latest News') }}</h2>
                <div class="space-y-6">
                    @foreach($posts as $post)
                    <div class="border-l-4 border-brandBlue-600 pl-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $post->title }}</h3>
                        <p class="text-gray-600 mb-2">{{ Str::limit($post->content, 100) }}</p>
                        <span class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($post->date)->format('M d, Y') }}</span>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('public.news.index', app()->getLocale()) }}" 
                   class="inline-block mt-4 text-brandBlue-600 hover:text-brandBlue-700 font-medium">
                    {{ __('public.View All News') }} →
                </a>
            </div>
            
            <!-- Events -->
            <div>
                <h2 class="text-3xl font-bold text-gray-800 mb-6">{{ __('public.Upcoming Events') }}</h2>
                <div class="space-y-6">
                    @foreach($events as $event)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $event->title }}</h3>
                        <div class="flex items-center text-sm text-gray-600">
                            <span class="mr-4">📅 {{ \Carbon\Carbon::parse($event->date)->format('M d, Y') }}</span>
                            <span>📍 {{ $event->location }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('public.events.index', app()->getLocale()) }}" 
                   class="inline-block mt-4 text-brandBlue-600 hover:text-brandBlue-700 font-medium">
                    {{ __('public.View All Events') }} →
                </a>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="bg-brandBlue-600 text-white py-16">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl md:text-4xl font-bold mb-4">{{ __('public.Ready to Start Your Journey?') }}</h2>
        <p class="text-xl mb-8 max-w-2xl mx-auto">{{ __('public.Join thousands of students who have chosen Akuru Institute for their Islamic education') }}</p>
        <div class="space-x-4">
            <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
               class="bg-white text-brandBlue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                {{ __('public.Apply Now') }}
            </a>
            <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
               class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-brandBlue-600 transition-colors">
                {{ __('public.Contact Us') }}
            </a>
        </div>
    </div>
</section>
@endsection