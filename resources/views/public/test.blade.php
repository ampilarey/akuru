@extends('public.layouts.public')

@section('title', 'Test Page - ' . config('app.name'))

@section('content')
<div class="bg-white py-16">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl font-bold text-brandGray-900 mb-8">Website Diagnostic Test</h1>
            
            <!-- Basic Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-brandBlue-50 rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-brandGray-900 mb-4">System Information</h2>
                    <ul class="space-y-2 text-brandGray-700">
                        <li><strong>App Name:</strong> {{ config('app.name') }}</li>
                        <li><strong>App Environment:</strong> {{ config('app.env') }}</li>
                        <li><strong>App URL:</strong> {{ config('app.url') }}</li>
                        <li><strong>Current Locale:</strong> {{ app()->getLocale() }}</li>
                        <li><strong>Available Locales:</strong> {{ implode(', ', array_keys(config('laravellocalization.supportedLocales'))) }}</li>
                    </ul>
                </div>
                
                <div class="bg-brandGray-50 rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-brandGray-900 mb-4">Database Status</h2>
                    <ul class="space-y-2 text-brandGray-700">
                        @try
                            <li><strong>Database Connection:</strong> 
                                @if(DB::connection()->getPdo())
                                    <span class="text-green-600">✓ Connected</span>
                                @else
                                    <span class="text-red-600">✗ Failed</span>
                                @endif
                            </li>
                            <li><strong>Pages Table:</strong> 
                                @if(Schema::hasTable('pages'))
                                    <span class="text-green-600">✓ Exists</span>
                                @else
                                    <span class="text-red-600">✗ Missing</span>
                                @endif
                            </li>
                            <li><strong>Posts Table:</strong> 
                                @if(Schema::hasTable('posts'))
                                    <span class="text-green-600">✓ Exists</span>
                                @else
                                    <span class="text-red-600">✗ Missing</span>
                                @endif
                            </li>
                            <li><strong>Courses Table:</strong> 
                                @if(Schema::hasTable('courses'))
                                    <span class="text-green-600">✓ Exists</span>
                                @else
                                    <span class="text-red-600">✗ Missing</span>
                                @endif
                            </li>
                        @catch(Exception $e)
                            <li><strong>Database Error:</strong> <span class="text-red-600">{{ $e->getMessage() }}</span></li>
                        @endtry
                    </ul>
                </div>
            </div>

            <!-- Model Tests -->
            <div class="bg-white border border-brandGray-200 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-brandGray-900 mb-4">Model Tests</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @try
                        <div class="text-center p-4 border rounded-lg">
                            <h3 class="font-semibold text-brandGray-900 mb-2">Pages</h3>
                            <p class="text-2xl font-bold text-brandBlue-600">{{ \App\Models\Page::count() }}</p>
                            <p class="text-sm text-brandGray-600">Total Pages</p>
                        </div>
                    @catch(Exception $e)
                        <div class="text-center p-4 border rounded-lg border-red-200 bg-red-50">
                            <h3 class="font-semibold text-red-900 mb-2">Pages</h3>
                            <p class="text-sm text-red-600">Error: {{ $e->getMessage() }}</p>
                        </div>
                    @endtry

                    @try
                        <div class="text-center p-4 border rounded-lg">
                            <h3 class="font-semibold text-brandGray-900 mb-2">Posts</h3>
                            <p class="text-2xl font-bold text-brandBlue-600">{{ \App\Models\Post::count() }}</p>
                            <p class="text-sm text-brandGray-600">Total Posts</p>
                        </div>
                    @catch(Exception $e)
                        <div class="text-center p-4 border rounded-lg border-red-200 bg-red-50">
                            <h3 class="font-semibold text-red-900 mb-2">Posts</h3>
                            <p class="text-sm text-red-600">Error: {{ $e->getMessage() }}</p>
                        </div>
                    @endtry

                    @try
                        <div class="text-center p-4 border rounded-lg">
                            <h3 class="font-semibold text-brandGray-900 mb-2">Courses</h3>
                            <p class="text-2xl font-bold text-brandBlue-600">{{ \App\Models\Course::count() }}</p>
                            <p class="text-sm text-brandGray-600">Total Courses</p>
                        </div>
                    @catch(Exception $e)
                        <div class="text-center p-4 border rounded-lg border-red-200 bg-red-50">
                            <h3 class="font-semibold text-red-900 mb-2">Courses</h3>
                            <p class="text-sm text-red-600">Error: {{ $e->getMessage() }}</p>
                        </div>
                    @endtry
                </div>
            </div>

            <!-- Route Tests -->
            <div class="bg-white border border-brandGray-200 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-brandGray-900 mb-4">Route Tests</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @php
                        $routes = [
                            'public.home' => 'Home Page',
                            'public.courses.index' => 'Courses Page',
                            'public.news.index' => 'News Page',
                            'public.contact.create' => 'Contact Page',
                            'public.admissions.create' => 'Admissions Page',
                        ];
                    @endphp
                    
                    @foreach($routes as $route => $name)
                        <div class="flex items-center justify-between p-3 border rounded-lg">
                            <span class="text-brandGray-700">{{ $name }}</span>
                            @try
                                @php $url = route($route, app()->getLocale()); @endphp
                                <a href="{{ $url }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm">
                                    Test Route
                                </a>
                            @catch(Exception $e)
                                <span class="text-red-600 text-sm">Error: {{ $e->getMessage() }}</span>
                            @endtry
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Language Test -->
            <div class="bg-white border border-brandGray-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-brandGray-900 mb-4">Language Test</h2>
                <div class="space-y-4">
                    <p><strong>Current Language:</strong> {{ app()->getLocale() }}</p>
                    <p><strong>Test Translation:</strong> {{ __('public.Akuru Institute') }}</p>
                    
                    <div class="flex space-x-4">
                        <a href="{{ LaravelLocalization::getLocalizedURL('en') }}" 
                           class="px-4 py-2 bg-brandBlue-600 text-white rounded-md hover:bg-brandBlue-700">
                            English
                        </a>
                        <a href="{{ LaravelLocalization::getLocalizedURL('ar') }}" 
                           class="px-4 py-2 bg-brandBlue-600 text-white rounded-md hover:bg-brandBlue-700">
                            العربية
                        </a>
                        <a href="{{ LaravelLocalization::getLocalizedURL('dv') }}" 
                           class="px-4 py-2 bg-brandBlue-600 text-white rounded-md hover:bg-brandBlue-700">
                            ދިވެހި
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
