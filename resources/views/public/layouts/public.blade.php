<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar','dv']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', config('app.name', 'Akuru Institute'))</title>
    <meta name="description" content="@yield('description', __('public.Learn Quran, Arabic, and Islamic Studies'))">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @stack('styles')
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 font-sans antialiased">
    <x-public.nav />
    
    <main class="min-h-[70vh]">
        @yield('content')
    </main>
    
    <x-public.footer />
    
    @stack('scripts')
</body>
</html>
