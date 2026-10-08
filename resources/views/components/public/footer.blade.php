@props(['compact' => false])
@if ($compact)
{{-- STATUS §5kz, the owner's decision (2026-09-29): on a shop's own pages the
     vendors asked for their brand to lead, so the Akuru footer shrinks to its
     copyright line. The header, the cart and "at Akuru Bookstore" stay. --}}
<footer class="shop-footer" data-testid="footer-compact" style="background:#3D1219">
  <div style="height:3px;background:linear-gradient(90deg,#A8861F,#C9A227,#E8BC3C,#C9A227,#A8861F)"></div>
  <div class="container mx-auto px-4 py-4">
    <p style="color:rgba(255,255,255,0.75);font-size:.8rem;margin:0;text-align:center">© {{ date('Y') }} {{ __('public.Akuru Institute') }}. {{ __('site.rights_reserved') }}</p>
  </div>
</footer>
@else
<footer style="background:linear-gradient(160deg,#3D1219 0%,#491821 60%,#5A1F28 100%)">
  {{-- Gold top accent --}}
  <div style="height:4px;background:linear-gradient(90deg,#A8861F,#C9A227,#E8BC3C,#C9A227,#A8861F)"></div>

  <div class="container mx-auto py-12 px-4">
    <div class="footer-grid">

      {{-- Brand column --}}
      <div>
        <div class="flex items-center gap-3 mb-5">
          <x-akuru-logo size="h-16" variant="on-dark" />
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

      {{-- The site's links, grouped by product (STATUS §5kk): open on a desk, folded on a phone. --}}
      @php
        $footerGroups = [
            ['e-learning', __('site.e_learning'), [
                [__('site.all_courses'), route('public.courses.index')],
                [__('site.quran_memorization'), route('public.courses.index')],
                [__('site.arabic_language'), route('public.courses.index')],
                [__('site.islamic_studies'), route('public.courses.index')],
                [__('site.tajweed'), route('public.courses.index')],
            ]],
            ['library', __('site.digital_library'), [
                // R5: books, articles and research are one shelf, filtered.
                [__('site.browse_books'), route('public.library.index', ['content_type' => 'book'])],
                [__('site.articles'), route('public.library.index', ['content_type' => 'article'])],
                [__('site.research'), route('public.library.index', ['content_type' => 'research'])],
                [__('site.authors'), route('public.library.index').'#authors'],
                [__('site.gift_cards'), route('public.gift-cards.index')],
                [__('site.write_for_akuru'), route('write.index')],
            ]],
            ['bookstore', __('site.bookstore'), [
                [__('site.shop'), route('public.shop.index')],
                [__('site.shops'), route('public.shop.index').'#shops'],
                [__('site.sell_on_akuru'), route('vendor.apply')],
                [__('site.shop_owner_signin'), route('vendor.index')],
                [__('site.delivery_returns'), route('public.page.show', 'delivery-and-returns')],
            ]],
            ['school', __('site.school'), [
                [__('site.admissions'), route('public.admissions.create')],
                [__('site.parent_portal'), route('dashboard')],
                [__('site.careers'), route('public.careers')],
            ]],
            ['about', __('site.about'), [
                [__('site.about_us'), route('public.about')],
                [__('site.news'), route('public.news.index')],
                [__('site.events'), route('public.events.index')],
                [__('site.gallery'), route('public.gallery.index')],
                [__('site.daily_reminders'), route('public.daily.index', 'ayah')],
                [__('site.prayer_times'), route('public.prayer-times')],
                [__('site.contact'), route('public.contact.create')],
            ]],
        ];
      @endphp
      @foreach ($footerGroups as [$key, $title, $links])
        <details class="footer-group" data-footer-group data-testid="footer-{{ $key }}" open>
          <summary>{{ $title }}
            <svg class="footer-chevron" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </summary>
          <ul>
            @foreach ($links as [$label, $url])
              <li><a href="{{ $url }}">{{ $label }}</a></li>
            @endforeach
          </ul>
        </details>
      @endforeach
    </div>

    {{-- Bottom bar --}}
    <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid rgba(255,255,255,0.12);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
      <p style="color:rgba(255,255,255,0.5);font-size:.8rem;margin:0">© {{ date('Y') }} {{ __('public.Akuru Institute') }}. {{ __('site.rights_reserved') }}</p>
      <div style="display:flex;align-items:center;gap:1.25rem">
        <a href="{{ route('public.page.show', 'privacy-policy') }}" style="color:rgba(255,255,255,0.5);font-size:.75rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">{{ __('site.privacy') }}</a>
        <a href="{{ route('public.page.show', 'terms') }}" style="color:rgba(255,255,255,0.5);font-size:.75rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">{{ __('site.terms') }}</a>
        <a href="{{ route('public.page.show', 'refund-policy') }}" style="color:rgba(255,255,255,0.5);font-size:.75rem;text-decoration:none" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">{{ __('site.refunds') }}</a>
      </div>
    </div>
  </div>
</footer>
<style>
  .footer-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
  @media (min-width: 768px) { .footer-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 2.5rem 2rem; } }
  @media (min-width: 1024px) { .footer-grid { grid-template-columns: 1.7fr repeat(5, minmax(0, 1fr)); } }
  .footer-group summary { list-style: none; display: flex; align-items: center; justify-content: space-between; font-weight: 700; font-size: .75rem; text-transform: uppercase; letter-spacing: 0.1em; color: #C9A227; margin-bottom: 1rem; }
  .footer-group summary::-webkit-details-marker { display: none; }
  .footer-group ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .6rem; }
  .footer-group a { color: rgba(255,255,255,.72); font-size: .875rem; text-decoration: none; }
  .footer-group a:hover { color: #fff; }
  .footer-chevron { display: none; }
  /* A phone folds each group behind its name, so the footer is a short list. */
  @media (max-width: 767px) {
    .footer-group { border-bottom: 1px solid rgba(255,255,255,.12); }
    .footer-group summary { cursor: pointer; margin: 0; min-height: 3rem; font-size: .9rem; letter-spacing: .04em; text-transform: none; color: #fff; }
    .footer-chevron { display: block; transition: transform .2s; }
    .footer-group[open] .footer-chevron { transform: rotate(180deg); }
    .footer-group ul { padding: 0 0 1rem; }
  }
  @media (min-width: 768px) { .footer-group summary { pointer-events: none; } }
</style>
<script>
  // Open on a desk, folded on a phone; the page is served open so the links
  // are there without script.
  (function () {
    var mq = window.matchMedia('(min-width: 768px)');
    function sync() {
      document.querySelectorAll('[data-footer-group]').forEach(function (group) { group.open = mq.matches; });
    }
    sync();
    (mq.addEventListener ? mq.addEventListener('change', sync) : mq.addListener(sync));
  })();
</script>
@endif
