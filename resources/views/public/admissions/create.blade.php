@extends('public.layouts.public')

@section('title', __('public.Admissions') . ' - ' . config('app.name'))

@section('content')
<div class="bg-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-brandGray-900 mb-4">
                    {{ __('public.Admissions') }}
                </h1>
                <p class="text-xl text-brandGray-600 max-w-2xl mx-auto">
                    {{ __('public.admission_description') }}
                </p>
            </div>

            <!-- Progress Indicator -->
            <div class="mb-8">
                <div class="flex items-center justify-between max-w-md mx-auto">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full bg-brandMaroon-600 text-white flex items-center justify-center font-bold">1</div>
                        <span class="text-xs mt-1 text-brandGray-600">{{ __('public.Personal Info') }}</span>
                    </div>
                    <div class="flex-1 h-1 bg-gray-200 mx-2"></div>
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full bg-brandMaroon-600 text-white flex items-center justify-center font-bold">2</div>
                        <span class="text-xs mt-1 text-brandGray-600">{{ __('public.Course') }}</span>
                    </div>
                    <div class="flex-1 h-1 bg-gray-200 mx-2"></div>
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full bg-brandMaroon-100 text-brandMaroon-600 flex items-center justify-center font-bold">3</div>
                        <span class="text-xs mt-1 text-brandGray-600">{{ __('public.Review') }}</span>
                    </div>
                </div>
            </div>

            <!-- Application Form -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <form method="POST" action="{{ route('public.admissions.store', app()->getLocale()) }}" class="space-y-6">
                    @csrf
                    
                    <!-- Course Selection -->
                    <div>
                        <label for="course_id" class="block text-sm font-medium text-brandGray-700 mb-2">
                            {{ __('public.Course of Interest') }}
                        </label>
                        <select name="course_id" id="course_id" class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            <option value="">{{ __('public.Select a course') }}</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" 
                                        {{ $selectedCourse && $selectedCourse->id === $course->id ? 'selected' : '' }}>
                                    {{ $course->title }} 
                                    @if($course->category)
                                        - {{ $course->category->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('course_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Personal Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Full Name') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="full_name" id="full_name" 
                                   value="{{ old('full_name') }}" required
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('full_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Phone Number') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" name="phone" id="phone" 
                                   value="{{ old('phone') }}" required
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Email Address') }}
                            </label>
                            <input type="email" name="email" id="email" 
                                   value="{{ old('email') }}"
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="guardian_name" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Guardian Name') }}
                            </label>
                            <input type="text" name="guardian_name" id="guardian_name" 
                                   value="{{ old('guardian_name') }}"
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('guardian_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Message -->
                    <div>
                        <label for="message" class="block text-sm font-medium text-brandGray-700 mb-2">
                            {{ __('public.Message') }}
                        </label>
                        <textarea name="message" id="message" rows="4" 
                                  class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500"
                                  placeholder="{{ __('public.admission_message_placeholder') }}">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Source -->
                    <div>
                        <label for="source" class="block text-sm font-medium text-brandGray-700 mb-2">
                            {{ __('public.How did you hear about us?') }}
                        </label>
                        <select name="source" id="source" class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            <option value="web">{{ __('public.Website') }}</option>
                            <option value="social">{{ __('public.Social Media') }}</option>
                            <option value="viber">Viber</option>
                            <option value="other">{{ __('public.Other') }}</option>
                        </select>
                    </div>

                    <!-- Submit Button -->
                    <div class="text-center">
                        <button type="submit" class="btn-primary px-8 py-3 text-lg">
                            {{ __('public.Submit Application') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Additional Information -->
            <div class="mt-12 bg-brandMaroon-50 rounded-lg p-8">
                <h3 class="text-2xl font-semibold text-brandGray-900 mb-4">
                    {{ __('public.Admission Process') }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-xl font-bold">1</span>
                        </div>
                        <h4 class="font-semibold text-brandGray-900 mb-2">{{ __('public.Submit Application') }}</h4>
                        <p class="text-brandGray-600 text-sm">{{ __('public.step1_description') }}</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-xl font-bold">2</span>
                        </div>
                        <h4 class="font-semibold text-brandGray-900 mb-2">{{ __('public.Review Process') }}</h4>
                        <p class="text-brandGray-600 text-sm">{{ __('public.step2_description') }}</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-xl font-bold">3</span>
                        </div>
                        <h4 class="font-semibold text-brandGray-900 mb-2">{{ __('public.Confirmation') }}</h4>
                        <p class="text-brandGray-600 text-sm">{{ __('public.step3_description') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
