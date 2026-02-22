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
        <p style="color:rgba(255,255,255,0.75);font-size:.875rem;line-height:1.7;margin-bottom:1.5rem;max-width:28rem">
          {{ __('public.footer_description') }}
        </p>
        <div style="display:flex;flex-direction:column;gap:.75rem">
          <div style="display:flex;align-items:center;gap:.75rem">
            <div style="width:1.75rem;height:1.75rem;border-radius:.5rem;background:rgba(201,162,39,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg style="width:.875rem;height:.875rem;color:#C9A227" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
            </div>
            <span style="color:rgba(255,255,255,0.75);font-size:.875rem">{{ $siteSettings['address'] ?? 'Malé, Republic of Maldives' }}</span>
          </div>
          <div style="display:flex;align-items:center;gap:.75rem">
            <div style="width:1.75rem;height:1.75rem;border-radius:.5rem;background:rgba(201,162,39,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg style="width:.875rem;height:.875rem;color:#C9A227" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>
            </div>
            <a href="tel:{{ $siteSettings['phone'] ?? '+9607972434' }}" style="color:rgba(255,255,255,0.75);font-size:.875rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.75)'">{{ $siteSettings['phone'] ?? '+960 797 2434' }}</a>
          </div>
          <div style="display:flex;align-items:center;gap:.75rem">
            <div style="width:1.75rem;height:1.75rem;border-radius:.5rem;background:rgba(201,162,39,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg style="width:.875rem;height:.875rem;color:#C9A227" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
            </div>
            <a href="mailto:{{ $siteSettings['email'] ?? 'info@akuru.edu.mv' }}" style="color:rgba(255,255,255,0.75);font-size:.875rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.75)'">{{ $siteSettings['email'] ?? 'info@akuru.edu.mv' }}</a>
          </div>
          <div style="display:flex;align-items:center;gap:.75rem">
            <div style="width:1.75rem;height:1.75rem;border-radius:.5rem;background:#7C3AED;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg style="width:.875rem;height:.875rem" fill="white" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
            </div>
            <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}" style="color:rgba(255,255,255,0.75);font-size:.875rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.75)'">Viber: +{{ $siteSettings['viber'] ?? '960 797 2434' }}</a>
          </div>
        </div>
      </div>

      {{-- Quick Links --}}
      <div>
        <h4 style="font-weight:700;font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;color:#C9A227;margin-bottom:1.25rem">Quick Links</h4>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.625rem">
          @foreach([
            ['About Us', route('public.about', app()->getLocale())],
            ['Courses', route('public.courses.index')],
            ['Admissions', route('public.admissions.create', app()->getLocale())],
            ['News', route('public.news.index', app()->getLocale())],
            ['Events', route('public.events.index', app()->getLocale())],
            ['Gallery', route('public.gallery.index')],
            ['Contact', route('public.contact.create', app()->getLocale())],
          ] as [$label, $url])
          <li><a href="{{ $url }}" style="color:rgba(255,255,255,0.72);font-size:.875rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.72)'">{{ $label }}</a></li>
          @endforeach
        </ul>
      </div>

      {{-- Programs --}}
      <div>
        <h4 style="font-weight:700;font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;color:#C9A227;margin-bottom:1.25rem">Programs</h4>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.625rem">
          @foreach([
            ['Quran Memorization', route('public.courses.index')],
            ['Arabic Language', route('public.courses.index')],
            ['Islamic Studies', route('public.courses.index')],
            ['Tajweed Classes', route('public.courses.index')],
            ['Adult Education', route('public.courses.index')],
          ] as [$label, $url])
          <li><a href="{{ $url }}" style="color:rgba(255,255,255,0.72);font-size:.875rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.72)'">{{ $label }}</a></li>
          @endforeach
        </ul>
      </div>
    </div>

    {{-- Bottom bar --}}
    <div style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid rgba(255,255,255,0.12);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
      <p style="color:rgba(255,255,255,0.5);font-size:.8rem;margin:0">© {{ date('Y') }} Akuru Institute. All rights reserved.</p>
      <div style="display:flex;align-items:center;gap:1.25rem">
        <a href="{{ route('public.page.show', 'privacy-policy') }}" style="color:rgba(255,255,255,0.5);font-size:.75rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">Privacy Policy</a>
        <a href="{{ route('public.page.show', 'terms') }}" style="color:rgba(255,255,255,0.5);font-size:.75rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">Terms of Service</a>
        <a href="{{ route('public.page.show', 'refund-policy') }}" style="color:rgba(255,255,255,0.5);font-size:.75rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">Refund Policy</a>
      </div>
    </div>
  </div>
</footer>
