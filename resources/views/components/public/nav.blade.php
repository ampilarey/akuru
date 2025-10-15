<nav class="bg-white border-b shadow-sm">
  <div class="container mx-auto flex items-center justify-between py-4 px-4">
    <div class="flex items-center space-x-4 rtl:space-x-reverse">
      <a href="{{ url(app()->getLocale()) }}" class="flex items-center">
        <x-akuru-logo size="h-10" class="mr-3 rtl:ml-3 rtl:mr-0" />
        <span class="font-bold text-xl text-brandBlue-600 hidden sm:inline">{{ __('public.Akuru Institute') }}</span>
      </a>
    </div>
    
    <!-- Desktop Navigation -->
    <div class="hidden md:flex items-center space-x-6 rtl:space-x-reverse">
      <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandBlue-600 transition-colors">
        {{ __('public.Courses') }}
      </a>
      <a href="{{ route('public.news.index', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandBlue-600 transition-colors">
        {{ __('public.News') }}
      </a>
      <a href="{{ route('public.events.index', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandBlue-600 transition-colors">
        {{ __('public.Events') }}
      </a>
      <a href="{{ route('public.gallery.index', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandBlue-600 transition-colors">
        {{ __('public.Gallery') }}
      </a>
      <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
         class="text-brandGray-600 hover:text-brandBlue-600 transition-colors">
        {{ __('public.Contact') }}
      </a>
      <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
         class="btn-primary">
        {{ __('public.Apply Now') }}
      </a>
    </div>

    <!-- Language Switcher & Login -->
    <div class="flex items-center space-x-4 rtl:space-x-reverse">
      <!-- Language Switcher -->
      <div class="flex items-center space-x-2 rtl:space-x-reverse text-sm">
        <a href="{{ LaravelLocalization::getLocalizedURL('en') }}" 
           class="px-2 py-1 rounded {{ app()->getLocale() === 'en' ? 'bg-brandBlue-600 text-white' : 'text-brandGray-600 hover:bg-brandGray-100' }}">
          EN
        </a>
        <a href="{{ LaravelLocalization::getLocalizedURL('ar') }}" 
           class="px-2 py-1 rounded {{ app()->getLocale() === 'ar' ? 'bg-brandBlue-600 text-white' : 'text-brandGray-600 hover:bg-brandGray-100' }}">
          العربية
        </a>
        <a href="{{ LaravelLocalization::getLocalizedURL('dv') }}" 
           class="px-2 py-1 rounded {{ app()->getLocale() === 'dv' ? 'bg-brandBlue-600 text-white' : 'text-brandGray-600 hover:bg-brandGray-100' }}">
          ދިވެހި
        </a>
      </div>

      <!-- Login Link -->
      @auth
        <a href="{{ route('dashboard') }}" class="text-brandGray-600 hover:text-brandBlue-600 transition-colors">
          {{ __('public.Dashboard') }}
        </a>
      @else
        <a href="{{ route('login') }}" class="text-brandGray-600 hover:text-brandBlue-600 transition-colors">
          {{ __('public.Login') }}
        </a>
      @endauth

      <!-- Mobile Menu Button -->
      <button class="md:hidden p-2 text-brandGray-600" onclick="toggleMobileMenu()">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
    </div>
  </div>

  <!-- Mobile Navigation -->
  <div id="mobileMenu" class="hidden md:hidden bg-white border-t">
    <div class="container mx-auto py-4 px-4 space-y-2">
      <a href="{{ route('public.courses.index', app()->getLocale()) }}" 
         class="block py-2 text-brandGray-600 hover:text-brandBlue-600">
        {{ __('public.Courses') }}
      </a>
      <a href="{{ route('public.news.index', app()->getLocale()) }}" 
         class="block py-2 text-brandGray-600 hover:text-brandBlue-600">
        {{ __('public.News') }}
      </a>
      <a href="{{ route('public.events.index', app()->getLocale()) }}" 
         class="block py-2 text-brandGray-600 hover:text-brandBlue-600">
        {{ __('public.Events') }}
      </a>
      <a href="{{ route('public.gallery.index', app()->getLocale()) }}" 
         class="block py-2 text-brandGray-600 hover:text-brandBlue-600">
        {{ __('public.Gallery') }}
      </a>
      <a href="{{ route('public.contact.create', app()->getLocale()) }}" 
         class="block py-2 text-brandGray-600 hover:text-brandBlue-600">
        {{ __('public.Contact') }}
      </a>
      <a href="{{ route('public.admissions.create', app()->getLocale()) }}" 
         class="block py-2 btn-primary text-center">
        {{ __('public.Apply Now') }}
      </a>
    </div>
  </div>
</nav>

<script>
function toggleMobileMenu() {
  const menu = document.getElementById('mobileMenu');
  menu.classList.toggle('hidden');
}
</script>
