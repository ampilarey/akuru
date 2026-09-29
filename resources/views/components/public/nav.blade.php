@php
  // The website's frame is built around the four products (E-Learning,
  // Digital Library, Bookstore, School); everything about the institute
  // itself sits under About (the 2026-09-28 website design, STATUS §5ki).
  $siteProducts = [
      ['key' => 'courses', 'label' => __('site.courses'), 'short' => __('site.courses'), 'line' => __('site.courses_line'), 'href' => route('public.courses.index'), 'active' => request()->routeIs('public.courses.*'),
       'icon' => 'M4 5h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm6 3.5v5l4-2.5-4-2.5zM8 21h8'],
      ['key' => 'library', 'label' => __('site.digital_library'), 'short' => __('site.library'), 'line' => __('site.library_line'), 'href' => route('public.library.index'), 'active' => request()->routeIs('public.library.*'),
       'icon' => 'M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11'],
      ['key' => 'bookstore', 'label' => __('site.bookstore'), 'short' => __('site.shop'), 'line' => __('site.bookstore_line'), 'href' => route('public.shop.index'), 'active' => request()->routeIs('public.shop.*'),
       'icon' => 'M5 8h14l-1 12H6L5 8zm4 0V6a3 3 0 016 0v2'],
      ['key' => 'school', 'label' => __('site.school'), 'short' => __('site.school'), 'line' => __('site.school_line'), 'href' => route('public.admissions.create'), 'active' => request()->routeIs('public.admissions.*'),
       'icon' => 'M3 10l9-5 9 5-9 5-9-5zm4 2v5c3 2 7 2 10 0v-5m4-2v5'],
  ];
  // R5 (RESEARCH_ARTICLES_PLAN): the Digital Library's own sections — books,
  // articles and research are one shelf, filtered, and research is no longer
  // under About.
  $siteLibraryLinks = [
      ['key' => 'all', 'label' => __('site.library_all'), 'href' => route('public.library.index')],
      ['key' => 'books', 'label' => __('site.books'), 'href' => route('public.library.index', ['content_type' => 'book'])],
      ['key' => 'articles', 'label' => __('site.articles'), 'href' => route('public.library.index', ['content_type' => 'article'])],
      ['key' => 'research', 'label' => __('site.research'), 'href' => route('public.library.index', ['content_type' => 'research'])],
      ['key' => 'authors', 'label' => __('site.authors'), 'href' => route('public.library.index').'#authors'],
      ['key' => 'gift-cards', 'label' => __('site.gift_cards'), 'href' => route('public.gift-cards.index')],
  ];
  $siteAboutLinks = collect([
      ['public.about', 'site.about_us'],
      ['public.news.index', 'site.news'],
      ['public.events.index', 'site.events'],
      ['public.gallery.index', 'site.gallery'],
      ['public.achievements', 'site.achievements'],
      ['public.careers', 'site.careers'],
      ['public.contact.create', 'site.contact'],
  ])->map(fn ($l) => ['href' => route($l[0]), 'label' => __($l[1]), 'active' => request()->routeIs($l[0], str_replace('.index', '.*', $l[0]))])->all();
  $siteAboutActive = collect($siteAboutLinks)->contains('active', true);
