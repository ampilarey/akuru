<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
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

    @stack('head_meta')

    <!-- Canonical URL -->
    <link rel="canonical" href="{{ url()->current() }}">

    @foreach($hreflangLinks ?? [] as $link)
    <link rel="alternate" hreflang="{{ $link['hreflang'] }}" href="{{ $link['href'] }}">
    @endforeach

    @include('public.partials.json_ld', ['payload' => $organizationJsonLd ?? []])
    @stack('jsonld')
    
    <!-- Google Translate API -->
    <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" defer></script>

    <!-- Detect active GT language from cookie and load the right font BEFORE render -->
    <script>
    (function() {
      var m = document.cookie.match(/googtrans=\/en\/([a-z]{2,})/);
      var lang = m ? m[1] : 'en';
      var fontUrls = {
        ar: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap',
        dv: null  // Faruma is self-hosted via @font-face — no external link needed
      };
      if (fontUrls[lang] !== undefined) {
        document.documentElement.classList.add('gt-lang-' + lang);
        if (fontUrls[lang]) {
          var link = document.createElement('link');
          link.rel = 'stylesheet';
          link.href = fontUrls[lang];
          document.head.appendChild(link);
        }
      }
    })();
    </script>

    <!-- Font overrides for translated languages — layout/direction unchanged -->
    <style>
      @font-face {
        font-family: 'Faruma';
        src: url('{{ asset('fonts/Faruma.woff2') }}') format('woff2'),
             url('{{ asset('fonts/Faruma.woff') }}') format('woff');
        font-weight: 100 900;
        font-style: normal;
        font-display: swap;
        unicode-range: U+0780-U+07BF; /* Thaana block */
      }
      /* Arabic font */
      .gt-lang-ar, .gt-lang-ar body, .gt-lang-ar * {
        font-family: 'Cairo', 'Noto Sans Arabic', Arial, sans-serif !important;
        letter-spacing: 0 !important;
      }
      /* Dhivehi font — target everything including GT-injected <font> tags */
      .gt-lang-dv, .gt-lang-dv body, .gt-lang-dv * {
        font-family: 'Faruma', 'MV Boli', sans-serif !important;
        letter-spacing: 0 !important;
      }
    </style>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}?v=2">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon-32x32.png') }}?v=2">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa')
    
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

    @include('public.partials.prayer-banner-assets')

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
            <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}" target="_blank" rel="noopener"
               class="viber-tab flex flex-col items-center justify-center py-2.5 gap-0.5 active:bg-gray-50 transition-colors">
                <x-public.viber-icon class="w-5 h-5" />
                <span class="text-xs leading-none">Viber</span>
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

    {{-- Viber float button (hidden on mobile since the bottom bar has it).
         Brand colour #7360F2; plain scoped CSS because the deployed
         stylesheet is a committed Vite build. --}}
    <style>
        @keyframes viber-pulse {
            0%   { box-shadow: 0 0 0 0 rgba(115,96,242,.45); }
            70%  { box-shadow: 0 0 0 14px rgba(115,96,242,0); }
            100% { box-shadow: 0 0 0 0 rgba(115,96,242,0); }
        }
        .viber-float {
            background: linear-gradient(145deg, #8F7CF7, #7360F2 55%, #5B49D6);
            box-shadow: 0 10px 24px rgba(115,96,242,.42);
            animation: viber-pulse 2.5s ease-out infinite;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .viber-float:hover { transform: scale(1.08); box-shadow: 0 14px 30px rgba(115,96,242,.55); }
        .viber-float:active { transform: scale(1.02); }
        /* Respect the OS "reduce motion" setting — the pulse is decorative. */
        @media (prefers-reduced-motion: reduce) { .viber-float { animation: none; } }
        .viber-tab { color: #7360F2; }
        .viber-tab:hover { color: #5B49D6; }
    </style>
    <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}" target="_blank" rel="noopener"
       class="viber-float hidden sm:flex fixed bottom-6 right-6 z-40 w-14 h-14 text-white rounded-full items-center justify-center"
       aria-label="{{ __('public.Chat with us on Viber') }}"
       title="{{ __('public.Chat with us on Viber') }}">
        <x-public.viber-icon class="w-7 h-7" />
    </a>

    {{-- Google Analytics placeholder - Add your GA4 ID to .env as GA_MEASUREMENT_ID --}}
    @if(config('services.google.analytics_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google.analytics_id') }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ config('services.google.analytics_id') }}');</script>
    @endif

    <script>
    window.akuruFunnelUrl = @json(route('public.funnel.store'));
    window.akuruFunnel = function (name, courseId) {
        if (!name || !courseId) {
            return;
        }
        if (typeof gtag === 'function') {
            gtag('event', name, { course_id: courseId });
        }
        var tokenEl = document.querySelector('meta[name="csrf-token"]');
        var body = new URLSearchParams();
        body.set('name', String(name));
        body.set('course_id', String(courseId));
        if (tokenEl) {
            body.set('_token', tokenEl.getAttribute('content') || '');
        }
        var blob = new Blob([body.toString()], { type: 'application/x-www-form-urlencoded' });
        if (navigator.sendBeacon && window.akuruFunnelUrl) {
            navigator.sendBeacon(window.akuruFunnelUrl, blob);
            return;
        }
        fetch(window.akuruFunnelUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    };
    document.addEventListener('click', function (event) {
        var el = event.target && event.target.closest ? event.target.closest('[data-akuru-funnel]') : null;
        if (!el) {
            return;
        }
        window.akuruFunnel(el.getAttribute('data-akuru-funnel'), el.getAttribute('data-course-id'));
    }, true);
    </script>

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
