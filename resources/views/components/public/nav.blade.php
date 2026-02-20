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
      <a href="{{ route('public.news.index', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200">
        {{ __('public.News') }}
      </a>
      <a href="{{ route('public.events.index', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandGold-600 transition-colors duration-200">
        {{ __('public.Events') }}
      </a>
      <a href="{{ route('public.gallery.index', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200">
        {{ __('public.Gallery') }}
      </a>
      <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200">
        {{ __('public.Contact') }}
      </a>
      <a href="{{ route('public.courses.index') }}" 
         class="bg-brandMaroon-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-brandMaroon-700 transition-colors shadow-md">
        {{ __('public.Enroll') }}
      </a>
    </div>

    <!-- Right Side - Language Switcher, Login, Mobile Menu -->
    <div class="flex items-center space-x-2 sm:space-x-4 rtl:space-x-reverse">
      <!-- Language Switcher - Hidden on very small screens -->
      <div class="hidden sm:flex items-center space-x-1 rtl:space-x-reverse text-sm">
        <a href="{{ LaravelLocalization::getLocalizedURL('en') }}" 
           class="px-2 py-1 rounded text-xs {{ app()->getLocale() === 'en' ? 'bg-brandMaroon-600 text-white' : 'text-brandGray-600 hover:bg-brandGold-100' }}">
          EN
        </a>
        <a href="{{ LaravelLocalization::getLocalizedURL('ar') }}" 
           class="px-2 py-1 rounded text-xs {{ app()->getLocale() === 'ar' ? 'bg-brandMaroon-600 text-white' : 'text-brandGray-600 hover:bg-brandGold-100' }}">
          العربية
        </a>
        <a href="{{ LaravelLocalization::getLocalizedURL('dv') }}" 
           class="px-2 py-1 rounded text-xs {{ app()->getLocale() === 'dv' ? 'bg-brandMaroon-600 text-white' : 'text-brandGray-600 hover:bg-brandGold-100' }}">
          ދިވެހި
        </a>
      </div>

      <!-- Login Link - Hidden on small screens -->
      <div class="hidden md:block">
        @auth
          <a href="{{ route('my.enrollments') }}" class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200 text-sm">
            My Enrollments
          </a>
          &nbsp;
          <a href="{{ route('dashboard') }}" class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200 text-sm">
            {{ __('public.Dashboard') }}
          </a>
        @else
          <a href="{{ route('login') }}" class="text-brandGray-600 hover:text-brandMaroon-600 transition-colors duration-200 text-sm">
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
      <!-- Mobile Language Switcher -->
      <div class="flex items-center justify-center space-x-2 py-3 border-b border-gray-200 mb-4">
        <span class="text-sm text-brandGray-500 mr-2">{{ __('public.Language') }}:</span>
        <a href="{{ LaravelLocalization::getLocalizedURL('en') }}" 
           class="px-3 py-1 rounded text-sm {{ app()->getLocale() === 'en' ? 'bg-brandMaroon-600 text-white' : 'text-brandGray-600 hover:bg-brandGold-100' }}">
          EN
        </a>
        <a href="{{ LaravelLocalization::getLocalizedURL('ar') }}" 
           class="px-3 py-1 rounded text-sm {{ app()->getLocale() === 'ar' ? 'bg-brandMaroon-600 text-white' : 'text-brandGray-600 hover:bg-brandGold-100' }}">
          العربية
        </a>
        <a href="{{ LaravelLocalization::getLocalizedURL('dv') }}" 
           class="px-3 py-1 rounded text-sm {{ app()->getLocale() === 'dv' ? 'bg-brandMaroon-600 text-white' : 'text-brandGray-600 hover:bg-brandGold-100' }}">
          ދިވެހި
        </a>
      </div>

      <!-- Mobile Navigation Links -->
      <a href="{{ route('public.courses.index') }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
        {{ __('public.Courses') }}
      </a>
      <a href="{{ route('public.news.index', app()->getLocale()) }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
        {{ __('public.News') }}
      </a>
      <a href="{{ route('public.events.index', app()->getLocale()) }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandGold-600 hover:bg-brandGold-50 rounded-lg transition-colors duration-200">
        {{ __('public.Events') }}
      </a>
      <a href="{{ route('public.gallery.index', app()->getLocale()) }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
        {{ __('public.Gallery') }}
      </a>
      <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
         class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
        {{ __('public.Contact') }}
      </a>
      
      <!-- Mobile Login/Dashboard -->
      <div class="pt-2 border-t border-gray-200 mt-4">
        @auth
          <a href="{{ route('my.enrollments') }}"
             class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
            My Enrollments
          </a>
          <a href="{{ route('dashboard') }}" 
             class="block py-3 px-4 text-brandGray-600 hover:text-brandMaroon-600 hover:bg-brandBeige-100 rounded-lg transition-colors duration-200">
            {{ __('public.Dashboard') }}
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
