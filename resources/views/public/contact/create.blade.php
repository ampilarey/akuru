@extends('public.layouts.public')

@section('title', __('public.Contact Us') . ' - ' . config('app.name'))

@section('content')
<div class="bg-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-brandGray-900 mb-4">
                    {{ __('public.Contact Us') }}
                </h1>
                <p class="text-xl text-brandGray-600 max-w-2xl mx-auto">
                    {{ __('public.contact_description') }}
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Contact Form -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-semibold text-brandGray-900 mb-6">
                        {{ __('public.Send us a Message') }}
                    </h2>
                    
                    @if(session('success'))
                        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('public.contact.store', app()->getLocale()) }}" class="space-y-6">
                        @csrf
                        
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Full Name') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" 
                                   value="{{ old('name') }}" required
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
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

                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Phone Number') }}
                            </label>
                            <input type="tel" name="phone" id="phone" 
                                   value="{{ old('phone') }}"
                                   class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Message -->
                        <div>
                            <label for="message" class="block text-sm font-medium text-brandGray-700 mb-2">
                                {{ __('public.Message') }} <span class="text-red-500">*</span>
                            </label>
                            <textarea name="message" id="message" rows="6" required
                                      class="w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500"
                                      placeholder="{{ __('public.contact_message_placeholder') }}">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit" class="btn-primary w-full">
                                {{ __('public.Send Message') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Contact Information -->
                <div class="space-y-8">
                    <!-- Contact Details -->
                    <div class="bg-brandMaroon-50 rounded-lg p-8">
                        <h2 class="text-2xl font-semibold text-brandGray-900 mb-6">
                            {{ __('public.Get in Touch') }}
                        </h2>
                        
                        <div class="space-y-6">
                            <!-- Address -->
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-brandGray-900 mb-1">{{ __('public.Address') }}</h3>
                                    <p class="text-brandGray-600">{{ __('public.address') }}</p>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-brandGray-900 mb-1">{{ __('public.Phone') }}</h3>
                                    <p class="text-brandGray-600">+960 797 2434</p>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-brandMaroon-600 text-white rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-brandGray-900 mb-1">{{ __('public.Email') }}</h3>
                                    <p class="text-brandGray-600">info@akuru.edu.mv</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Office Hours -->
                    <div class="bg-brandGray-50 rounded-lg p-8">
                        <h3 class="text-xl font-semibold text-brandGray-900 mb-4">
                            {{ __('public.Office Hours') }}
                        </h3>
                        <div class="space-y-2 text-brandGray-600">
                            <div class="flex justify-between">
                                <span>{{ __('public.Sunday - Thursday') }}</span>
                                <span>8:00 AM - 4:00 PM</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ __('public.Friday') }}</span>
                                <span>8:00 AM - 12:00 PM</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ __('public.Saturday') }}</span>
                                <span>{{ __('public.Closed') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="bg-white border border-brandGray-200 rounded-lg p-8">
                        <h3 class="text-xl font-semibold text-brandGray-900 mb-4">
                            {{ __('public.Quick Links') }}
                        </h3>
                        <div class="space-y-3">
                            <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
                               class="block text-brandMaroon-600 hover:text-brandMaroon-800 transition-colors">
                                {{ __('public.Apply for Admission') }}
                            </a>
                            <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
                               class="block text-brandMaroon-600 hover:text-brandMaroon-800 transition-colors">
                                {{ __('public.View Courses') }}
                            </a>
                            <a href="{{ route('public.news.index', app()->getLocale()) }}" 
                               class="block text-brandMaroon-600 hover:text-brandMaroon-800 transition-colors">
                                {{ __('public.Latest News') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