@endphp
<div style="height:3px;background:linear-gradient(90deg,#A8861F,#C9A227,#E8BC3C,#C9A227,#A8861F)"></div>
<nav class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-50">
  <div class="container mx-auto flex items-center justify-between py-3 px-4">
    <!-- Logo (always visible, links home) -->
    <div class="nav-logo shrink-0 flex items-center">
      <a href="{{ LaravelLocalization::localizeURL('/') }}" class="flex items-center" aria-label="{{ __('public.Home') }}">
        <x-akuru-logo size="h-11 sm:h-14" />
      </a>
    </div>

    {{-- Prayer ribbon (mobile/tablet): sits between the logo and the translate button --}}
    <div class="header-prayer header-prayer--mobile nav-mobile-only" data-block="prayer_bar">
      @include('public.partials.prayer-banner')
    </div>

    {{-- Desktop navigation: the four products, then About (STATUS §5ki). --}}
    <div class="nav-desktop items-center" data-testid="site-nav">
      <nav aria-label="{{ __('site.main_menu') }}" class="nav-links">
        @foreach ($siteProducts as $product)
          @if ($product['key'] === 'library')
            {{-- R5: the product link still goes to the shelf; the caret opens its sections. --}}
            <div class="nav-about nav-lib" id="nav-lib">
              <a href="{{ $product['href'] }}" data-testid="nav-{{ $product['key'] }}"
                 class="nav-link {{ $product['active'] ? 'is-active' : '' }}" @if ($product['active']) aria-current="page" @endif>
                {{ $product['label'] }}
              </a>
              <button type="button" class="nav-lib-btn" aria-expanded="false" aria-controls="nav-library-menu" aria-label="{{ __('site.library_menu') }}"
                      onclick="toggleNavMenu(event, 'nav-lib')" data-testid="nav-library-more">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <div id="nav-library-menu" class="nav-about-menu" data-testid="nav-library-menu">
                @foreach ($siteLibraryLinks as $link)
                  <a href="{{ $link['href'] }}" data-testid="nav-library-{{ $link['key'] }}">{{ $link['label'] }}</a>
                @endforeach
              </div>
            </div>
          @else
            <a href="{{ $product['href'] }}" data-testid="nav-{{ $product['key'] }}"
               class="nav-link {{ $product['active'] ? 'is-active' : '' }}" @if ($product['active']) aria-current="page" @endif>
              {{ $product['label'] }}
            </a>
          @endif
        @endforeach
        <div class="nav-about" id="nav-about">
          <button type="button" class="nav-link nav-about-btn {{ $siteAboutActive ? 'is-active' : '' }}" aria-expanded="false" aria-controls="nav-about-menu"
                  onclick="toggleNavMenu(event, 'nav-about')" data-testid="nav-about">
            {{ __('site.about') }}
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div id="nav-about-menu" class="nav-about-menu" data-testid="nav-about-menu">
            @foreach ($siteAboutLinks as $link)
              <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="page" @endif>{{ $link['label'] }}</a>
            @endforeach
          </div>
        </div>
      </nav>
      {{-- Prayer strip (Bake&Grill-style, Akuru colors) — unchanged by the redesign. --}}
      <div class="header-prayer nav-prayer" data-block="prayer_bar">
        @include('public.partials.prayer-banner')
      </div>
      <a href="{{ route('public.search') }}" class="nav-icon" aria-label="{{ __('site.search') }}" title="{{ __('site.search') }}" data-testid="nav-search">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      </a>
      <a href="{{ route('public.shop.cart') }}" class="nav-icon" data-testid="nav-cart" aria-label="{{ __('shop.cart_title') }}" title="{{ __('site.cart') }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4h2l2.4 11.2a1 1 0 001 .8h9.2a1 1 0 001-.8L20 8H6.2M9 20.5a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
      </a>
    </div>

    <!-- Right Side: Translate + User + Hamburger -->
    {{-- The deployed stylesheet is a committed Vite build, so sizing here
         uses plain scoped CSS instead of uncompiled Tailwind utilities. --}}
    <style>
      /* ── Header layout ──────────────────────────────────────────────
         The full link row only fits from 1280px up (nine links + the
         prayer pill + Enroll). Below that the hamburger menu carries
         every link, so the logo and the prayer ribbon always have room.
         Written as plain scoped CSS: the deployed stylesheet is a
         committed Vite build with no `xl:` variants compiled in. */
      .nav-logo { flex: 0 0 auto; }
      .nav-logo img { min-width: 0; }
      .nav-desktop, .nav-desktop-only { display: none; min-width: 0; }
      .nav-mobile-only { display: block; }
      .nav-links { display: flex; align-items: center; gap: 1.1rem; }
      .nav-link { display: inline-flex; align-items: center; gap: .2rem; font-size: .9rem; font-weight: 600; color: #3F3A36; white-space: nowrap; background: none; border: 0; padding: .35rem 0; cursor: pointer; border-bottom: 2px solid transparent; }
      .nav-link:hover, .nav-link.is-active { color: #7C2D37; }
      .nav-link.is-active { border-bottom-color: #C9A227; }
      .nav-about { position: relative; }
      .nav-lib { display: inline-flex; align-items: center; }
      .nav-lib-btn { display: inline-flex; align-items: center; padding: .35rem .15rem; margin-inline-start: -.35rem; color: #5E5650; background: none; border: 0; cursor: pointer; border-radius: .4rem; }
      .nav-lib-btn:hover, .nav-lib.is-open .nav-lib-btn { color: #7C2D37; }
      .nav-about-menu { display: none; position: absolute; top: 100%; inset-inline-start: -1rem; z-index: 60; min-width: 13rem; padding: .4rem; margin-top: .35rem; background: #fff; border: 1px solid #EDE4D8; border-radius: .75rem; box-shadow: 0 16px 40px rgba(60,20,27,.14); }
      .nav-about.is-open .nav-about-menu { display: block; }
      .nav-about-menu a { display: block; padding: .55rem .8rem; border-radius: .5rem; font-size: .875rem; color: #3F3A36; text-decoration: none; }
      .nav-about-menu a:hover, .nav-about-menu a[aria-current] { background: #FBF6EC; color: #7C2D37; }
      .nav-icon { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: .6rem; color: #3F3A36; }
      .nav-icon:hover { color: #7C2D37; background: #FBF6EC; }
      .nav-cta { padding: .5rem 1.1rem; }
      /* The prayer pill is the one flexible item: it gives up width
         before anything else is pushed out of the row. */
      .header-prayer:not(.header-prayer--mobile) { width: min(330px, 24vw); flex: 0 1 auto; }
      @media (min-width: 1280px) {
        .nav-desktop { display: flex; gap: .6rem; }
        .nav-links { margin-inline-end: .5rem; }
        .nav-desktop-only { display: inline-flex; }
        .nav-mobile-only, .nav-mobile-menu { display: none !important; }
      }
      @media (min-width: 1400px) {
        .nav-links { gap: 1.5rem; }
        .nav-link { font-size: .95rem; }
        .nav-cta { padding: .5rem 1.35rem; }
      }
      /* The phone menu: search, the four products, About, the account.
         It scrolls on its own so a short screen reaches the bottom of it
         above the bottom bar. */
      #mobileMenu:not(.hidden) { max-height: calc(100dvh - 4.5rem); overflow-y: auto; }
      @media (max-width: 639px) { #mobileMenu:not(.hidden) { max-height: calc(100dvh - 8.5rem); } }
      .nav-m-search { display: flex; align-items: center; gap: .6rem; padding: 0 .9rem; height: 3rem; border: 1px solid #DCCFBE; border-radius: .75rem; background: #fff; }
      .nav-m-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-size: 16px; padding: 0; box-shadow: none; }
      .nav-m-products { margin-top: .9rem; border: 1px solid #EDE4D8; border-radius: .9rem; overflow: hidden; background: #fff; }
      .nav-m-products > a { display: flex; align-items: center; gap: .8rem; padding: .8rem .9rem; color: #1F1A17; text-decoration: none; border-top: 1px solid #F1EAE0; }
      .nav-m-products > a:first-child { border-top: 0; }
      .nav-m-products > a[aria-current] { background: #FBF6EC; }
      .nav-m-sub { display: flex; flex-wrap: wrap; gap: .4rem; padding: 0 .9rem .8rem; padding-inline-start: 4.2rem; }
      .nav-m-sub a { display: inline-flex; align-items: center; min-height: 2.25rem; padding: 0 .7rem; border: 1px solid #EDE4D8; border-radius: 999px; font-size: .8125rem; font-weight: 600; color: #7C2D37; text-decoration: none; background: #FBF8F3; }
      .nav-m-icon { flex: 0 0 auto; width: 2.5rem; height: 2.5rem; border-radius: .6rem; background: #F6ECEE; color: #7C2D37; display: flex; align-items: center; justify-content: center; }
      .nav-m-heading { margin: 1.1rem .25rem .5rem; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #6B625B; }
      .nav-m-about { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
      .nav-m-about a { display: flex; align-items: center; min-height: 2.75rem; padding: 0 .8rem; border: 1px solid #EDE4D8; border-radius: .6rem; background: #fff; font-size: .875rem; font-weight: 600; color: #3F3A36; text-decoration: none; }
      .nav-m-about a[aria-current] { border-color: #C9A227; color: #7C2D37; }
      .nav-right { gap: .4rem; }
      .nav-translate { padding: .375rem .45rem; }
      .nav-burger { padding: .35rem; margin-right: -.35rem; }
      @media (min-width: 640px) {
        .nav-right { gap: .5rem; }
        .nav-translate { padding: .375rem .5rem; }
        .nav-burger { padding: .5rem; margin-right: -.5rem; }
      }
      /* The layout's 44px tap-target rule (button,a ≤768px) would inflate
         these compact header controls; exempt them explicitly. */
      @media (max-width: 768px) {
        .nav-translate, .nav-burger { min-height: 0; min-width: 0; }
      }
    </style>
    <div class="nav-right flex items-center">

      {{-- ── Translate dropdown ── --}}
      <div class="relative" id="gt-wrapper">
        <button onclick="toggleGT(event)" aria-label="Translate"
                class="nav-translate flex items-center gap-1 rounded-lg text-sm text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 border border-gray-200 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
          </svg>
          <span class="hidden sm:inline text-xs font-medium">Translate</span>
        </button>

        <div id="gt-dropdown"
             class="hidden absolute right-0 top-full mt-1 z-50 bg-white rounded-xl shadow-xl border border-gray-200 w-48">

          {{-- Pinned: column of 3 --}}
          <div class="p-2 space-y-1">
            <button onclick="translateTo('en')"
                    class="gt-pin w-full text-left px-3 py-2 text-sm rounded-lg border border-gray-200 hover:border-brandMaroon-400 hover:bg-brandBeige-50 transition-colors text-gray-700 font-medium"
                    data-code="en">🇬🇧 English</button>
            <button onclick="translateTo('ar')"
                    class="gt-pin w-full text-left px-3 py-2 text-sm rounded-lg border border-gray-200 hover:border-brandMaroon-400 hover:bg-brandBeige-50 transition-colors text-gray-700 font-medium"
                    data-code="ar">🇸🇦 العربية</button>
            <button onclick="translateTo('dv')"
                    class="gt-pin w-full text-left px-3 py-2 text-sm rounded-lg border border-gray-200 hover:border-brandMaroon-400 hover:bg-brandBeige-50 transition-colors text-gray-700 font-medium"
                    data-code="dv">🇲🇻 ދިވެހި</button>
          </div>

          {{-- Search --}}
          <div class="px-2 pb-2">
            <input id="gt-search" type="text" placeholder="Search other language…"
                   oninput="filterGTLangs(this.value)"
                   class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-brandMaroon-400 placeholder-gray-400">
          </div>

          {{-- Search results (hidden until typing) --}}
          <div id="gt-lang-list" class="hidden max-h-44 overflow-y-auto border-t border-gray-100 py-1"></div>
        </div>
      </div>
      {{-- GT init element: off-screen so GT can initialise its hidden select --}}
      <div id="google_translate_element" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true"></div>

      {{-- ── User menu (desktop, md+) ── --}}
      <div class="hidden md:block">
        @auth
          {{-- Dropdown: My Portal + Logout --}}
          <div class="relative" id="user-menu-wrapper">
            <button onclick="toggleUserMenu()"
                    class="flex items-center gap-1.5 text-sm font-medium text-brandMaroon-700 border border-brandMaroon-200 px-3 py-1.5 rounded-lg hover:bg-brandMaroon-50 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
              {{ auth()->user()->navLabel() }}
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div id="user-menu-dropdown"
                 class="absolute right-0 top-full mt-1 z-50 bg-white rounded-xl shadow-xl border border-gray-200 min-w-44 py-1 hidden">
              {{-- One door into the app for everyone (docs/SIGN_IN_PLAN.md ID3):
                   `/dashboard` sends each person to their own workspace's home —
                   the office, a teacher's day, the family, a shop, their learning.
                   Until ID3 this was a role-by-role list beside a link to the old
                   course portal. --}}
              <a href="{{ route('dashboard') }}" data-testid="nav-my-portal"
                 class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-lg mx-1 mb-1"
                 style="background:linear-gradient(135deg,#7C2D37,#5A1F28)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                {{ __('nav.my_portal') }}
              </a>
              <div class="border-t border-gray-100 my-1"></div>
              <a href="{{ route('my.enrollments') }}"
                 class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-brandBeige-50 hover:text-brandMaroon-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                {{ __('nav.my_enrolments') }}
              </a>
              <a href="{{ route('public.library.my') }}"
                 class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-brandBeige-50 hover:text-brandMaroon-700" data-testid="nav-my-library">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                {{ __('public.My Library') }}
              </a>
              <a href="{{ route('public.wallet') }}"
                 class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-brandBeige-50 hover:text-brandMaroon-700" data-testid="nav-my-wallet">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                {{ __('public.My Wallet') }}
              </a>
              <div class="border-t border-gray-100 my-1"></div>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                  </svg>
                  Log out
                </button>
              </form>
            </div>
          </div>
        @else
          <a href="{{ route('login') }}"
             class="inline-flex items-center gap-1 text-sm font-medium text-brandGray-600 hover:text-brandMaroon-600 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-brandBeige-50 transition-colors">
            Login
          </a>
        @endauth
      </div>

      <a href="{{ route('public.courses.index') }}" data-testid="nav-enroll"
         class="nav-cta nav-desktop-only font-bold rounded-lg shadow-md transition-all hover:scale-105"
         style="background:#C9A227;color:#491821">
        {{ __('site.enroll') }}
      </a>

      {{-- ── Hamburger (mobile/tablet) ── --}}
      <button class="nav-burger nav-mobile-only text-brandGray-600 hover:text-brandMaroon-600 transition-colors"
              onclick="toggleMobileMenu()" aria-label="{{ __('site.menu') }}" aria-expanded="false" aria-controls="mobileMenu" data-testid="nav-burger">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

    </div>
  </div>

  {{-- Phone and tablet menu: search, the four products, About, the account (STATUS §5ki). --}}
  <div id="mobileMenu" class="hidden nav-mobile-menu border-t shadow-lg" style="background:#FBF8F3" data-testid="mobile-menu">
    <div class="container mx-auto py-4 px-4">
      <form action="{{ route('public.search') }}" method="GET" role="search" class="nav-m-search">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="#6B625B" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <label for="nav-m-q" class="sr-only">{{ __('site.search') }}</label>
        <input id="nav-m-q" type="search" name="q" placeholder="{{ __('site.search_placeholder') }}" autocomplete="off">
      </form>

      <div class="nav-m-products" data-testid="mobile-menu-products">
        @foreach ($siteProducts as $product)
          <a href="{{ $product['href'] }}" @if ($product['active']) aria-current="page" @endif>
            <span class="nav-m-icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $product['icon'] }}"/></svg></span>
            <span class="flex flex-col">
              <strong class="text-base">{{ $product['label'] }}</strong>
              <span class="text-sm" style="color:#5E5650">{{ $product['line'] }}</span>
            </span>
          </a>
          @if ($product['key'] === 'library')
            {{-- R5: the library's sections, as small links under its row. --}}
            <div class="nav-m-sub" data-testid="mobile-menu-library">
              @foreach (array_slice($siteLibraryLinks, 1) as $link)
                <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
              @endforeach
            </div>
          @endif
        @endforeach
      </div>

      <p class="nav-m-heading">{{ __('site.about_akuru') }}</p>
      <div class="nav-m-about" data-testid="mobile-menu-about">
        @foreach ($siteAboutLinks as $link)
          <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="page" @endif>{{ $link['label'] }}</a>
        @endforeach
        <a href="tel:{{ $siteSettings['phone'] ?? '+9607972434' }}">{{ __('site.call_us') }}</a>
      </div>

      <div class="pt-3 mt-4 border-t border-gray-200">
        @auth
          <p class="px-1 py-1 text-xs text-gray-500">Signed in as {{ auth()->user()->navLabel() }}</p>
          {{-- One door into the app for everyone (ID3). --}}
          <a href="{{ route('dashboard') }}" data-testid="nav-my-portal-mobile"
             class="block py-3 px-4 font-semibold text-white rounded-lg mb-1"
             style="background:linear-gradient(135deg,#7C2D37,#5A1F28)">
            {{ __('nav.my_portal') }}
          </a>
          <a href="{{ route('my.enrollments') }}" class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg">{{ __('nav.my_enrolments') }}</a>
          <a href="{{ route('public.library.my') }}" class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg">{{ __('public.My Library') }}</a>
          <a href="{{ route('public.wallet') }}" class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg">{{ __('public.My Wallet') }}</a>
          <form method="POST" action="{{ route('logout') }}" class="px-4 pt-1 pb-2">
            @csrf
            <button type="submit" class="w-full text-left py-2.5 px-0 text-sm text-red-600 hover:text-red-700 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              Log out
            </button>
          </form>
        @endauth
        <div class="grid grid-cols-2 gap-2 pt-2">
          @guest
            <a href="{{ route('login') }}" class="flex items-center justify-center py-3 rounded-lg border font-semibold" style="border-color:#C7B49A;background:#fff;color:#1F1A17">{{ __('site.login') }}</a>
          @endguest
          <a href="{{ route('public.courses.index') }}"
             class="@auth col-span-2 @endauth flex items-center justify-center py-3 rounded-lg font-bold shadow-md"
             style="background:#C9A227;color:#491821">
            {{ __('site.enroll') }}
          </a>
        </div>
      </div>
    </div>
  </div>
</nav>

<script>
// ── Google Translate ──────────────────────────────────────────────
var GT_LANGS = [
  {c:'af',l:'Afrikaans'},{c:'sq',l:'Albanian'},{c:'am',l:'Amharic'},{c:'ar',l:'Arabic'},
  {c:'hy',l:'Armenian'},{c:'az',l:'Azerbaijani'},{c:'eu',l:'Basque'},{c:'be',l:'Belarusian'},
  {c:'bn',l:'Bengali'},{c:'bs',l:'Bosnian'},{c:'bg',l:'Bulgarian'},{c:'ca',l:'Catalan'},
  {c:'ceb',l:'Cebuano'},{c:'ny',l:'Chichewa'},{c:'zh-CN',l:'Chinese (Simplified)'},
  {c:'zh-TW',l:'Chinese (Traditional)'},{c:'co',l:'Corsican'},{c:'hr',l:'Croatian'},
  {c:'cs',l:'Czech'},{c:'da',l:'Danish'},{c:'nl',l:'Dutch'},{c:'eo',l:'Esperanto'},
  {c:'et',l:'Estonian'},{c:'tl',l:'Filipino'},{c:'fi',l:'Finnish'},{c:'fr',l:'French'},
  {c:'fy',l:'Frisian'},{c:'gl',l:'Galician'},{c:'ka',l:'Georgian'},{c:'de',l:'German'},
  {c:'el',l:'Greek'},{c:'gu',l:'Gujarati'},{c:'ht',l:'Haitian Creole'},{c:'ha',l:'Hausa'},
  {c:'haw',l:'Hawaiian'},{c:'iw',l:'Hebrew'},{c:'hi',l:'Hindi'},{c:'hmn',l:'Hmong'},
  {c:'hu',l:'Hungarian'},{c:'is',l:'Icelandic'},{c:'ig',l:'Igbo'},{c:'id',l:'Indonesian'},
  {c:'ga',l:'Irish'},{c:'it',l:'Italian'},{c:'ja',l:'Japanese'},{c:'jw',l:'Javanese'},
  {c:'kn',l:'Kannada'},{c:'kk',l:'Kazakh'},{c:'km',l:'Khmer'},{c:'ko',l:'Korean'},
  {c:'ku',l:'Kurdish'},{c:'ky',l:'Kyrgyz'},{c:'lo',l:'Lao'},{c:'la',l:'Latin'},
  {c:'lv',l:'Latvian'},{c:'lt',l:'Lithuanian'},{c:'lb',l:'Luxembourgish'},{c:'mk',l:'Macedonian'},
  {c:'mg',l:'Malagasy'},{c:'ms',l:'Malay'},{c:'ml',l:'Malayalam'},{c:'mt',l:'Maltese'},
  {c:'mi',l:'Maori'},{c:'mr',l:'Marathi'},{c:'mn',l:'Mongolian'},{c:'my',l:'Myanmar (Burmese)'},
  {c:'ne',l:'Nepali'},{c:'no',l:'Norwegian'},{c:'ps',l:'Pashto'},{c:'fa',l:'Persian'},
  {c:'pl',l:'Polish'},{c:'pt',l:'Portuguese'},{c:'pa',l:'Punjabi'},{c:'ro',l:'Romanian'},
  {c:'ru',l:'Russian'},{c:'sm',l:'Samoan'},{c:'gd',l:'Scots Gaelic'},{c:'sr',l:'Serbian'},
  {c:'st',l:'Sesotho'},{c:'sn',l:'Shona'},{c:'sd',l:'Sindhi'},{c:'si',l:'Sinhala'},
  {c:'sk',l:'Slovak'},{c:'sl',l:'Slovenian'},{c:'so',l:'Somali'},{c:'es',l:'Spanish'},
  {c:'su',l:'Sundanese'},{c:'sw',l:'Swahili'},{c:'sv',l:'Swedish'},{c:'tg',l:'Tajik'},
  {c:'ta',l:'Tamil'},{c:'te',l:'Telugu'},{c:'th',l:'Thai'},{c:'tr',l:'Turkish'},
  {c:'uk',l:'Ukrainian'},{c:'ur',l:'Urdu'},{c:'uz',l:'Uzbek'},{c:'vi',l:'Vietnamese'},
  {c:'cy',l:'Welsh'},{c:'xh',l:'Xhosa'},{c:'yi',l:'Yiddish'},{c:'yo',l:'Yoruba'},{c:'zu',l:'Zulu'}
];
// Pinned languages excluded from the search list
var GT_PINNED = ['en','ar','dv'];

function googleTranslateElementInit() {
  new google.translate.TranslateElement({ pageLanguage: 'en', autoDisplay: false }, 'google_translate_element');
  markActiveLang();
}

function filterGTLangs(q) {
  var list = document.getElementById('gt-lang-list');
  if (!list) return;
  var trimmed = q.trim();
  if (!trimmed) {
    list.innerHTML = '';
    list.classList.add('hidden');
    return;
  }
  var filtered = GT_LANGS.filter(function(l) {
    return !GT_PINNED.includes(l.c) && l.l.toLowerCase().includes(trimmed.toLowerCase());
  });
  list.classList.remove('hidden');
  if (!filtered.length) {
    list.innerHTML = '<p class="px-4 py-3 text-xs text-gray-400">No results</p>';
    return;
  }
  list.innerHTML = filtered.map(function(l) {
    return '<button onclick="translateTo(\'' + l.c + '\')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-brandBeige-50 hover:text-brandMaroon-700 transition-colors">' + l.l + '</button>';
  }).join('');
}

function markActiveLang() {
  var m = document.cookie.match(/googtrans=\/en\/([a-z-]+)/);
  var active = m ? m[1] : 'en';
  document.querySelectorAll('.gt-pin').forEach(function(btn) {
    var isActive = btn.dataset.code === active;
    btn.classList.toggle('border-brandMaroon-500', isActive);
    btn.classList.toggle('bg-brandMaroon-50', isActive);
    btn.classList.toggle('text-brandMaroon-700', isActive);
  });
}

function translateTo(lang) {
  document.getElementById('gt-dropdown')?.classList.add('hidden');
  var search = document.getElementById('gt-search');
  if (search) search.value = '';
  var list = document.getElementById('gt-lang-list');
  if (list) { list.innerHTML = ''; list.classList.add('hidden'); }

  if (lang === 'en') {
    var d = location.hostname;
    ['/', window.location.pathname].forEach(function(p) {
      document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=' + p;
      document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=' + p + '; domain=' + d;
      document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=' + p + '; domain=.' + d;
    });
    window.location.reload();
    return;
  }

  // Apply font class (no layout/direction change)
  var html = document.documentElement;
  html.classList.remove('gt-lang-ar', 'gt-lang-dv');
  var fontMeta = {
    ar: { url: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap', stack: "'Cairo','Noto Sans Arabic',Arial,sans-serif" },
    dv: { url: null, stack: "'Faruma','MV Boli',sans-serif" }
  };
  if (fontMeta[lang]) {
    html.classList.add('gt-lang-' + lang);
    // Force font on body so GT-injected elements inherit it
    document.body.style.setProperty('font-family', fontMeta[lang].stack, 'important');
    if (fontMeta[lang].url && !document.querySelector('link[href="' + fontMeta[lang].url + '"]')) {
      var link = document.createElement('link'); link.rel = 'stylesheet'; link.href = fontMeta[lang].url;
      document.head.appendChild(link);
    }
  } else {
    document.body.style.removeProperty('font-family');
  }

  var tries = 0;
  function attempt() {
    var sel = document.querySelector('.goog-te-combo');
    if (!sel && tries++ < 20) { setTimeout(attempt, 300); return; }
    if (!sel) return;
    sel.value = lang;
    sel.dispatchEvent(new Event('change'));
  }
  attempt();
}

function toggleGT(e) {
  e.stopPropagation();
  var dd = document.getElementById('gt-dropdown');
  var opening = dd.classList.contains('hidden');
  dd.classList.toggle('hidden');
  document.getElementById('user-menu-dropdown')?.classList.add('hidden');
  if (opening) {
    setTimeout(function() { document.getElementById('gt-search')?.focus(); }, 50);
  }
}

// ── User dropdown ─────────────────────────────────────────────────
function toggleUserMenu() {
  document.getElementById('user-menu-dropdown')?.classList.toggle('hidden');
  document.getElementById('gt-dropdown')?.classList.add('hidden');
}

// ── About menu (desktop) ─────────────────────────────────────────
// The header's two dropdowns (About, and the Digital Library's sections, R5):
// one open at a time; Escape and a click outside close them.
var NAV_MENUS = ['nav-about', 'nav-lib'];
function closeNavMenu(id, focus) {
  var box = document.getElementById(id);
  if (!box || !box.classList.contains('is-open')) return;
  box.classList.remove('is-open');
  box.querySelector('button').setAttribute('aria-expanded', 'false');
  if (focus) box.querySelector('button').focus();
}
function toggleNavMenu(e, id) {
  e.stopPropagation();
  var box = document.getElementById(id);
  var open = !box.classList.contains('is-open');
  NAV_MENUS.forEach(function(other) { if (other !== id) closeNavMenu(other, false); });
  box.classList.toggle('is-open', open);
  box.querySelector('button').setAttribute('aria-expanded', open ? 'true' : 'false');
}
function toggleAboutMenu(e) { toggleNavMenu(e, 'nav-about'); }
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') NAV_MENUS.forEach(function(id) { closeNavMenu(id, true); });
});

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
  NAV_MENUS.forEach(function(id) {
    var box = document.getElementById(id);
    if (box && !box.contains(e.target)) closeNavMenu(id, false);
  });
  if (!document.getElementById('gt-wrapper')?.contains(e.target))
    document.getElementById('gt-dropdown')?.classList.add('hidden');
  if (!document.getElementById('user-menu-wrapper')?.contains(e.target))
    document.getElementById('user-menu-dropdown')?.classList.add('hidden');
});

// ── Suppress GT banner bar ────────────────────────────────────────
(function() {
  const s = document.createElement('style');
  s.textContent = `
    body { top: 0 !important; }
    .goog-te-banner-frame { display: none !important; }
    .skiptranslate { display: none !important; }
  `;
  document.head.appendChild(s);
})();

// ── Mobile menu ───────────────────────────────────────────────────
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
  // Add global function after DOM is loaded
  window.toggleMobileMenu = function() {
    const menu = document.getElementById('mobileMenu');
    const button = document.querySelector('[onclick="toggleMobileMenu()"]');
    
    if (!menu || !button) return; // Safety check
    
    if (menu.classList.contains('hidden')) {
      menu.classList.remove('hidden');
      button.setAttribute('aria-expanded', 'true');
    } else {
      menu.classList.add('hidden');
      button.setAttribute('aria-expanded', 'false');
    }
  };

  // Close mobile menu when clicking outside
  document.addEventListener('click', function(event) {
    const menu = document.getElementById('mobileMenu');
    const button = document.querySelector('[onclick="toggleMobileMenu()"]');
    
    if (!menu || !button) return; // Safety check
    
    if (!menu.contains(event.target) && !button.contains(event.target)) {
      menu.classList.add('hidden');
      button.setAttribute('aria-expanded', 'false');
    }
  });

  // Close mobile menu on window resize
  window.addEventListener('resize', function() {
    const menu = document.getElementById('mobileMenu');
    const button = document.querySelector('[onclick="toggleMobileMenu()"]');
    
    if (!menu || !button) return; // Safety check
    
    if (window.innerWidth >= 1280) { // desktop link row takes over
      menu.classList.add('hidden');
      button.setAttribute('aria-expanded', 'false');
    }
  });
});
</script>
