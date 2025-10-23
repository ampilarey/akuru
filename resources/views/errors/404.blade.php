@extends('public.layouts.public')

@section('title', __('public.Page Not Found') . ' - ' . config('app.name'))
@section('description', __('public.The page you are looking for could not be found.'))

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 text-center">
        <div>
            <h1 class="text-9xl font-bold text-indigo-600">404</h1>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                {{ __('public.Page Not Found') }}
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('public.The page you are looking for could not be found.') }}
            </p>
        </div>
        
        <div class="mt-8 space-y-4">
            <a href="{{ route('home') }}" 
               class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('public.Go Home') }}
            </a>
            
            <div class="text-sm text-gray-500">
                <p>{{ __('public.Need help?') }}</p>
                <a href="{{ route('contact.create') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                    {{ __('public.Contact Us') }}
                </a>
            </div>
        </div>
        
        <!-- Popular Links -->
        <div class="mt-8">
            <h3 class="text-sm font-medium text-gray-900 mb-4">{{ __('public.Popular Pages') }}</h3>
            <div class="grid grid-cols-1 gap-2">
                <a href="{{ route('courses.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                    {{ __('public.Courses') }}
                </a>
                <a href="{{ route('news.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                    {{ __('public.News') }}
                </a>
                <a href="{{ route('events.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                    {{ __('public.Events') }}
                </a>
                <a href="{{ route('admissions.create') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                    {{ __('public.Admissions') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
