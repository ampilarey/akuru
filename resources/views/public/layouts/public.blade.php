<!DOCTYPE html>
{{-- STATUS §5lp: Dhivehi and Arabic read right to left, as the app shell and the reader already do. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['dv', 'ar'], true) ? 'rtl' : 'ltr' }}">
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
        ar: null, // Cairo is self-hosted with app.css since STATUS §5np — no external link needed
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

    <!-- Font overrides for translated languages (direction is set on <html>, STATUS §5lp) -->
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
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}?v=4">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}?v=4">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}?v=4">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon-32x32.png') }}?v=4">
    
    <!-- Fonts -->
    {{-- Figtree comes with app.css, self-hosted (STATUS §5np). --}}
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa')
    
    @stack('styles')
    
    <!-- Mobile-specific styles -->
    <style>
        /* Improve mobile touch targets */
        @media (max-width: 768px) {
            /* A checkbox or radio keeps its own size: stretched to 44px it
               became a large empty square (the shop's "In stock only"). Its
               label is the thing a thumb taps. */
            button, a, input:not([type="checkbox"]):not([type="radio"]), select, textarea {
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
        
        /* STATUS §5lp: a "go on" arrow points the way the page reads. */
        [dir="rtl"] .rtl-flip { display: inline-block; transform: scaleX(-1); }

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
        {{--
            Flash messages, on every public page.

            Nothing here rendered them, so a `redirect()->with('error', …)` from
            a public controller showed the visitor **nothing**. The case that
            found it: a paying family finishes registration, clicks the one
            button offered — "Proceed to payment" — and, if the gateway is not
            configured, lands back on the course list with money outstanding and
            no explanation. `paymentRetry` sets the message; the layout dropped
            it on the floor.

            Same shape as the dead-end resume link (STATUS §5dr), on the other
            payment path: the code was right and the person was told nothing.
            Fixed in the layout rather than at that one redirect, because every
            public `with('error')` had the same problem.
        --}}
        @foreach (['error' => 'red', 'success' => 'green', 'status' => 'blue'] as $key => $tone)
            @if (session($key))
                <div role="alert"
                     class="mx-auto mt-4 max-w-3xl rounded border border-{{ $tone }}-200 bg-{{ $tone }}-50 px-4 py-3 text-sm text-{{ $tone }}-800">
                    {{ session($key) }}
                </div>
            @endif
        @endforeach

        @yield('content')
    </main>
    
    {{-- A shop's own pages set 'shop_footer' and get only the copyright line (STATUS §5kz). --}}
    @hasSection('shop_footer')
        <x-public.footer :compact="true" />
    @else
        <x-public.footer />
    @endif

    @include('public.partials.prayer-banner-assets')

    {{-- STATUS §5lt: on the Bookstore's pages, a shop's own included, a phone gets the store's
         tab bar (Home · Categories · Deals · Account · Cart; a shop's own tabs on its pages)
         instead of the site's. One bar and one spacer on every page. --}}
    @if (request()->routeIs('public.shop.*'))
    @include('public.shop._bottom-bar', ['shopVendor' => request()->routeIs('public.shop.vendor', 'public.shop.vendor.*') && isset($vendor) && is_array($vendor) ? $vendor : null])
    @else
    {{-- Phone bottom bar: Home · Courses · Library · Shop · Account (STATUS §5ki).
         Viber moved to the floating chat button, Call into the menu's About. --}}
    @php
      $bottomTabs = [
          ['home', __('site.home'), route('public.home'), request()->routeIs('public.home'), 'M4 11l8-7 8 7v9h-5v-6H9v6H4v-9z'],
          ['courses', __('site.courses'), route('public.courses.index'), request()->routeIs('public.courses.*'), 'M4 5h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm6 3.5v5l4-2.5-4-2.5zM8 21h8'],
          ['library', __('site.library'), route('public.library.index'), request()->routeIs('public.library.*'), 'M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11'],
          ['shop', __('site.shop'), route('public.shop.index'), request()->routeIs('public.shop.*'), 'M5 8h14l-1 12H6L5 8zm4 0V6a3 3 0 016 0v2'],
      ];
    @endphp
    <nav aria-label="{{ __('site.quick_links') }}" data-testid="bottom-bar"
         class="fixed bottom-0 left-0 right-0 z-50 sm:hidden bg-white border-t border-gray-200 shadow-lg safe-area-bottom">
        <div class="grid grid-cols-5">
            @foreach ($bottomTabs as [$key, $label, $href, $active, $icon])
            <a href="{{ $href }}" data-testid="bottom-{{ $key }}" @if ($active) aria-current="page" @endif
               class="bottom-tab flex flex-col items-center justify-center py-2 gap-1 {{ $active ? 'is-active' : '' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/></svg>
                <span class="text-xs leading-none">{{ $label }}</span>
            </a>
            @endforeach
            @auth
            {{-- Into the app, to the person's own home (SIGN_IN_PLAN ID3). --}}
            <a href="{{ route('dashboard') }}" data-testid="bottom-my-portal"
               class="bottom-tab flex flex-col items-center justify-center py-2 gap-1">
            @else
            <a href="{{ route('login') }}" data-testid="bottom-login"
               class="bottom-tab flex flex-col items-center justify-center py-2 gap-1 {{ request()->routeIs('login') ? 'is-active' : '' }}">
            @endauth
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a4 4 0 100-8 4 4 0 000 8zm-8 9c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                <span class="text-xs leading-none">{{ __('site.account') }}</span>
            </a>
        </div>
    </nav>
    <style>
        .bottom-tab { color: #5E5650; font-weight: 600; min-height: 3.5rem; }
        .bottom-tab:hover, .bottom-tab.is-active { color: #7C2D37; }
        .bottom-tab.is-active { font-weight: 700; box-shadow: inset 0 3px 0 #C9A227; }
    </style>
    {{-- Padding so footer content doesn't hide behind sticky bar on mobile --}}
    <div class="sm:hidden h-16"></div>
    @endif

    {{-- Viber float button. On a phone it sits above the bottom bar, which
         no longer carries Viber (STATUS §5ki).
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
        .viber-float { right: 1rem; bottom: 5.25rem; width: 3rem; height: 3rem; }
        body.has-cookie-bar .viber-float { bottom: 8.5rem; }
        @media (min-width: 640px) {
            .viber-float, body.has-cookie-bar .viber-float { right: 1.5rem; bottom: 1.5rem; width: 3.5rem; height: 3.5rem; }
            body.has-cookie-bar .viber-float { bottom: 4.5rem; }
        }
    </style>
    <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}" target="_blank" rel="noopener"
       class="viber-float flex fixed z-40 text-white rounded-full items-center justify-center" data-testid="viber-float"
       aria-label="{{ __('public.Chat with us on Viber') }}"
       title="{{ __('public.Chat with us on Viber') }}">
        <x-public.viber-icon class="w-6 h-6 sm:w-7 sm:h-7" />
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

    {{-- Cookie consent: one line, above the phone's bottom bar (STATUS §5ki). --}}
    <div id="cookieConsent" data-testid="cookie-bar" class="cookie-bar fixed left-0 right-0 z-50 hidden shadow-lg" style="background:#1F1A17;color:#EDE6E0">
        <div class="container mx-auto flex items-center gap-3 px-4 py-2">
            <p class="text-xs sm:text-sm flex-1 m-0">
                <span class="sm:hidden">{{ __('site.cookies_short') }}</span>
                <span class="hidden sm:inline">{{ __('public.cookie_consent_message') }}</span>
                <a href="{{ route('public.page.show', 'privacy-policy') }}" class="underline" style="color:#E8C766">{{ __('public.Privacy Policy') }}</a>
            </p>
            <button type="button" onclick="acceptCookies()" class="cookie-btn shrink-0 px-3 rounded-lg text-xs sm:text-sm font-bold" style="background:#C9A227;color:#2B1F04">
                {{ __('public.Accept') }}
            </button>
            <button type="button" onclick="dismissCookies()" class="cookie-btn shrink-0 px-2 text-xs sm:text-sm" style="background:transparent;color:#EDE6E0">
                {{ __('public.Decline') }}
            </button>
        </div>
    </div>
    <style>
        .cookie-bar { bottom: 4rem; }
        .cookie-bar .cookie-btn { min-height: 2.25rem; min-width: 0; }
        @media (min-width: 640px) { .cookie-bar { bottom: 0; } }
    </style>
    <script>
    (function(){
        if (!localStorage.getItem('cookieConsent')) {
            document.getElementById('cookieConsent').classList.remove('hidden');
            document.body.classList.add('has-cookie-bar');
        }
    })();
    function acceptCookies() {
        localStorage.setItem('cookieConsent', 'accepted');
        document.getElementById('cookieConsent').classList.add('hidden');
        document.body.classList.remove('has-cookie-bar');
    }
    function dismissCookies() {
        localStorage.setItem('cookieConsent', 'declined');
        document.getElementById('cookieConsent').classList.add('hidden');
        document.body.classList.remove('has-cookie-bar');
    }
    </script>

    {{-- STATUS §5lb: a timed sale's countdown, wherever the store shows one ([data-sale-ends]). --}}
    <script>
    (() => {
        const clocks = document.querySelectorAll('[data-sale-ends]');
        if (!clocks.length) return;
        const pad = (n) => String(n).padStart(2, '0');
        const tick = () => clocks.forEach((el) => {
            const left = Math.floor((Date.parse(el.dataset.saleEnds) - Date.now()) / 1000);
            if (!(left > 0)) { el.textContent = el.dataset.ended; return; }
            const d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
            const time = (d > 0 ? d + ' ' + el.dataset.days + ' ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
            el.textContent = el.dataset.template.replace('__TIME__', time);
        });
        tick();
        setInterval(tick, 1000);
    })();
    </script>

    @stack('scripts')
</body>
</html>
