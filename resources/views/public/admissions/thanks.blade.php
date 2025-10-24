@extends('public.layouts.public')

@section('title', __('public.Thank You') . ' - ' . config('app.name'))

@section('content')
<div class="bg-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-2xl mx-auto text-center">
            <!-- Success Icon -->
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-8">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <!-- Success Message -->
            <h1 class="text-4xl font-bold text-brandGray-900 mb-4">
                {{ __('public.Thank You!') }}
            </h1>
            
            <p class="text-xl text-brandGray-600 mb-8">
                {{ __('public.admission_submitted_successfully') }}
            </p>

            <!-- What's Next -->
            <div class="bg-brandMaroon-50 rounded-lg p-8 mb-8">
                <h2 class="text-2xl font-semibold text-brandGray-900 mb-4">
                    {{ __('public.What happens next?') }}
                </h2>
                <ul class="text-left space-y-3 text-brandGray-700">
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-brandMaroon-600 mt-1 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        {{ __('public.admission_next_step1') }}
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-brandMaroon-600 mt-1 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        {{ __('public.admission_next_step2') }}
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-brandMaroon-600 mt-1 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        {{ __('public.admission_next_step3') }}
                    </li>
                </ul>
            </div>

            <!-- Contact Information -->
            <div class="bg-brandGray-50 rounded-lg p-6 mb-8">
                <h3 class="text-lg font-semibold text-brandGray-900 mb-3">
                    {{ __('public.Need Help?') }}
                </h3>
                <p class="text-brandGray-600 mb-4">
                    {{ __('public.contact_us_for_questions') }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="tel:+9607972434" class="flex items-center justify-center px-4 py-2 bg-white border border-brandGray-300 rounded-md hover:bg-brandGray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                        </svg>
                        +960 797 2434
                    </a>
                    <a href="mailto:info@akuru.edu.mv" class="flex items-center justify-center px-4 py-2 bg-white border border-brandGray-300 rounded-md hover:bg-brandGray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                        </svg>
                        info@akuru.edu.mv
                    </a>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('public.home', app()->getLocale()) }}" class="btn-primary">
                    {{ __('public.Back to Home') }}
                </a>
                <a href="{{ route('public.courses.index', app()->getLocale()) }}" class="btn-secondary">
                    {{ __('public.View Courses') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
