@extends('public.layouts.public')

@section('title', __('public.Courses'))
@section('description', __('public.Explore our Quranic and Islamic studies courses'))

@section('content')
<!-- Page Header -->
<section class="bg-gradient-to-br from-brandBlue-50 to-brandBlue-100 py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-brandBlue-900 mb-4">{{ __('public.Our Courses') }}</h1>
        <p class="text-xl text-brandGray-700">{{ __('public.Explore our comprehensive Islamic education programs') }}</p>
    </div>
</section>

<!-- Filters -->
<section class="bg-white border-b py-6">
    <div class="container mx-auto px-4">
        <form method="GET" action="{{ route('public.courses.index', app()->getLocale()) }}" class="flex flex-wrap gap-4 items-end">
            <!-- Category Filter -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Category') }}</label>
                <select name="category" class="form-input w-full">
                    <option value="">{{ __('public.All Categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Status') }}</label>
                <select name="status" class="form-input w-full">
                    <option value="">{{ __('public.All Statuses') }}</option>
                    <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>{{ __('public.Open') }}</option>
                    <option value="upcoming" {{ request('status') == 'upcoming' ? 'selected' : '' }}>{{ __('public.Upcoming') }}</option>
                </select>
            </div>

            <!-- Language Filter -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Language') }}</label>
                <select name="language" class="form-input w-full">
                    <option value="">{{ __('public.All Languages') }}</option>
                    <option value="en" {{ request('language') == 'en' ? 'selected' : '' }}>English</option>
                    <option value="ar" {{ request('language') == 'ar' ? 'selected' : '' }}>العربية</option>
                    <option value="dv" {{ request('language') == 'dv' ? 'selected' : '' }}>ދިވެހި</option>
                    <option value="mixed" {{ request('language') == 'mixed' ? 'selected' : '' }}>{{ __('public.Mixed') }}</option>
                </select>
            </div>

            <!-- Apply Button -->
            <div>
                <button type="submit" class="btn-primary">
                    {{ __('public.Filter') }}
                </button>
            </div>
        </form>
    </div>
</section>

<!-- Courses Grid -->
<section class="py-12">
    <div class="container mx-auto px-4">
        @if($courses->count() > 0)
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($courses as $course)
                    <article class="card overflow-hidden hover:shadow-lg transition-shadow duration-200">
                        <!-- Course Image -->
                        @if($course->cover_image)
                            <img src="{{ asset('storage/' . $course->cover_image) }}" 
                                 alt="{{ $course->title }}"
                                 class="w-full h-48 object-cover">
                        @else
                            <div class="w-full h-48 bg-gradient-to-br from-brandBlue-100 to-brandBlue-200 flex items-center justify-center">
                                <svg class="w-16 h-16 text-brandBlue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                        @endif

                        <div class="p-6">
                            <!-- Category Badge -->
                            @if($course->courseCategory)
                                <span class="inline-block px-3 py-1 text-xs font-medium bg-brandBlue-100 text-brandBlue-800 rounded-full mb-3">
                                    {{ $course->courseCategory->name }}
                                </span>
                            @endif

                            <!-- Course Title -->
                            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $course->title }}</h3>

                            <!-- Short Description -->
                            <p class="text-gray-600 mb-4 line-clamp-3">{{ $course->short_desc }}</p>

                            <!-- Course Meta -->
                            <div class="flex flex-wrap gap-4 text-sm text-gray-500 mb-4">
                                <!-- Language -->
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                                    </svg>
                                    <span>
                                        @if($course->language === 'en') English
                                        @elseif($course->language === 'ar') العربية
                                        @elseif($course->language === 'dv') ދިވެހި
                                        @else {{ __('public.Mixed') }}
                                        @endif
                                    </span>
                                </div>

                                <!-- Level -->
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    <span class="capitalize">{{ $course->level }}</span>
                                </div>

                                <!-- Status -->
                                <div class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full {{ $course->status === 'open' ? 'bg-green-500' : 'bg-yellow-500' }}"></span>
                                    <span class="capitalize">{{ $course->status }}</span>
                                </div>
                            </div>

                            <!-- Fee (if any) -->
                            @if($course->fee)
                                <div class="text-2xl font-bold text-brandBlue-600 mb-4">
                                    {{ number_format($course->fee, 2) }} MVR
                                </div>
                            @endif

                            <!-- CTA Button -->
                            <a href="{{ route('public.courses.show', [app()->getLocale(), $course->slug]) }}" 
                               class="btn-primary w-full text-center">
                                {{ __('public.View Details') }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-8">
                {{ $courses->links() }}
            </div>
        @else
            <!-- No Courses Found -->
            <div class="text-center py-12">
                <svg class="w-24 h-24 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <h3 class="text-2xl font-semibold text-gray-700 mb-2">{{ __('public.No Courses Found') }}</h3>
                <p class="text-gray-500 mb-6">{{ __('public.Please try different filters or check back later') }}</p>
                <a href="{{ route('public.courses.index', app()->getLocale()) }}" class="btn-secondary">
                    {{ __('public.Clear Filters') }}
                </a>
            </div>
        @endif
    </div>
</section>

<!-- Call to Action -->
<section class="bg-brandBlue-600 py-12">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">{{ __('public.Ready to Start Learning?') }}</h2>
        <p class="text-xl text-brandBlue-100 mb-6">{{ __('public.Apply now and begin your Islamic education journey') }}</p>
        <a href="{{ route('public.admissions.create', app()->getLocale()) }}" class="btn-secondary bg-white text-brandBlue-600 hover:bg-brandBlue-50">
            {{ __('public.Apply Now') }}
        </a>
    </div>
</section>
@endsection

