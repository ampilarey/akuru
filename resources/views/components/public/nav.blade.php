<nav class="bg-white border-b shadow-sm sticky top-0 z-50">
  <div class="container mx-auto flex items-center justify-between py-3 px-4">
    <!-- Logo -->
    <div class="flex items-center space-x-3 rtl:space-x-reverse">
      <a href="{{ LaravelLocalization::localizeURL('/') }}" class="flex items-center">
        <x-akuru-logo size="h-10 sm:h-12" />
      </a>
    </div>
    
    <!-- Desktop Navigation -->
    <div class="hidden lg:flex items-center space-x-6 rtl:space-x-reverse">
      <a href="{{ route('public.courses.index') }}" 
         class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200">
        {{ __('public.Courses') }}
      </a>
      <a href="{{ route('public.news.index') }}" 
         class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200">
        {{ __('public.News') }}
      </a>
      <a href="{{ route('public.articles.index') }}" 
         class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200">
        Articles
      </a>
      <a href="{{ route('public.events.index') }}" 
         class="text-brandGray-600 hover:text-brandGold-600 transition-colors duration-200">
        {{ __('public.Events') }}
      </a>
      <a href="{{ route('public.gallery.index') }}" 
         class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200">
        {{ __('public.Gallery') }}
      </a>
      <a href="{{ route('public.contact.create') }}" 
         class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200">
        {{ __('public.Contact') }}
      </a>
      {{-- Search icon --}}
      <a href="{{ route('public.search') }}" class="text-brandGray-500 hover:text-brandMaroon-600 transition-colors" aria-label="Search">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </a>
      <a href="{{ route('public.courses.index') }}" 
         class="bg-brandMaroon-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-brandMaroon-700 transition-colors shadow-md">
        {{ __('public.Enroll') }}
      </a>
    </div>

    <!-- Right Side: Translate + User + Hamburger -->
    <div class="flex items-center gap-2">

      {{-- ── Translate: custom dropdown, GT widget hidden ── --}}
      <div class="relative" id="gt-wrapper">
        <button onclick="toggleGT(event)" aria-label="Translate"
                class="flex items-center gap-1 px-2 py-1.5 rounded-lg text-sm text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 border border-gray-200 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
          </svg>
          <span class="hidden sm:inline text-xs font-medium">Translate</span>
        </button>
        <div id="gt-dropdown"
             class="hidden absolute right-0 top-full mt-1 z-50 bg-white rounded-xl shadow-xl border border-gray-200 py-1 min-w-36">
          @foreach([
            ['code'=>'en',    'label'=>'🇬🇧 English'],
            ['code'=>'dv',    'label'=>'🇲🇻 ދިވެހި'],
            ['code'=>'ar',    'label'=>'🇸🇦 العربية'],
            ['code'=>'ur',    'label'=>'🇵🇰 اردو'],
            ['code'=>'hi',    'label'=>'🇮🇳 हिन्दी'],
            ['code'=>'ms',    'label'=>'🇲🇾 Melayu'],
            ['code'=>'fr',    'label'=>'🇫🇷 Français'],
            ['code'=>'de',    'label'=>'🇩🇪 Deutsch'],
          ] as $lang)
          <button onclick="translateTo('{{ $lang['code'] }}')"
                  class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-brandBeige-50 hover:text-brandMaroon-700 transition-colors">
            {{ $lang['label'] }}
          </button>
          @endforeach
        </div>
      </div>
      {{-- GT init element: off-screen but NOT display:none so it initialises properly --}}
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
              {{ auth()->user()->name }}
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div id="user-menu-dropdown"
                 class="absolute right-0 top-full mt-1 z-50 bg-white rounded-xl shadow-xl border border-gray-200 min-w-44 py-1 hidden">
              <a href="{{ route('portal.dashboard') }}"
                 class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-brandBeige-50 hover:text-brandMaroon-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                My Portal
              </a>
              <a href="{{ route('portal.enrollments') }}"
                 class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-brandBeige-50 hover:text-brandMaroon-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                My Enrollments
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

      {{-- ── Hamburger (mobile/tablet) ── --}}
      <button class="lg:hidden p-2 text-brandGray-600 hover:text-brandMaroon-600 transition-colors"
              onclick="toggleMobileMenu()" aria-label="Toggle mobile menu">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

    </div>
  </div>

  <!-- Mobile Navigation -->
  <div id="mobileMenu" class="hidden lg:hidden bg-white border-t shadow-lg">
    <div class="container mx-auto py-4 px-4 space-y-1">
      <!-- Mobile Navigation Links -->
      <a href="{{ route('public.courses.index') }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
        {{ __('public.Courses') }}
      </a>
      <a href="{{ route('public.news.index') }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
        {{ __('public.News') }}
      </a>
      <a href="{{ route('public.events.index') }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandGold-600 hover:bg-brandGold-50 rounded-lg transition-colors duration-200">
        {{ __('public.Events') }}
      </a>
      <a href="{{ route('public.gallery.index') }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
        {{ __('public.Gallery') }}
      </a>
      <a href="{{ route('public.contact.create') }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
        {{ __('public.Contact') }}
      </a>
      
      <!-- Mobile Login/Portal/Logout -->
      <div class="pt-2 border-t border-gray-200 mt-4">
        @auth
          <p class="px-4 py-1 text-xs text-gray-400">Signed in as {{ auth()->user()->name }}</p>
          <a href="{{ route('portal.dashboard') }}"
             class="block py-3 px-4 text-brandMaroon-700 font-medium hover:bg-brandMaroon-50 rounded-lg transition-colors duration-200">
            My Portal
          </a>
          <a href="{{ route('portal.enrollments') }}"
             class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
            My Enrollments
          </a>
          <a href="{{ route('portal.payments') }}"
             class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
            My Payments
          </a>
          <form method="POST" action="{{ route('logout') }}" class="px-4 pt-1 pb-2">
            @csrf
            <button type="submit"
                    class="w-full text-left py-2.5 px-0 text-sm text-red-600 hover:text-red-700 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
              </svg>
              Log out
            </button>
          </form>
        @else
          <a href="{{ route('login') }}"
             class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
            Login
          </a>
        @endauth
      </div>

      <!-- Mobile Apply Now Button -->
      <div class="pt-4">
        <a href="{{ route('public.courses.index') }}" 
           class="block w-full py-3 px-4 bg-brandMaroon-600 text-white text-center rounded-lg font-semibold hover:bg-brandMaroon-700 transition-colors shadow-md">
          {{ __('public.Enroll') }}
        </a>
      </div>
    </div>
  </div>
