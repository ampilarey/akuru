<footer style="background:linear-gradient(160deg,#3D1219 0%,#491821 60%,#5A1F28 100%)">
  {{-- Gold top accent --}}
  <div style="height:4px;background:linear-gradient(90deg,#A8861F,#C9A227,#E8BC3C,#C9A227,#A8861F)"></div>

  <div class="container mx-auto py-12 px-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-10">

      {{-- Brand column --}}
      <div class="col-span-1 md:col-span-2">
        <div class="flex items-center gap-3 mb-5">
          <x-akuru-logo size="h-12" class="brightness-0 invert" />
        </div>
        <p class="text-white/65 text-sm leading-relaxed mb-6 max-w-sm">
          {{ __('public.footer_description') }}
        </p>
        <div class="space-y-2.5">
          <div class="flex items-center gap-3 text-white/70 text-sm">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(201,162,39,0.2)">
              <svg class="w-3.5 h-3.5" style="color:#C9A227" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
            </div>
            <span>{{ $siteSettings['address'] ?? 'Malé, Republic of Maldives' }}</span>
          </div>
          <div class="flex items-center gap-3 text-white/70 text-sm">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(201,162,39,0.2)">
              <svg class="w-3.5 h-3.5" style="color:#C9A227" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>
            </div>
            <a href="tel:{{ $siteSettings['phone'] ?? '+9607972434' }}" class="hover:text-white transition-colors">{{ $siteSettings['phone'] ?? '+960 797 2434' }}</a>
          </div>
          <div class="flex items-center gap-3 text-white/70 text-sm">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(201,162,39,0.2)">
              <svg class="w-3.5 h-3.5" style="color:#C9A227" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
            </div>
            <a href="mailto:{{ $siteSettings['email'] ?? 'info@akuru.edu.mv' }}" class="hover:text-white transition-colors">{{ $siteSettings['email'] ?? 'info@akuru.edu.mv' }}</a>
          </div>
          <div class="flex items-center gap-3 text-white/70 text-sm">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 bg-purple-700">
              <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
            </div>
            <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}" class="hover:text-white transition-colors">Viber: +{{ $siteSettings['viber'] ?? '960 797 2434' }}</a>
          </div>
        </div>
      </div>

      {{-- Quick Links --}}
      <div>
        <h4 class="font-bold text-sm uppercase tracking-widest mb-5" style="color:#C9A227">Quick Links</h4>
        <ul class="space-y-2.5">
          @foreach([
            ['About Us', route('public.about', app()->getLocale())],
            ['Courses', route('public.courses.index')],
            ['Admissions', route('public.admissions.create', app()->getLocale())],
            ['News', route('public.news.index', app()->getLocale())],
            ['Events', route('public.events.index', app()->getLocale())],
            ['Gallery', route('public.gallery.index')],
            ['Contact', route('public.contact.create', app()->getLocale())],
          ] as [$label, $url])
          <li><a href="{{ $url }}" class="text-white/60 hover:text-white text-sm transition-colors">{{ $label }}</a></li>
          @endforeach
        </ul>
      </div>

      {{-- Programs --}}
      <div>
        <h4 class="font-bold text-sm uppercase tracking-widest mb-5" style="color:#C9A227">Programs</h4>
        <ul class="space-y-2.5">
          @foreach([
            ['Quran Memorization', route('public.courses.index')],
            ['Arabic Language', route('public.courses.index')],
            ['Islamic Studies', route('public.courses.index')],
            ['Tajweed Classes', route('public.courses.index')],
            ['Adult Education', route('public.courses.index')],
          ] as [$label, $url])
          <li><a href="{{ $url }}" class="text-white/60 hover:text-white text-sm transition-colors">{{ $label }}</a></li>
          @endforeach
        </ul>
      </div>
    </div>

    {{-- Bottom bar --}}
    <div class="mt-10 pt-6 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">
      <p class="text-white/40 text-sm">© {{ date('Y') }} Akuru Institute. All rights reserved.</p>
      <div class="flex items-center gap-5">
        <a href="{{ route('public.page.show', 'privacy-policy') }}" class="text-white/40 hover:text-white text-xs transition-colors">Privacy Policy</a>
        <a href="{{ route('public.page.show', 'terms') }}" class="text-white/40 hover:text-white text-xs transition-colors">Terms of Service</a>
        <a href="{{ route('public.page.show', 'refund-policy') }}" class="text-white/40 hover:text-white text-xs transition-colors">Refund Policy</a>
      </div>
    </div>
  </div>
</footer>
