@extends('public.layouts.public')

@section('title', __('public.Server Error') . ' - ' . config('app.name'))
@section('description', __('public.Something went wrong on our end. Please try again later.'))

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 text-center">
        <div>
            <h1 class="text-9xl font-bold text-red-600">500</h1>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                {{ __('public.Server Error') }}
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('public.Something went wrong on our end. Please try again later.') }}
            </p>
        </div>
        
        <div class="mt-8 space-y-4">
            <a href="{{ route('public.home', app()->getLocale()) }}" 
               class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('public.Go Home') }}
            </a>
            
            <button onclick="window.location.reload()" 
                    class="group relative w-full flex justify-center py-2 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('public.Try Again') }}
            </button>
            
            <div class="text-sm text-gray-500">
                <p>{{ __('public.Still having issues?') }}</p>
                <a href="{{ route('public.contact.create', app()->getLocale()) }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                    {{ __('public.Contact Support') }}
                </a>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="mt-8 bg-gray-100 rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-900 mb-2">{{ __('public.Get Help') }}</h3>
            <div class="text-sm text-gray-600 space-y-1">
                <p>{{ __('public.Email') }}: <a href="mailto:support@akuru.edu.mv" class="text-indigo-600 hover:text-indigo-500">support@akuru.edu.mv</a></p>
                <p>{{ __('public.Phone') }}: <a href="tel:+9601234567" class="text-indigo-600 hover:text-indigo-500">+960 123-4567</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
