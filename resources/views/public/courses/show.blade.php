@extends('public.layouts.public')

@section('title', $course->title)
@section('description', $course->short_desc)

@section('content')
<!-- Course Header -->
<section class="bg-gradient-to-br from-brandBlue-50 to-brandBlue-100 py-12">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-8 items-center">
            <!-- Course Info -->
            <div>
                <!-- Category Badge -->
                @if($course->courseCategory)
                    <span class="inline-block px-3 py-1 text-sm font-medium bg-brandBlue-600 text-white rounded-full mb-4">
                        {{ $course->courseCategory->name }}
                    </span>
                @endif

                <h1 class="text-4xl lg:text-5xl font-bold text-brandBlue-900 mb-4">{{ $course->title }}</h1>
                <p class="text-xl text-brandGray-700 mb-6">{{ $course->short_desc }}</p>

                <!-- Course Meta -->
                <div class="flex flex-wrap gap-6 mb-6">
                    <!-- Language -->
                    <div class="flex items-center gap-2 text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                        </svg>
                        <span class="font-medium">
                            @if($course->language === 'en') English
                            @elseif($course->language === 'ar') العربية
                            @elseif($course->language === 'dv') ދިވެހި
                            @else {{ __('public.Mixed') }}
                            @endif
                        </span>
                    </div>

                    <!-- Level -->
                    <div class="flex items-center gap-2 text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span class="font-medium capitalize">{{ $course->level }}</span>
                    </div>

                    <!-- Status -->
                    <div class="flex items-center gap-2 text-gray-700">
                        <span class="w-3 h-3 rounded-full {{ $course->status === 'open' ? 'bg-green-500' : ($course->status === 'upcoming' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                        <span class="font-medium capitalize">{{ $course->status }}</span>
                    </div>

                    <!-- Seats (if available) -->
                    @if($course->seats)
                        <div class="flex items-center gap-2 text-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <span class="font-medium">{{ $course->seats }} {{ __('public.Seats') }}</span>
                        </div>
                    @endif
                </div>

                <!-- Fee -->
                @if($course->fee)
                    <div class="mb-6">
                        <div class="text-3xl font-bold text-brandBlue-600">
                            {{ number_format($course->fee, 2) }} MVR
                        </div>
                    </div>
                @endif

                <!-- CTA Button -->
                <a href="{{ route('public.admissions.create', [app()->getLocale(), 'course' => $course->id]) }}" 
                   class="btn-primary inline-flex items-center px-8 py-4 text-lg">
                    {{ __('public.Apply for This Course') }}
                    <svg class="w-5 h-5 ml-2 rtl:ml-0 rtl:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                </a>
            </div>

            <!-- Course Image -->
            <div>
                @if($course->cover_image)
                    <img src="{{ asset('storage/' . $course->cover_image) }}" 
                         alt="{{ $course->title }}"
                         class="rounded-lg shadow-xl w-full h-auto">
                @else
                    <div class="aspect-[4/3] bg-gradient-to-br from-brandBlue-200 to-brandBlue-300 rounded-lg shadow-xl flex items-center justify-center">
                        <svg class="w-32 h-32 text-brandBlue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<!-- Course Details -->
<section class="py-12">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <!-- Description -->
                <div class="card p-6 mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ __('public.Course Description') }}</h2>
                    <div class="prose max-w-none text-gray-700">
                        {!! nl2br(e($course->body)) !!}
                    </div>
                </div>

                <!-- Schedule (if available) -->
                @if($course->schedule && is_array($course->schedule) && count($course->schedule) > 0)
                    <div class="card p-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ __('public.Course Schedule') }}</h2>
                        <div class="space-y-3">
                            @foreach($course->schedule as $item)
                                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded">
                                    <svg class="w-5 h-5 text-brandBlue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-gray-700">{{ $item }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Quick Info Card -->
                <div class="card p-6 mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">{{ __('public.Course Information') }}</h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('public.Status') }}</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium 
                                    {{ $course->status === 'open' ? 'bg-green-100 text-green-800' : ($course->status === 'upcoming' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    <span class="w-2 h-2 rounded-full {{ $course->status === 'open' ? 'bg-green-500' : ($course->status === 'upcoming' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                                    {{ ucfirst($course->status) }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('public.Language') }}</dt>
                            <dd class="mt-1 text-gray-900 font-medium">
                                @if($course->language === 'en') English
                                @elseif($course->language === 'ar') العربية
                                @elseif($course->language === 'dv') ދިވެހި
                                @else {{ __('public.Mixed') }}
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('public.Level') }}</dt>
                            <dd class="mt-1 text-gray-900 font-medium capitalize">{{ $course->level }}</dd>
                        </div>

                        @if($course->fee)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('public.Course Fee') }}</dt>
                                <dd class="mt-1 text-2xl font-bold text-brandBlue-600">{{ number_format($course->fee, 2) }} MVR</dd>
                            </div>
                        @endif

                        @if($course->seats)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('public.Available Seats') }}</dt>
                                <dd class="mt-1 text-gray-900 font-medium">{{ $course->seats }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <!-- CTA Card -->
                <div class="card p-6 bg-gradient-to-br from-brandBlue-50 to-brandBlue-100 border-brandBlue-200">
                    <h3 class="text-lg font-bold text-brandBlue-900 mb-3">{{ __('public.Interested in this course?') }}</h3>
                    <p class="text-sm text-brandGray-700 mb-4">{{ __('public.Submit your application and we will contact you soon') }}</p>
                    <a href="{{ route('public.admissions.create', [app()->getLocale(), 'course' => $course->id]) }}" 
                       class="btn-primary w-full text-center">
                        {{ __('public.Apply Now') }}
                    </a>
                </div>

                <!-- Contact Card -->
                <div class="card p-6 mt-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-3">{{ __('public.Have Questions?') }}</h3>
                    <p class="text-sm text-gray-600 mb-4">{{ __('public.Contact us for more information about this course') }}</p>
                    <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
                       class="btn-secondary w-full text-center text-sm">
                        {{ __('public.Contact Us') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Other Courses -->
<section class="bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-gray-900 mb-8">{{ __('public.Other Courses') }}</h2>
        <div class="grid md:grid-cols-3 gap-6">
            <!-- You can add related courses here if needed -->
            <p class="text-gray-500 col-span-3 text-center">{{ __('public.Explore more courses') }} 
                <a href="{{ route('public.courses.index', app()->getLocale()) }}" class="text-brandBlue-600 hover:underline">{{ __('public.here') }}</a>
            </p>
        </div>
    </div>
</section>
@endsection

