@extends('public.layouts.public')

@section('title', 'Language Test - ' . config('app.name'))

@section('content')
<div class="bg-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl font-bold text-brandGray-900 mb-8">Language Switching Test</h1>
            
            <!-- Current Locale Info -->
            <div class="bg-brandBlue-50 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-brandGray-900 mb-4">Current Locale Information</h2>
                <ul class="space-y-2 text-brandGray-700">
                    <li><strong>app()->getLocale():</strong> <code class="bg-white px-2 py-1 rounded">{{ app()->getLocale() }}</code></li>
                    <li><strong>LaravelLocalization::getCurrentLocale():</strong> <code class="bg-white px-2 py-1 rounded">{{ LaravelLocalization::getCurrentLocale() }}</code></li>
                    <li><strong>Current URL:</strong> <code class="bg-white px-2 py-1 rounded">{{ url()->current() }}</code></li>
                    <li><strong>Session Locale:</strong> <code class="bg-white px-2 py-1 rounded">{{ session('locale', 'not set') }}</code></li>
                </ul>
            </div>

            <!-- Language Links Test -->
            <div class="bg-white border border-brandGray-200 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-brandGray-900 mb-4">Language Switcher URLs</h2>
                <div class="space-y-4">
                    @php
                        $locales = ['en', 'ar', 'dv'];
                    @endphp
                    
                    @foreach($locales as $locale)
                        <div class="border rounded-lg p-4">
                            <h3 class="font-semibold text-brandGray-900 mb-2">
                                {{ $locale === 'en' ? 'English' : ($locale === 'ar' ? 'العربية' : 'ދިވެހި') }}
                                @if(app()->getLocale() === $locale)
                                    <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded">CURRENT</span>
                                @endif
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div>
                                    <strong>getLocalizedURL:</strong> 
                                    <code class="bg-brandGray-100 px-2 py-1 rounded">{{ LaravelLocalization::getLocalizedURL($locale) }}</code>
                                </div>
                                <div>
                                    <a href="{{ LaravelLocalization::getLocalizedURL($locale) }}" 
                                       class="inline-flex items-center px-4 py-2 bg-brandBlue-600 text-white rounded-md hover:bg-brandBlue-700">
                                        Switch to {{ $locale === 'en' ? 'English' : ($locale === 'ar' ? 'العربية' : 'ދިވެހި') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Test Translations -->
            <div class="bg-white border border-brandGray-200 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-brandGray-900 mb-4">Translation Test</h2>
                <div class="space-y-2">
                    <p><strong>public.Akuru Institute:</strong> {{ __('public.Akuru Institute') }}</p>
                    <p><strong>public.Courses:</strong> {{ __('public.Courses') }}</p>
                    <p><strong>public.News:</strong> {{ __('public.News') }}</p>
                    <p><strong>public.Contact:</strong> {{ __('public.Contact') }}</p>
                    <p><strong>public.Welcome to Akuru Institute:</strong> {{ __('public.Welcome to Akuru Institute') }}</p>
                </div>
            </div>

            <!-- Route Test -->
            <div class="bg-white border border-brandGray-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-brandGray-900 mb-4">Route Test</h2>
                <div class="space-y-2">
                    @php
                        $testRoutes = [
                            'public.home' => 'Home',
                            'public.courses.index' => 'Courses',
                            'public.news.index' => 'News',
                            'public.contact.create' => 'Contact',
                        ];
                    @endphp
                    
                    @foreach($testRoutes as $routeName => $label)
                        <div class="flex items-center justify-between p-2 border-b">
                            <span>{{ $label }}:</span>
                            <code class="bg-brandGray-100 px-2 py-1 rounded text-sm">
                                {{ route($routeName, app()->getLocale()) }}
                            </code>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Back to Home -->
            <div class="mt-8">
                <a href="{{ route('public.home', app()->getLocale()) }}" 
                   class="inline-flex items-center px-6 py-3 bg-brandBlue-600 text-white rounded-lg hover:bg-brandBlue-700">
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
