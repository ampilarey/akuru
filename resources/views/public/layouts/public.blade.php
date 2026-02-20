<!DOCTYPE html>
<html lang="en" dir="ltr">
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
    
    <!-- Google Translate API -->
    <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" defer></script>

    <!-- Detect active GT language from cookie and load the right font BEFORE render -->
    <script>
    (function() {
      var m = document.cookie.match(/googtrans=\/en\/([a-z]{2,})/);
      var lang = m ? m[1] : 'en';
      var fontUrls = {
        ar: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap',
        dv: 'https://fonts.googleapis.com/css2?family=Noto+Sans+Thaana:wght@400;500;600;700&display=swap'
      };
      if (fontUrls[lang]) {
        document.documentElement.classList.add('gt-lang-' + lang);
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = fontUrls[lang];
        document.head.appendChild(link);
      }
    })();
    </script>

    <!-- Font overrides for translated languages — layout/direction unchanged -->
    <style>
      .gt-lang-ar * { font-family: 'Cairo', 'Noto Sans Arabic', Arial, sans-serif !important; letter-spacing: 0 !important; }
      .gt-lang-dv * { font-family: 'Noto Sans Thaana', 'MV Boli', sans-serif !important; letter-spacing: 0 !important; }
    </style>

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

    {{-- Sticky Mobile Bottom Bar (small screens only) --}}
    <nav class="fixed bottom-0 left-0 right-0 z-50 sm:hidden bg-white border-t border-gray-200 shadow-lg safe-area-bottom">
        <div class="grid grid-cols-5 divide-x divide-gray-100">
            <a href="{{ route('public.courses.index') }}"
               class="flex flex-col items-center justify-center py-2.5 gap-0.5 text-gray-600 hover:text-brandMaroon-600 active:bg-gray-50 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span class="text-xs leading-none">Courses</span>
            </a>
            <a href="{{ route('public.courses.index') }}"
               class="flex flex-col items-center justify-center py-2.5 gap-0.5 bg-brandMaroon-600 text-white hover:bg-brandMaroon-700 active:bg-brandMaroon-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span class="text-xs leading-none">Apply</span>
            </a>
            <a href="https://wa.me/{{ $siteSettings['whatsapp'] ?? '9607972434' }}" target="_blank" rel="noopener"
               class="flex flex-col items-center justify-center py-2.5 gap-0.5 text-green-600 hover:text-green-700 active:bg-gray-50 transition-colors">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                <span class="text-xs leading-none">WhatsApp</span>
            </a>
            <a href="tel:{{ $siteSettings['phone'] ?? '+9607972434' }}"
               class="flex flex-col items-center justify-center py-2.5 gap-0.5 text-gray-600 hover:text-brandMaroon-600 active:bg-gray-50 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <span class="text-xs leading-none">Call</span>
            </a>
            @auth
            <a href="{{ route('portal.dashboard') }}"
               class="flex flex-col items-center justify-center py-2.5 gap-0.5 text-gray-600 hover:text-brandMaroon-600 active:bg-gray-50 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span class="text-xs leading-none">My Portal</span>
            </a>
            @else
            <a href="{{ route('login') }}"
               class="flex flex-col items-center justify-center py-2.5 gap-0.5 text-gray-600 hover:text-brandMaroon-600 active:bg-gray-50 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                <span class="text-xs leading-none">Login</span>
            </a>
            @endauth
        </div>
    </nav>
    {{-- Padding so footer content doesn't hide behind sticky bar on mobile --}}
    <div class="sm:hidden h-16"></div>

    {{-- WhatsApp Float Button (hidden on mobile since bottom bar has it) --}}
    <a href="https://wa.me/{{ $siteSettings['whatsapp'] ?? '9607972434' }}" target="_blank" rel="noopener" class="hidden sm:flex fixed bottom-6 right-6 z-40 w-14 h-14 bg-green-500 hover:bg-green-600 text-white rounded-full items-center justify-center shadow-lg transition-all hover:scale-110" aria-label="Contact us on WhatsApp">
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
