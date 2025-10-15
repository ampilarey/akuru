@extends('public.layouts.public')

@section('title', __('public.Welcome to Akuru Institute'))
@section('description', __('public.Learn Quran, Arabic, and Islamic Studies'))

@section('content')
<!-- Hero Section -->
<section class="bg-gradient-to-br from-brandBlue-50 to-brandBlue-100 py-16 lg:py-24">
    <div class="container mx-auto px-4">
        @if($heroBanners->count() > 0)
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <h1 class="text-4xl lg:text-6xl font-bold text-brandBlue-900 leading-tight">
                        {{ $heroBanners->first()->title }}
                    </h1>
                    @if($heroBanners->first()->subtitle)
                        <p class="text-xl text-brandGray-700 leading-relaxed">
                            {{ $heroBanners->first()->subtitle }}
                        </p>
                    @endif
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
                           class="btn-primary inline-flex items-center justify-center px-8 py-4 text-lg">
                            {{ __('public.View Courses') }}
                            <svg class="w-5 h-5 ml-2 rtl:ml-0 rtl:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                            </svg>
                        </a>
                        <a href="{{ route('public.about', app()->getLocale()) }}" 
                           class="btn-secondary inline-flex items-center justify-center px-8 py-4 text-lg">
                            {{ __('public.Learn More') }}
                        </a>
                    </div>
                </div>
                <div class="relative">
                    <div class="aspect-[4/3] bg-white rounded-2xl shadow-2xl overflow-hidden">
                        @if($heroBanners->first()->image_path)
                            <img src="{{ Storage::url($heroBanners->first()->image_path) }}" 
                                 alt="{{ $heroBanners->first()->title }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-brandBlue-100 to-brandBlue-200 flex items-center justify-center">
                                <svg class="w-32 h-32 text-brandBlue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2L3 7v11a2 2 0 002 2h10a2 2 0 002-2V7l-7-5z"/>
                                    <path d="M8 15h4v-4H8v4z"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="text-center max-w-4xl mx-auto">
                <h1 class="text-4xl lg:text-6xl font-bold text-brandBlue-900 mb-6">
                    {{ __('public.Welcome to Akuru Institute') }}
                </h1>
                <p class="text-xl text-brandGray-700 mb-8 leading-relaxed">
                    {{ __('public.Learn Quran, Arabic, and Islamic Studies') }}
                </p>
                <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
                   class="btn-primary inline-flex items-center px-8 py-4 text-lg">
                    {{ __('public.View Courses') }}
                    <svg class="w-5 h-5 ml-2 rtl:ml-0 rtl:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                </a>
            </div>
        @endif
    </div>
</section>

<!-- Featured Courses -->
@if($featuredCourses->count() > 0)
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl lg:text-4xl font-bold text-brandGray-900 mb-4">
                {{ __('public.Featured Courses') }}
            </h2>
            <p class="text-lg text-brandGray-600 max-w-2xl mx-auto">
                {{ __('public.Join thousands of students in their journey to learn Islam') }}
            </p>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            @foreach($featuredCourses->take(6) as $course)
                <div class="card hover:shadow-lg transition-shadow duration-300">
                    @if($course->cover_image)
                        <img src="{{ Storage::url($course->cover_image) }}" 
                             alt="{{ $course->title }}"
                             class="w-full h-48 object-cover rounded-t-lg">
                    @endif
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-brandBlue-600 font-medium">
                                {{ $course->category->name }}
                            </span>
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                {{ $course->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ __('public.' . ucfirst($course->status)) }}
                            </span>
                        </div>
                        <h3 class="text-xl font-semibold text-brandGray-900 mb-2">
                            {{ $course->title }}
                        </h3>
                        <p class="text-brandGray-600 mb-4 line-clamp-3">
                            {{ $course->short_desc }}
                        </p>
                        <div class="flex items-center justify-between">
                            @if($course->fee)
                                <span class="text-lg font-bold text-brandBlue-600">
                                    MVR {{ number_format($course->fee, 2) }}
                                </span>
                            @else
                                <span class="text-lg font-bold text-green-600">
                                    {{ __('public.Free') }}
                                </span>
                            @endif
                            <a href="{{ route('public.courses.show', [app()->getLocale(), $course->slug]) }}" 
                               class="text-brandBlue-600 hover:text-brandBlue-700 font-medium">
                                {{ __('public.Learn More') }} →
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="text-center">
            <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
               class="btn-secondary">
                {{ __('public.View All') }} {{ __('public.Courses') }}
            </a>
        </div>
    </div>
