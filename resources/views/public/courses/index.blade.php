@extends('public.layouts.public')

@section('title', __('public.Courses'))
@section('description', __('public.Explore our Quranic and Islamic studies courses'))

@section('content')
<!-- Page Header -->
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-brandMaroon-900 mb-4">{{ __('public.Our Courses') }}</h1>
        <p class="text-xl text-brandGray-700">{{ __('public.Explore our comprehensive Islamic education programs') }}</p>
    </div>
</section>

<!-- Search and Filters -->
<section class="bg-white border-b py-6">
    <div class="container mx-auto px-4">
        <!-- Search Bar -->
        <div class="mb-6">
            <form method="GET" action="{{ route('public.courses.index') }}" class="max-w-2xl mx-auto">
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="{{ __('public.Search courses...') }}"
                           class="form-input w-full pl-10 pr-4 py-3 text-lg">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <button type="submit" class="absolute inset-y-0 right-0 px-4 bg-brandMaroon-600 text-white rounded-r-md hover:bg-brandMaroon-700">
                        {{ __('public.Search') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Advanced Filters -->
        <form method="GET" action="{{ route('public.courses.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Category') }}</label>
                    <select name="category" class="form-input w-full">
                        <option value="">{{ __('public.All Categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->slug }}" {{ request('category') == $category->slug ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Status') }}</label>
                    <select name="status" class="form-input w-full">
                        <option value="">{{ __('public.All Statuses') }}</option>
                        <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>{{ __('public.Open') }}</option>
                        <option value="upcoming" {{ request('status') == 'upcoming' ? 'selected' : '' }}>{{ __('public.Upcoming') }}</option>
                    </select>
                </div>

                <!-- Language Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Language') }}</label>
                    <select name="language" class="form-input w-full">
                        <option value="">{{ __('public.All Languages') }}</option>
                        <option value="en" {{ request('language') == 'en' ? 'selected' : '' }}>English</option>
                        <option value="ar" {{ request('language') == 'ar' ? 'selected' : '' }}>العربية</option>
                        <option value="dv" {{ request('language') == 'dv' ? 'selected' : '' }}>ދިވެހި</option>
                        <option value="mixed" {{ request('language') == 'mixed' ? 'selected' : '' }}>{{ __('public.Mixed') }}</option>
                    </select>
                </div>

                <!-- Level Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Level') }}</label>
                    <select name="level" class="form-input w-full">
                        <option value="">{{ __('public.All Levels') }}</option>
                        <option value="kids" {{ request('level') == 'kids' ? 'selected' : '' }}>{{ __('public.Kids') }}</option>
                        <option value="youth" {{ request('level') == 'youth' ? 'selected' : '' }}>{{ __('public.Youth') }}</option>
                        <option value="adult" {{ request('level') == 'adult' ? 'selected' : '' }}>{{ __('public.Adult') }}</option>
                        <option value="all" {{ request('level') == 'all' ? 'selected' : '' }}>{{ __('public.All Ages') }}</option>
                    </select>
                </div>
            </div>

            <!-- Additional Filters Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Enrollment Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Enrollment') }}</label>
                    <select name="enrollment" class="form-input w-full">
                        <option value="">{{ __('public.All Enrollment') }}</option>
                        <option value="open" {{ request('enrollment') == 'open' ? 'selected' : '' }}>{{ __('public.Enrollment Open') }}</option>
                        <option value="upcoming" {{ request('enrollment') == 'upcoming' ? 'selected' : '' }}>{{ __('public.Starting Soon') }}</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('public.Sort By') }}</label>
                    <select name="sort" class="form-input w-full">
                        <option value="default" {{ request('sort') == 'default' ? 'selected' : '' }}>{{ __('public.Default') }}</option>
                        <option value="title" {{ request('sort') == 'title' ? 'selected' : '' }}>{{ __('public.Title A-Z') }}</option>
                        <option value="fee_low" {{ request('sort') == 'fee_low' ? 'selected' : '' }}>{{ __('public.Price: Low to High') }}</option>
                        <option value="fee_high" {{ request('sort') == 'fee_high' ? 'selected' : '' }}>{{ __('public.Price: High to Low') }}</option>
                        <option value="start_date" {{ request('sort') == 'start_date' ? 'selected' : '' }}>{{ __('public.Start Date') }}</option>
                        <option value="featured" {{ request('sort') == 'featured' ? 'selected' : '' }}>{{ __('public.Featured First') }}</option>
                    </select>
                </div>

                <!-- Featured Only -->
                <div class="flex items-end">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="featured" 
                               value="1" 
                               {{ request('featured') ? 'checked' : '' }}
                               class="rounded border-gray-300 text-brandMaroon-600 shadow-sm focus:border-brandMaroon-300 focus:ring focus:ring-brandMaroon-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">{{ __('public.Featured Only') }}</span>
                    </label>
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-primary">
                    {{ __('public.Apply Filters') }}
                </button>
                <a href="{{ route('public.courses.index') }}" class="btn-secondary">
                    {{ __('public.Clear All') }}
                </a>
            </div>
        </form>
    </div>
