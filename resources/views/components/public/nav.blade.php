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

      {{-- ── Translate dropdown ── --}}
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
             class="hidden absolute right-0 top-full mt-1 z-50 bg-white rounded-xl shadow-xl border border-gray-200 w-56">

          {{-- Pinned: English · Arabic · Dhivehi --}}
          <div class="flex gap-1 p-2 border-b border-gray-100">
            <button onclick="translateTo('en')"
                    class="gt-pin flex-1 text-center text-xs font-semibold py-2 rounded-lg border border-gray-200 hover:border-brandMaroon-400 hover:bg-brandBeige-50 transition-colors text-gray-700"
                    data-code="en">🇬🇧 EN</button>
            <button onclick="translateTo('ar')"
                    class="gt-pin flex-1 text-center text-xs font-semibold py-2 rounded-lg border border-gray-200 hover:border-brandMaroon-400 hover:bg-brandBeige-50 transition-colors text-gray-700"
                    data-code="ar">🇸🇦 AR</button>
            <button onclick="translateTo('dv')"
                    class="gt-pin flex-1 text-center text-xs font-semibold py-2 rounded-lg border border-gray-200 hover:border-brandMaroon-400 hover:bg-brandBeige-50 transition-colors text-gray-700"
                    data-code="dv">🇲🇻 DV</button>
          </div>

          {{-- Search other languages --}}
          <div class="p-2 border-b border-gray-100">
            <div class="relative">
              <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input id="gt-search" type="text" placeholder="Search language…"
                     oninput="filterGTLangs(this.value)"
                     class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-brandMaroon-400">
            </div>
          </div>

          {{-- Other languages list --}}
          <div id="gt-lang-list" class="max-h-44 overflow-y-auto py-1"></div>
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
  renderGTList('');
  markActiveLang();
}

function renderGTList(query) {
  var list = document.getElementById('gt-lang-list');
  if (!list) return;
  var q = query.toLowerCase().trim();
  var filtered = GT_LANGS.filter(function(l) {
    return !GT_PINNED.includes(l.c) && (!q || l.l.toLowerCase().includes(q));
  });
  if (!filtered.length) {
    list.innerHTML = '<p class="px-4 py-3 text-xs text-gray-400">No results for "' + query + '"</p>';
    return;
  }
  list.innerHTML = filtered.map(function(l) {
    return '<button onclick="translateTo(\'' + l.c + '\')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-brandBeige-50 hover:text-brandMaroon-700 transition-colors">' + l.l + '</button>';
  }).join('');
}

function filterGTLangs(q) { renderGTList(q); }

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
  document.getElementById('gt-search') && (document.getElementById('gt-search').value = '');
  renderGTList('');

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
  var fontUrls = {
    ar: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap',
    dv: null  // Faruma / MV Boli are system fonts — no external load needed
  };
  if (fontUrls[lang] !== undefined) {
    html.classList.add('gt-lang-' + lang);
    if (fontUrls[lang] && !document.querySelector('link[href="' + fontUrls[lang] + '"]')) {
      var link = document.createElement('link'); link.rel = 'stylesheet'; link.href = fontUrls[lang];
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
