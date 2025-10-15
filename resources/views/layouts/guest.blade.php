<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' || app()->getLocale() === 'dv' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Akuru Institute') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @if(app()->getLocale() === 'ar')
        <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
        @endif

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gradient-to-br from-brandBlue-50 to-brandGray-50">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <!-- Language Switcher -->
            <div class="absolute top-4 right-4">
                <select id="language-switcher" class="form-input text-sm">
                    <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>English</option>
                    <option value="ar" {{ app()->getLocale() === 'ar' ? 'selected' : '' }}>العربية</option>
                    <option value="dv" {{ app()->getLocale() === 'dv' ? 'selected' : '' }}>ދިވެހި</option>
                </select>
            </div>

            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="w-20 h-20 bg-brandBlue-500 rounded-full flex items-center justify-center">
                        <span class="text-white text-2xl font-bold">A</span>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-brandBlue-500 mb-2">Akuru Institute</h1>
                <p class="text-brandGray-600">Islamic & Arabic Education</p>
            </div>

            <!-- Auth Form Container -->
            <div class="w-full sm:max-w-md px-6 py-8 bg-white shadow-xl rounded-lg border border-brandGray-200">
                {{ $slot }}
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center text-sm text-brandGray-500">
                <p>&copy; {{ date('Y') }} Akuru Institute. All rights reserved.</p>
            </div>
        </div>
    </body>
</html>