</section>

<!-- Courses Grid -->
<section class="py-12">
    <div class="container mx-auto px-4">
        @if($courses->count() > 0)
            <div class="grid lg:grid-cols-4 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-3">
                    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($courses as $course)
                    <article class="card overflow-hidden hover:shadow-lg transition-all duration-300 group relative">
                        <!-- Featured Badge -->
                        @if($course->is_featured)
                            <div class="absolute top-4 right-4 z-10">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-brandGold-500 text-brandMaroon-900">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                    {{ __('public.Featured') }}
                                </span>
                            </div>
                        @endif

                        <!-- Course Image -->
                        <div class="relative overflow-hidden">
                            @if($course->cover_image)
                                <x-public.picture
                                    :src="$course->cover_image"
                                    :alt="$course->title"
                                    class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300"
                                    loading="lazy"
                                />
                            @else
                                <div class="w-full h-48 bg-gradient-to-br from-brandBeige-100 to-brandBeige-200 flex items-center justify-center">
                                    <svg class="w-16 h-16 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                </div>
                            @endif
                            
                            <!-- Status Overlay -->
                            <div class="absolute top-4 left-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    {{ $course->status === 'open' ? 'bg-green-100 text-green-800' : 
                                       ($course->status === 'upcoming' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    <span class="w-2 h-2 rounded-full mr-1 {{ $course->status === 'open' ? 'bg-green-500' : 
                                        ($course->status === 'upcoming' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                                    {{ ucfirst($course->status) }}
                                </span>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Category Badge -->
                            @if($course->category)
                                <span class="inline-block px-3 py-1 text-xs font-medium bg-brandMaroon-100 text-brandMaroon-800 rounded-full mb-3">
                                    {{ $course->category->name }}
                                </span>
                            @endif

                            <!-- Course Title -->
                            <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-brandMaroon-600 transition-colors">
                                {{ $course->title }}
                            </h3>

                            <!-- Short Description -->
                            <p class="text-gray-600 mb-4 line-clamp-3">{{ $course->short_desc }}</p>

                            <!-- Course Meta -->
                            <div class="space-y-2 mb-4">
                                <!-- Duration -->
                                @if($course->duration_weeks)
                                    <div class="flex items-center gap-2 text-sm text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>{{ $course->duration_text }}</span>
                                    </div>
                                @endif

                                <!-- Language & Level -->
                                <div class="flex items-center gap-4 text-sm text-gray-600">
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
                                    <div class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        <span class="capitalize">{{ $course->level }}</span>
                                    </div>
                                </div>

                                <!-- Available Seats -->
                                @if($course->available_seats !== null)
                                    <div class="flex items-center gap-2 text-sm {{ $course->available_seats > 5 ? 'text-green-600' : ($course->available_seats > 0 ? 'text-yellow-600' : 'text-red-600') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        <span>
                                            @if($course->available_seats > 0)
                                                {{ $course->available_seats }} {{ __('public.seats available') }}
                                            @else
                                                {{ __('public.Waitlist only') }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <!-- Fee -->
                            <div class="mb-4">
                                @if($course->fee)
                                    <div class="text-2xl font-bold text-brandMaroon-600">
                                        {{ $course->formatted_fee }}
                                    </div>
                                @else
                                    <div class="text-lg font-semibold text-green-600">
                                        {{ __('public.Free') }}
                                    </div>
                                @endif
                            </div>

                            <!-- CTA Button -->
                            @if($course->isFull())
                                <div class="flex gap-2">
                                    <span class="flex-1 text-center py-2 px-3 text-sm bg-red-50 text-red-700 border border-red-200 rounded-lg font-medium">
                                        Fully booked
                                    </span>
                                    <a href="{{ LaravelLocalization::localizeURL(route('public.courses.show', $course->slug)) }}"
                                       class="py-2 px-3 text-sm border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">
                                        Details
                                    </a>
                                </div>
                            @else
                                <a href="{{ LaravelLocalization::localizeURL(route('public.courses.show', $course->slug)) }}"
                                   class="btn-primary w-full text-center group-hover:bg-brandMaroon-700 transition-colors">
                                    {{ __('public.View Details') }}
                                </a>
                            @endif
                        </div>
                    </article>
                    @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-8">
                        {{ $courses->links() }}
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <!-- Featured Courses -->
                    @if($featuredCourses->count() > 0)
                        <div class="card mb-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 text-brandGold-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                {{ __('public.Featured Courses') }}
                            </h3>
                            <div class="space-y-4">
                                @foreach($featuredCourses as $featuredCourse)
                                    <div class="border-l-4 border-brandGold-500 pl-4">
                                        <h4 class="font-semibold text-gray-900 mb-1">
                                            <a href="{{ LaravelLocalization::localizeURL(route('public.courses.show', $featuredCourse->slug)) }}" 
                                               class="hover:text-brandMaroon-600 transition-colors">
                                                {{ $featuredCourse->title }}
                                            </a>
                                        </h4>
                                        <p class="text-sm text-gray-600 mb-2">{{ Str::limit($featuredCourse->short_desc, 80) }}</p>
                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                            <span class="capitalize">{{ $featuredCourse->level }}</span>
                                            @if($featuredCourse->fee)
                                                <span class="font-semibold text-brandMaroon-600">{{ $featuredCourse->formatted_fee }}</span>
                                            @else
                                                <span class="font-semibold text-green-600">{{ __('public.Free') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Quick Stats -->
                    <div class="card mb-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('public.Course Statistics') }}</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">{{ __('public.Total Courses') }}</span>
                                <span class="font-semibold text-brandMaroon-600">{{ $courses->total() }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">{{ __('public.Open for Enrollment') }}</span>
                                <span class="font-semibold text-green-600">{{ $courses->where('status', 'open')->count() }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">{{ __('public.Starting Soon') }}</span>
                                <span class="font-semibold text-yellow-600">{{ $courses->where('status', 'upcoming')->count() }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Categories -->
                    <div class="card">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('public.Course Categories') }}</h3>
                        <div class="space-y-2">
                            @foreach($categories as $category)
                                <a href="{{ route('public.courses.index') }}?category={{ urlencode($category->slug) }}" 
                                   class="block px-3 py-2 text-sm text-gray-700 hover:bg-brandBeige-100 hover:text-brandMaroon-600 rounded transition-colors">
                                    {{ $category->name }}
                                    <span class="float-right text-xs text-gray-500">
                                        ({{ $courses->where('course_category_id', $category->id)->count() }})
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- No Courses Found -->
            <div class="text-center py-12">
                <svg class="w-24 h-24 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <h3 class="text-2xl font-semibold text-gray-700 mb-2">{{ __('public.No Courses Found') }}</h3>
                <p class="text-gray-500 mb-6">{{ __('public.Please try different filters or check back later') }}</p>
                <a href="{{ route('public.courses.index') }}" class="btn-secondary">
                    {{ __('public.Clear Filters') }}
                </a>
            </div>
        @endif
    </div>
</section>

<!-- Call to Action -->
<section class="bg-brandMaroon-600 py-12">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">{{ __('public.Ready to Start Learning?') }}</h2>
        <p class="text-xl text-brandBeige-100 mb-6">{{ __('public.Apply now and begin your Islamic education journey') }}</p>
        <a href="{{ route('public.admissions.create', app()->getLocale()) }}" class="btn-secondary bg-white text-brandMaroon-600 hover:bg-brandMaroon-50">
            {{ __('public.Submit an inquiry') }}
        </a>
    </div>
</section>
@endsection

