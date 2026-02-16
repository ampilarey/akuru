<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar','dv']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- SEO Meta Tags -->
    <title>@yield('title', config('app.name', 'Akuru Institute'))</title>
    <meta name="description" content="@yield('description', __('public.Learn Quran, Arabic, and Islamic Studies'))">
    <meta name="keywords" content="@yield('keywords', 'Quran, Arabic, Islamic Studies, Education, Maldives, Akuru Institute')">
    <meta name="author" content="Akuru Institute">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="@yield('og_title', $title ?? config('app.name', 'Akuru Institute'))">
    <meta property="og:description" content="@yield('og_description', $description ?? __('public.Learn Quran, Arabic, and Islamic Studies'))">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.jpg'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="Akuru Institute">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', $title ?? config('app.name', 'Akuru Institute'))">
    <meta name="twitter:description" content="@yield('og_description', $description ?? __('public.Learn Quran, Arabic, and Islamic Studies'))">
    <meta name="twitter:image" content="@yield('og_image', asset('images/og-default.jpg'))">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="{{ url()->current() }}">
    
    <!-- Hreflang Tags for Multilingual SEO -->
    @foreach(config('laravellocalization.supportedLocales') as $localeCode => $properties)
        <link rel="alternate" hreflang="{{ $localeCode }}" href="{{ LaravelLocalization::getLocalizedURL($localeCode, null, [], true) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ LaravelLocalization::getLocalizedURL(config('app.locale'), null, [], true) }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Unregister any existing service workers from other projects -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for(let registration of registrations) {
                    registration.unregister();
                }
            });
            // Clear all caches
            if ('caches' in window) {
                caches.keys().then(function(names) {
                    for (let name of names) caches.delete(name);
                });
            }
        }
    </script>
    
    @stack('styles')
    
    <!-- Mobile-specific styles -->
    <style>
        /* Improve mobile touch targets */
        @media (max-width: 768px) {
            button, a, input, select, textarea {
                min-height: 44px;
                min-width: 44px;
            }
            
            /* Improve mobile scrolling */
            body {
                -webkit-overflow-scrolling: touch;
            }
            
            /* Prevent zoom on input focus */
            input[type="text"], input[type="email"], input[type="tel"], input[type="password"], textarea, select {
                font-size: 16px;
            }
        }
        
        /* Smooth mobile menu animation */
        #mobileMenu {
            transition: max-height 0.3s ease-in-out;
            overflow: hidden;
        }
        
        /* Improve mobile button spacing */
        @media (max-width: 640px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 font-sans antialiased">
    <x-public.nav />
    
    <main class="min-h-[70vh]">
        @yield('content')
    </main>
    
    <x-public.footer />

    {{-- WhatsApp Float Button --}}
    <a href="https://wa.me/9607972434" target="_blank" rel="noopener" class="fixed bottom-6 right-6 z-40 w-14 h-14 bg-green-500 hover:bg-green-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all hover:scale-110" aria-label="Contact us on WhatsApp">
        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </a>

    {{-- Google Analytics placeholder - Add your GA4 ID to .env as GA_MEASUREMENT_ID --}}
    @if(config('services.google.analytics_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google.analytics_id') }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ config('services.google.analytics_id') }}');</script>
    @endif

    {{-- Cookie Consent Banner --}}
    <div id="cookieConsent" class="fixed bottom-0 left-0 right-0 z-50 hidden bg-white border-t border-gray-200 shadow-lg p-4 md:p-6">
        <div class="container mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
            <p class="text-sm text-gray-700">
                {{ __('public.cookie_consent_message') }}
                <a href="{{ route('public.page.show', 'privacy-policy') }}" class="text-brandMaroon-600 hover:underline">{{ __('public.Privacy Policy') }}</a>
            </p>
            <div class="flex gap-3 shrink-0">
                <button onclick="acceptCookies()" class="px-4 py-2 bg-brandMaroon-600 text-white rounded-lg hover:bg-brandMaroon-700 text-sm font-medium">
                    {{ __('public.Accept') }}
                </button>
                <button onclick="dismissCookies()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                    {{ __('public.Decline') }}
                </button>
            </div>
        </div>
    </div>
    <script>
    (function(){
        if (!localStorage.getItem('cookieConsent')) {
            document.getElementById('cookieConsent').classList.remove('hidden');
        }
    })();
    function acceptCookies() {
        localStorage.setItem('cookieConsent', 'accepted');
        document.getElementById('cookieConsent').classList.add('hidden');
    }
    function dismissCookies() {
        localStorage.setItem('cookieConsent', 'declined');
        document.getElementById('cookieConsent').classList.add('hidden');
    }
    </script>
    
    @stack('scripts')
</body>
</html>