</section>
@endif

<!-- Latest News -->
@if($latestPosts->count() > 0)
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl lg:text-4xl font-bold text-brandGray-900 mb-4">
                {{ __('public.Latest News') }}
            </h2>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            @foreach($latestPosts as $post)
                <article class="card hover:shadow-lg transition-shadow duration-300">
                    @if($post->cover_image)
                        <img src="{{ Storage::url($post->cover_image) }}" 
                             alt="{{ $post->title }}"
                             class="w-full h-48 object-cover rounded-t-lg">
                    @endif
                    <div class="p-6">
                        <div class="flex items-center gap-4 mb-3 text-sm text-brandGray-500">
                            <time datetime="{{ $post->published_at->format('Y-m-d') }}">
                                {{ $post->published_at->format('M d, Y') }}
                            </time>
                            <span>{{ $post->author->name }}</span>
                        </div>
                        <h3 class="text-xl font-semibold text-brandGray-900 mb-2">
                            {{ $post->title }}
                        </h3>
                        <p class="text-brandGray-600 mb-4 line-clamp-3">
                            {{ $post->summary }}
                        </p>
                        <a href="{{ route('public.news.show', [app()->getLocale(), $post->slug]) }}" 
                           class="text-brandBlue-600 hover:text-brandBlue-700 font-medium">
                            {{ __('public.Read More') }} →
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
        
        <div class="text-center">
            <a href="{{ route('public.news.index', app()->getLocale()) }}" 
               class="btn-secondary">
                {{ __('public.View All') }} {{ __('public.News') }}
            </a>
        </div>
    </div>
</section>
@endif

<!-- Testimonials -->
@if($testimonials->count() > 0)
<section class="py-16 bg-brandBlue-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl lg:text-4xl font-bold text-brandGray-900 mb-4">
                {{ __('public.What Our Students Say') }}
            </h2>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($testimonials as $testimonial)
                <div class="card text-center">
                    @if($testimonial->avatar_path)
                        <img src="{{ Storage::url($testimonial->avatar_path) }}" 
                             alt="{{ $testimonial->name }}"
                             class="w-16 h-16 rounded-full mx-auto mb-4 object-cover">
                    @else
                        <div class="w-16 h-16 rounded-full bg-brandBlue-100 flex items-center justify-center mx-auto mb-4">
                            <span class="text-brandBlue-600 font-semibold text-lg">
                                {{ substr($testimonial->name, 0, 1) }}
                            </span>
                        </div>
                    @endif
                    <blockquote class="text-brandGray-700 italic mb-4">
                        "{{ $testimonial->quote }}"
                    </blockquote>
                    <div>
                        <div class="font-semibold text-brandGray-900">{{ $testimonial->name }}</div>
                        @if($testimonial->role)
                            <div class="text-sm text-brandGray-500">{{ $testimonial->role }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- FAQs -->
@if($faqs->count() > 0)
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl lg:text-4xl font-bold text-brandGray-900 mb-4">
                {{ __('public.Frequently Asked Questions') }}
            </h2>
        </div>
        
        <div class="max-w-3xl mx-auto space-y-4">
            @foreach($faqs as $faq)
                <div class="card">
                    <details class="group">
                        <summary class="flex items-center justify-between cursor-pointer font-medium text-brandGray-900 p-2">
                            {{ $faq->question }}
                            <svg class="w-5 h-5 text-brandGray-500 transition-transform group-open:rotate-180" 
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </summary>
                        <div class="mt-4 text-brandGray-600 leading-relaxed">
                            {{ $faq->answer }}
                        </div>
                    </details>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Call to Action -->
<section class="py-16 bg-brandBlue-600 text-white">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl lg:text-4xl font-bold mb-4">
            {{ __('public.Ready to Start Learning?') }}
        </h2>
        <p class="text-xl text-brandBlue-100 mb-8 max-w-2xl mx-auto">
            {{ __('public.Join thousands of students in their journey to learn Islam') }}
        </p>
        <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
           class="inline-flex items-center px-8 py-4 bg-white text-brandBlue-600 rounded-lg font-semibold hover:bg-brandBlue-50 transition-colors">
            {{ __('public.Apply Now') }}
            <svg class="w-5 h-5 ml-2 rtl:ml-0 rtl:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
            </svg>
        </a>
    </div>
</section>
@endsection
