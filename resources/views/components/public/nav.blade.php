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

    <!-- Right Side - Translate, Login, Mobile Menu -->
    <div class="flex items-center space-x-2 sm:space-x-4">
      <!-- Google Translate Button -->
      <div id="gt-wrapper" class="relative hidden sm:block">
        <button onclick="toggleGT()" aria-label="Translate page"
                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 border border-gray-200 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
          </svg>
          Translate
        </button>
        {{-- GT widget renders here --}}
        <div id="google_translate_element"
             class="absolute right-0 top-full mt-1 z-50 bg-white rounded-lg shadow-lg border border-gray-200 p-2 hidden min-w-max"></div>
      </div>

      <!-- Login / Portal Link - Hidden on small screens -->
      <div class="hidden md:block">
        @auth
          <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brandMaroon-700 border border-brandMaroon-200 px-3 py-1.5 rounded-lg hover:bg-brandMaroon-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            My Portal
          </a>
        @else
          <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brandGray-600 hover:text-brandMaroon-600 transition-colors">
            {{ __('public.Login') }}
          </a>
        @endauth
      </div>

      <!-- Mobile Menu Button -->
      <button class="lg:hidden p-2 text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200" 
              onclick="toggleMobileMenu()" 
              aria-label="Toggle mobile menu">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
    </div>
  </div>

  <!-- Mobile Navigation -->
  <div id="mobileMenu" class="hidden lg:hidden bg-white border-t shadow-lg">
    <div class="container mx-auto py-4 px-4 space-y-1">
      <!-- Mobile Translate -->
      <div class="py-3 border-b border-gray-200 mb-4">
        <p class="text-xs text-brandGray-400 mb-2 px-1">Translate this page:</p>
        <div id="google_translate_element_mobile"></div>
      </div>

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
      
      <!-- Mobile Login/Portal -->
      <div class="pt-2 border-t border-gray-200 mt-4">
        @auth
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
        @else
          <a href="{{ route('login') }}" 
             class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
            {{ __('public.Login') }}
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
  // Desktop dropdown widget
  new google.translate.TranslateElement({
    pageLanguage: 'en',
    layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
    autoDisplay: false
  }, 'google_translate_element');

  // Mobile inline widget
  new google.translate.TranslateElement({
    pageLanguage: 'en',
    layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
    autoDisplay: false
  }, 'google_translate_element_mobile');
}

function toggleGT() {
  const el = document.getElementById('google_translate_element');
  el.classList.toggle('hidden');
}

// Close GT dropdown when clicking outside
document.addEventListener('click', function(e) {
  const wrapper = document.getElementById('gt-wrapper');
  if (wrapper && !wrapper.contains(e.target)) {
    document.getElementById('google_translate_element')?.classList.add('hidden');
  }
});

// Hide the "Powered by Google" bar that GT injects at the top
const gtStyle = document.createElement('style');
gtStyle.textContent = `
  body { top: 0 !important; }
  .goog-te-banner-frame { display: none !important; }
  .skiptranslate { display: none !important; }
  .goog-te-gadget { font-size: 0 !important; }
  .goog-te-gadget select { font-size: 13px !important; border-radius: 6px; border: 1px solid #e5e7eb; padding: 4px 8px; }
`;
document.head.appendChild(gtStyle);

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