</nav>

<script>
// ── Google Translate ──────────────────────────────────────────────
function googleTranslateElementInit() {
  new google.translate.TranslateElement({
    pageLanguage: 'en',
    autoDisplay: false
  }, 'google_translate_element');
}

// Trigger translation by programmatically setting the hidden GT select
function translateTo(lang) {
  // Close dropdown
  document.getElementById('gt-dropdown')?.classList.add('hidden');

  if (lang === 'en') {
    // Clear the googtrans cookie on all path/domain variants then reload
    var d = location.hostname;
    ['/', window.location.pathname].forEach(function(path) {
      document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=' + path;
      document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=' + path + '; domain=' + d;
      document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=' + path + '; domain=.' + d;
    });
    window.location.reload();
    return;
  }

  // Swap font class immediately (no layout/direction change)
  var html = document.documentElement;
  html.classList.remove('gt-lang-ar', 'gt-lang-dv');
  if (lang === 'ar' || lang === 'dv') {
    html.classList.add('gt-lang-' + lang);
    // Load font if not already loaded
    var fontUrls = {
      ar: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap',
      dv: 'https://fonts.googleapis.com/css2?family=Noto+Sans+Thaana:wght@400;500;600;700&display=swap'
    };
    if (!document.querySelector('link[href="' + fontUrls[lang] + '"]')) {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = fontUrls[lang];
      document.head.appendChild(link);
    }
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

// Toggle custom translate dropdown
function toggleGT(e) {
  e.stopPropagation();
  document.getElementById('gt-dropdown')?.classList.toggle('hidden');
  document.getElementById('user-menu-dropdown')?.classList.add('hidden');
}

// ── User dropdown ─────────────────────────────────────────────────
function toggleUserMenu() {
  document.getElementById('user-menu-dropdown')?.classList.toggle('hidden');
  document.getElementById('gt-dropdown')?.classList.add('hidden');
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
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
      menu.style.maxHeight = menu.scrollHeight + 'px';
      button.setAttribute('aria-expanded', 'true');
    } else {
      menu.classList.add('hidden');
      menu.style.maxHeight = '0px';
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
      menu.style.maxHeight = '0px';
      button.setAttribute('aria-expanded', 'false');
    }
  });

  // Close mobile menu on window resize
  window.addEventListener('resize', function() {
    const menu = document.getElementById('mobileMenu');
    const button = document.querySelector('[onclick="toggleMobileMenu()"]');
    
    if (!menu || !button) return; // Safety check
    
    if (window.innerWidth >= 1024) { // lg breakpoint
      menu.classList.add('hidden');
      menu.style.maxHeight = '0px';
      button.setAttribute('aria-expanded', 'false');
    }
  });
});
</script>
