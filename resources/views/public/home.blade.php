@extends('public.layouts.public')
@section('title', $text['title'] ?? 'Welcome to Akuru Institute')
@section('description', $text['desc'] ?? 'Learn Quran, Arabic, and Islamic Studies in the Maldives')

@section('content')

{{-- ═══════════════════════════════════════════════════════════
  SECTION 1 — HERO CAROUSEL
  CSS translateX slide technique (works in all browsers, no Alpine.js dependency)
═══════════════════════════════════════════════════════════ --}}
@php
$bannerList = $heroBanners->map(fn($b) => [
    'title'    => $b->title    ?? $text['title'] ?? 'Welcome to Akuru Institute',
    'subtitle' => $b->subtitle ?? $text['desc']  ?? 'Learn Quran, Arabic, and Islamic Studies in the Maldives',
    'cta_text' => $b->cta_text ?? null,
    'cta_url'  => $b->cta_url  ?? null,
])->values()->toArray();
$bannerCount = count($bannerList);
@endphp

<section id="akuru-hero"
    style="background:linear-gradient(135deg,#3D1219 0%,#7C2D37 55%,#5A1F28 100%);position:relative;overflow:hidden;padding-bottom:56px">

  {{-- subtle pattern overlay --}}
  <div style="position:absolute;inset:0;opacity:.07;pointer-events:none;background-image:url(&quot;data:image/svg+xml,%3Csvg width='52' height='26' viewBox='0 0 52 26' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23C9A227' fill-opacity='1'%3E%3Cpath d='M10 10c0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6h2c0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4v2c-3.314 0-6-2.686-6-6 0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6zm25.464-1.95l8.486 8.486-1.414 1.414-8.486-8.486 1.414-1.414z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;)"></div>

  {{-- Slide stage — overflow hidden, track slides horizontally --}}
  <div style="position:relative">
  {{-- min-height (not height): on narrow screens the slide content is taller
       than the clamp and a fixed height clips the CTA under the trust block --}}
  <div style="position:relative;min-height:clamp(20rem,55vw,28rem);overflow:hidden">
    <div id="akuru-track" style="display:flex;transition:transform .7s cubic-bezier(.4,0,.2,1);will-change:transform">
      @foreach($bannerList as $i => $bn)
      <div style="min-width:100%;min-height:clamp(20rem,55vw,28rem);display:flex;align-items:center;justify-content:center">
        <div class="container mx-auto px-4 text-center text-white"
             style="width:100%;padding-top:4.5rem;padding-bottom:{{ $bannerCount > 1 ? '3rem' : '4.5rem' }}">
          <span style="display:inline-block;background:rgba(201,162,39,0.2);border:1px solid rgba(201,162,39,0.4);color:#E8BC3C;font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.375rem 1rem;border-radius:9999px;margin-bottom:1.25rem">
            🕌 Islamic Education in the Maldives
          </span>
          <h1 style="font-size:clamp(1.75rem,4.5vw,3.25rem);font-weight:800;line-height:1.15;margin-bottom:1.25rem;text-shadow:0 2px 16px rgba(0,0,0,.4)">
            {{ $bn['title'] }}
          </h1>
          <p style="font-size:clamp(.95rem,2vw,1.2rem);color:rgba(255,255,255,.8);max-width:40rem;margin:0 auto 2.25rem;line-height:1.7">
            {{ $bn['subtitle'] }}
          </p>
          <div style="display:flex;flex-wrap:wrap;gap:.875rem;justify-content:center">
            <a href="{{ $bn['cta_url'] ?? route('public.courses.index') }}"
               style="display:inline-flex;align-items:center;gap:.5rem;background:#C9A227;color:#3D1219;font-weight:700;padding:.875rem 2rem;border-radius:.75rem;font-size:1.05rem;text-decoration:none;transition:opacity .2s,transform .2s"
               onmouseover="this.style.opacity='.88';this.style.transform='scale(1.04)'" onmouseout="this.style.opacity='1';this.style.transform='scale(1)'">
              {{ $bn['cta_text'] ?? 'Enroll Now' }}
            </a>
            <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to know about Akuru Institute.') }}"
               style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.12);color:white;border:2px solid rgba(255,255,255,.35);font-weight:600;padding:.875rem 2rem;border-radius:.75rem;font-size:1.05rem;text-decoration:none;transition:background .2s"
               onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
              <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
              Chat on Viber
            </a>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>{{-- end slide stage --}}

  @if($bannerCount > 1)
  {{-- Prev / Next arrows — hidden on mobile --}}
  <button id="akuru-prev" class="hero-arrow" aria-label="Previous slide"
      style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:white;width:2.5rem;height:2.5rem;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .2s;z-index:10;padding:0"
      onmouseover="this.style.background='rgba(255,255,255,.3)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
  </button>
  <button id="akuru-next" class="hero-arrow" aria-label="Next slide"
      style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:white;width:2.5rem;height:2.5rem;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .2s;z-index:10;padding:0"
      onmouseover="this.style.background='rgba(255,255,255,.3)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
  </button>

  {{-- Dot indicators --}}
  <div style="position:absolute;bottom:1rem;left:0;right:0;display:flex;justify-content:center;align-items:center;gap:6px;z-index:10">
    @foreach($bannerList as $i => $bn)
    <span class="akuru-dot" data-idx="{{ $i }}" aria-label="Slide {{ $i + 1 }}"
          style="display:inline-block;border-radius:9999px;cursor:pointer;flex-shrink:0;transition:all .35s;{{ $i === 0 ? 'width:20px;height:6px;background:#C9A227' : 'width:6px;height:6px;background:rgba(255,255,255,.5)' }}"></span>
    @endforeach
  </div>
  @endif
  </div>{{-- end hero stage (slides + arrows + dots) --}}

  <style>
    @media (max-width: 639px) { .hero-arrow { display: none !important; } }
  </style>

  {{-- White wave into next section --}}
  <div style="position:absolute;bottom:0;left:0;right:0;line-height:0">
    <svg viewBox="0 0 1440 56" preserveAspectRatio="none" style="width:100%;height:56px;display:block"><path d="M0 56h1440V28C1080 56 720 0 360 28 180 42 0 28 0 28v28z" fill="white"/></svg>
  </div>

  @if($bannerCount > 1)
  <script>
  (function () {
    var track  = document.getElementById('akuru-track');
    var dots   = document.querySelectorAll('.akuru-dot');
    var prev   = document.getElementById('akuru-prev');
    var next   = document.getElementById('akuru-next');
    var total  = {{ $bannerCount }};
    var active = 0;
    var paused = false;
    var timer  = null;

    function go(i) {
      active = ((i % total) + total) % total;
      track.style.transform = 'translateX(-' + (active * 100) + '%)';
      dots.forEach(function (d, j) {
        if (j === active) {
          d.style.width = '20px'; d.style.height = '6px'; d.style.background = '#C9A227';
        } else {
          d.style.width = '6px';  d.style.height = '6px'; d.style.background = 'rgba(255,255,255,.5)';
        }
      });
      reset();
    }

    function reset() {
      clearInterval(timer);
      timer = setInterval(function () { if (!paused) go(active + 1); }, 5500);
    }

    if (prev) prev.addEventListener('click', function () { go(active - 1); });
    if (next) next.addEventListener('click', function () { go(active + 1); });

    dots.forEach(function (d) {
      d.addEventListener('click', function () { go(parseInt(d.dataset.idx)); });
    });

    var section = document.getElementById('akuru-hero');
    section.addEventListener('mouseenter', function () { paused = true; });
    section.addEventListener('mouseleave', function () { paused = false; });

    var tx = 0;
    section.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; }, { passive: true });
    section.addEventListener('touchend',   function (e) {
      var dx = e.changedTouches[0].clientX - tx;
      if (Math.abs(dx) > 45) { go(dx < 0 ? active + 1 : active - 1); }
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
      document.hidden ? clearInterval(timer) : reset();
    });

    reset();
  }());
  </script>
  @endif

  @include('public.home._trust')

</section>

{{-- The four products, straight under the hero (the 2026-09-28 website design, STATUS §5ki).
     Also carries the home page's shared styles: section heads, and the rows that are a grid
     on a desk and a sideways swipe on a phone. --}}
@php
  $homeTiles = [
      ['courses', __('site.e_learning'), __('site.courses_tile'), __('site.browse_courses'), route('public.courses.index'),
       'M4 5h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm6 3.5v5l4-2.5-4-2.5zM8 21h8'],
      ['library', __('site.digital_library'), __('site.library_tile'), __('site.open_library'), route('public.library.index'),
       'M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11'],
      ['bookstore', __('site.bookstore'), __('site.bookstore_tile'), __('site.visit_store'), route('public.shop.index'),
       'M5 8h14l-1 12H6L5 8zm4 0V6a3 3 0 016 0v2'],
      ['school', __('site.school'), __('site.school_tile'), __('site.admissions'), route('public.admissions.create'),
       'M3 10l9-5 9 5-9 5-9-5zm4 2v5c3 2 7 2 10 0v-5m4-2v5'],
  ];
@endphp
<style>
  .home-eyebrow { display: block; color: #7C2D37; font-weight: 700; font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; }
  .home-h2 { font-size: clamp(1.5rem, 3vw, 2.25rem); font-weight: 800; color: #111827; margin: .25rem 0 0; }
  .home-head { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem; }
  .home-more { color: #7C2D37; font-weight: 700; font-size: .9rem; text-decoration: none; display: inline-flex; align-items: center; gap: .25rem; }
  .home-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; }
  .home-row--books { grid-template-columns: repeat(6, minmax(0, 1fr)); }
  @media (max-width: 1023px) {
    .home-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .home-row--books { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  /* A phone swipes a row sideways instead of stacking it, so the page is
     half as long and the next section is always in reach. */
  @media (max-width: 639px) {
    .home-row, .home-row--books { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scroll-padding-inline: 1rem; gap: .75rem; margin: 0 -1rem; padding: 0 1rem .75rem; scrollbar-width: none; }
    .home-row::-webkit-scrollbar { display: none; }
    .home-row > * { flex: 0 0 78%; scroll-snap-align: start; }
    .home-row--books > * { flex: 0 0 40%; }
  }
  /* A phone's hero is shorter, so the four products are in the first screen. */
  @media (max-width: 639px) {
    #akuru-hero .container { padding-top: 2rem !important; padding-bottom: 2.5rem !important; }
    #akuru-hero h1 { margin-bottom: .75rem !important; }
    #akuru-hero p { margin-bottom: 1.25rem !important; }
  }
  .home-tiles { position: relative; z-index: 2; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; margin-top: -3rem; }
  .home-tile { display: flex; flex-direction: column; gap: .6rem; padding: 1.5rem; background: #fff; border: 1px solid #EDE4D8; border-radius: 1rem; box-shadow: 0 12px 30px rgba(60, 20, 27, .12); color: #1F1A17; text-decoration: none; transition: transform .2s, box-shadow .2s; }
  .home-tile:hover { transform: translateY(-3px); box-shadow: 0 16px 36px rgba(60, 20, 27, .16); }
  .home-tile-icon { width: 3rem; height: 3rem; border-radius: .75rem; background: #F6ECEE; color: #7C2D37; display: flex; align-items: center; justify-content: center; }
  .home-tile strong { font-size: 1.2rem; }
  .home-tile-line { font-size: .925rem; line-height: 1.5; color: #5E5650; }
  .home-tile-go { margin-top: auto; font-size: .925rem; font-weight: 700; color: #7C2D37; }
  @media (max-width: 1023px) { .home-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 639px) {
    .home-tiles { gap: .75rem; margin-top: -1.5rem; }
    .home-tile { padding: .9rem; gap: .35rem; box-shadow: 0 6px 18px rgba(60, 20, 27, .10); }
    .home-tile-icon { width: 2.5rem; height: 2.5rem; }
    .home-tile strong { font-size: 1rem; }
    .home-tile-line { font-size: .8rem; }
    .home-tile-go { display: none; }
  }
</style>
<section style="background:#FFFFFF;padding:0 0 1.5rem" data-testid="home-products">
  <div class="container mx-auto px-4">
    <div class="home-tiles">
      @foreach ($homeTiles as [$key, $name, $line, $go, $href, $icon])
        <a href="{{ $href }}" class="home-tile" data-testid="home-tile-{{ $key }}">
          <span class="home-tile-icon"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/></svg></span>
          <strong>{{ $name }}</strong>
          <span class="home-tile-line">{{ $line }}</span>
          <span class="home-tile-go">{{ $go }} <span class="rtl-flip" aria-hidden="true">→</span></span>
        </a>
      @endforeach
    </div>
  </div>
</section>

@include('public.home._daily')

{{-- ═══════════════════════════════════════════════════════════
  SECTION 2 — OPEN COURSES   bg: white
═══════════════════════════════════════════════════════════ --}}
<section style="background:#FFFFFF;padding:3rem 0 3.5rem" data-testid="home-courses-section">
  <div class="container mx-auto px-4">
    <div class="home-head">
      <div>
        <span class="home-eyebrow">{{ __('site.e_learning') }}</span>
        <h2 class="home-h2">{{ __('site.open_courses') }}</h2>
      </div>
      <a href="{{ route('public.courses.index') }}" class="home-more" data-testid="home-all-courses">
        {{ __('site.all_courses') }} <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </a>
    </div>
    <div class="home-row" data-testid="home-courses">
      @forelse($courses->take(4) as $course)
      @php
        $isModel = is_object($course) && method_exists($course,'getAttribute');
        $cSlug  = $isModel ? ($course->slug ?? '') : '';
        $cTitle = $isModel ? ($course->title ?? '') : '';
        $cDesc  = $isModel ? ($course->short_desc ?? '') : '';
        $cFee   = $isModel ? ($course->fee ?? null) : null;
        $cStatus = $isModel ? ($course->status ?? 'open') : 'open';
        $cDate  = $isModel && !empty($course->start_date) ? \Carbon\Carbon::parse($course->start_date) : null;
        $cSeats = $isModel ? ($course->conversion['seats_label'] ?? null) : null;
        $cSeatsTone = $isModel ? ($course->conversion['seats_tone'] ?? null) : null;
        $cDeadlineBadge = $isModel && !empty($course->conversion['deadline_badge']);
        $cDeadlineDays = $isModel ? ($course->conversion['deadline_days'] ?? null) : null;
        $cEarly = $isModel ? ($course->conversion['early_bird'] ?? null) : null;
        $cImg   = $isModel && !empty($course->cover_image) ? asset('storage/'.$course->cover_image) : null;
        $cHref  = $cSlug ? route('public.courses.show',$cSlug) : route('public.courses.index');
        $cStatusStyle = $cStatus === 'open' ? 'background:#DCFCE7;color:#15803D' : 'background:#FEF9C3;color:#92400E';
        $cFeeColor = ($cFee && $cFee > 0) ? '#7C2D37' : '#15803D';
        $cFeeText  = ($cFee && $cFee > 0) ? 'MVR '.number_format($cFee,0) : __('site.free');
        $cDateText = $cDate ? $cDate->format('d M Y') : __('site.date_tbc');
      @endphp
      <a href="{{ $cHref }}"
         style="display:flex;flex-direction:column;background:#fff;border:1.5px solid #E5E7EB;border-radius:1rem;overflow:hidden;text-decoration:none;transition:box-shadow .25s,transform .25s"
         onmouseover="this.style.boxShadow='0 12px 36px rgba(0,0,0,.12)';this.style.transform='translateY(-3px)'" onmouseout="this.style.boxShadow='none';this.style.transform='translateY(0)'">
        <div style="height:10rem;background:linear-gradient(135deg,#F3EBE0,#FBEDC7);overflow:hidden">
          @if($cImg)
          <img src="{{ $cImg }}" alt="{{ $cTitle }}" style="width:100%;height:100%;object-fit:cover">
          @else
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
            <svg width="48" height="48" fill="none" stroke="#C9A227" stroke-opacity=".35" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/></svg>
          </div>
          @endif
        </div>
        <div style="padding:1rem;flex:1;display:flex;flex-direction:column">
          {{-- Status + seats badges --}}
          <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem">
            <span style="font-size:.68rem;font-weight:700;padding:.15rem .55rem;border-radius:9999px;{{ $cStatusStyle }}">
              {{ $cStatus === 'open' ? '● '.__('site.open_badge') : '◷ Upcoming' }}
            </span>
            @if($cSeats)
            <span style="font-size:.68rem;font-weight:700;padding:.15rem .55rem;border-radius:9999px;{{ $cSeatsTone === 'full' ? 'background:#F3F4F6;color:#6B7280' : ($cSeatsTone === 'exact' ? 'background:#FEE2E2;color:#B91C1C' : 'background:#FEF3C7;color:#92400E') }}">{{ $cSeats }}</span>
            @endif
            @if($cDeadlineBadge)
            <span style="font-size:.68rem;font-weight:700;padding:.15rem .55rem;border-radius:9999px;background:#F3EBE0;color:#7C2D37">{{ $cDeadlineDays === 1 ? '1 day left' : $cDeadlineDays.' days left' }}</span>
            @endif
          </div>
          <h3 style="font-weight:700;color:#111827;font-size:1rem;line-height:1.35;margin-bottom:.375rem">{{ $cTitle }}</h3>
          <p style="font-size:.825rem;color:#6B7280;line-height:1.5;flex:1;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{{ $cDesc }}</p>
          <div style="display:flex;justify-content:space-between;align-items:center;padding-top:.875rem;margin-top:.875rem;border-top:1px solid #F3F4F6">
            <span style="font-size:.78rem;color:#9CA3AF">{{ $cDateText }}</span>
            <span style="font-weight:700;font-size:.9rem;color:{{ $cEarly || ($cFee && $cFee > 0) ? '#7C2D37' : '#15803D' }}">
              @if($cEarly)
                <span style="text-decoration:line-through;color:#9CA3AF;font-weight:500;margin-inline-end:.35rem">MVR {{ number_format($cEarly['normal_amount'], 0) }}</span>
                MVR {{ number_format($cEarly['amount'], 0) }}
              @else
                {{ $cFeeText }}
              @endif
            </span>
          </div>
        </div>
      </a>
      @empty
      <div style="grid-column:1/-1;text-align:center;padding:3rem;color:#9CA3AF">
        {{ __('site.no_courses') }} <a href="{{ route('public.admissions.create') }}" style="color:#7C2D37">{{ __('site.leave_details') }}</a>
      </div>
      @endforelse
    </div>
  </div>
</section>

{{-- The Digital Library's and the Bookstore's newest, and the School (the 2026-09-28 website
     design, STATUS §5ki). A shelf with nothing on it is left out rather than shown empty; its
     tile under the hero still leads there. --}}
@php
  $coverTones = [['#7C2D37', '#fff'], ['#2F4A3A', '#fff'], ['#C9A227', '#2B1F04'], ['#33415F', '#fff'], ['#5A1F28', '#fff'], ['#8A6A2F', '#fff']];
  $money = fn ($amount, $currency = 'MVR') => ($currency ?: 'MVR').' '.rtrim(rtrim(number_format((float) $amount, 2, '.', ','), '0'), '.');
@endphp

@if (! empty($shelves['books']))
<section style="background:#FBF6EC;padding:3.5rem 0" data-testid="home-library">
  <div class="container mx-auto px-4">
    <div class="home-head">
      <div>
        <span class="home-eyebrow">{{ __('site.digital_library') }}</span>
        <h2 class="home-h2">{{ __('site.new_in_library') }}</h2>
      </div>
      <a href="{{ route('public.library.index') }}" class="home-more">{{ __('site.open_library') }} <span class="rtl-flip" aria-hidden="true">→</span></a>
    </div>
    <div class="home-row home-row--books">
      @foreach ($shelves['books'] as $i => $book)
        @php [$tone, $ink] = $coverTones[$i % count($coverTones)]; @endphp
        <a href="{{ $book['href'] }}" class="flex flex-col gap-2 no-underline" style="color:#1F1A17" data-testid="home-book">
          <span class="block overflow-hidden rounded-lg" style="aspect-ratio:2/3;background:{{ $tone }};box-shadow:0 6px 16px rgba(0,0,0,.12)">
            @if ($book['cover_url'])
              <img src="{{ $book['cover_url'] }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">
            @else
              <span class="flex h-full items-end p-3 text-sm font-bold" style="color:{{ $ink }}">{{ $book['title'] }}</span>
            @endif
          </span>
          @if (! empty($book['type']))
            <span class="text-xs font-semibold uppercase" style="letter-spacing:.06em;color:#7C2D37" data-testid="home-book-type">{{ __('site.type_'.$book['type']) }}</span>
          @endif
          <strong class="text-sm leading-snug" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $book['title'] }}</strong>
          <span class="text-xs" style="color:#5E5650">
            @if ($book['by']){{ $book['by'] }} · @endif
            @if ($book['free'])
              <strong style="color:#15803D">{{ __('site.free') }}</strong>
            @elseif ($book['price'] !== null)
              {{ $money($book['price'], $book['currency']) }}
            @endif
          </span>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

@if (! empty($shelves['products']))
<section style="background:#FFFFFF;padding:3.5rem 0" data-testid="home-bookstore">
  <div class="container mx-auto px-4">
    <div class="home-head">
      <div>
        <span class="home-eyebrow">{{ __('site.akuru_bookstore') }}</span>
        <h2 class="home-h2">{{ __('site.from_bookstore') }}</h2>
      </div>
      <a href="{{ route('public.shop.index') }}" class="home-more">{{ __('site.visit_store') }} <span class="rtl-flip" aria-hidden="true">→</span></a>
    </div>
    <div class="home-row">
      @foreach ($shelves['products'] as $product)
        <a href="{{ $product['href'] }}" class="flex flex-col overflow-hidden rounded-2xl border no-underline" style="border-color:#EDE4D8;color:#1F1A17" data-testid="home-product">
          <span class="flex items-center justify-center" style="aspect-ratio:1;background:#F5F1EA">
            @if ($product['image'])
              <img src="{{ $product['image'] }}" alt="{{ $product['image_alt'] }}" loading="lazy" style="width:100%;height:100%;object-fit:cover">
            @else
              <svg class="h-10 w-10" fill="none" stroke="#C9B79A" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14l-1 12H6L5 8zm4 0V6a3 3 0 016 0v2"/></svg>
            @endif
          </span>
          <span class="flex flex-col gap-1 p-4">
            <span class="text-xs font-semibold" style="color:#6B625B">{{ $product['shop'] }}</span>
            <strong class="leading-snug" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $product['title'] }}</strong>
            <span class="font-extrabold" style="color:#7C2D37">
              @if ($product['compare_at_price'])<span class="font-medium line-through" style="color:#9CA3AF">{{ $money($product['compare_at_price'], $product['currency']) }}</span> @endif
              {{ $money($product['price'], $product['currency']) }}
            </span>
          </span>
        </a>
      @endforeach
    </div>
    <p class="mt-5 text-sm" style="color:#5E5650">{{ __('site.sell_prompt') }} <a href="{{ route('vendor.apply') }}" class="font-bold" style="color:#7C2D37">{{ __('site.sell_on_akuru') }} <span class="rtl-flip" aria-hidden="true">→</span></a></p>
  </div>
</section>
@endif

<section style="background:#F3EBE0;padding:3.5rem 0" data-testid="home-school">
  <div class="container mx-auto px-4 grid gap-8 lg:grid-cols-2 lg:items-center">
    <div class="flex flex-col gap-4">
      <span class="home-eyebrow">{{ __('site.akuru_school') }}</span>
      <h2 class="home-h2" style="margin:0">{{ __('site.school_heading') }}</h2>
      <p class="text-base leading-relaxed" style="color:#4F4741;margin:0">{{ __('site.school_body') }}</p>
      <div class="flex flex-wrap gap-3">
        <a href="{{ route('public.admissions.create') }}" class="inline-flex items-center rounded-lg px-5 py-3 font-bold text-white no-underline" style="background:#7C2D37" data-testid="home-school-apply">{{ __('site.apply_admission') }}</a>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="inline-flex items-center rounded-lg border px-5 py-3 font-semibold no-underline" style="border-color:#C7B49A;background:#fff;color:#1F1A17">{{ __('site.parent_login') }}</a>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3 rounded-2xl bg-white p-4 sm:p-6" style="box-shadow:0 10px 30px rgba(60,20,27,.08)">
      @foreach ([
        ['school_attendance', 'M4 6h16v14H4V6zm0 4h16M8 3v4m8-4v4M9 15l2 2 4-4'],
        ['school_results', 'M5 20V10m7 10V4m7 16v-7'],
        ['school_homework', 'M4 20h4L19 9l-4-4L4 16v4z'],
        ['school_fees', 'M3 7h18v12H3V7zm0 4h18M7 15h4'],
      ] as [$key, $icon])
        <div class="flex flex-col gap-2 rounded-xl p-3 sm:flex-row sm:gap-3 sm:p-4" style="background:#FBF8F3">
          <svg class="h-6 w-6 shrink-0" fill="none" stroke="#7C2D37" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/></svg>
          <span class="flex flex-col gap-1">
            <strong class="text-sm sm:text-base">{{ __('site.'.$key) }}</strong>
            <span class="text-xs sm:text-sm" style="color:#5E5650">{{ __('site.'.$key.'_line') }}</span>
          </span>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
  SECTION 4 — STATS   bg: brand maroon #7C2D37
  One row, what each product holds, counted — never a made-up number, and a
  count of nothing is left out (STATUS §5kk). Students taught is the hero's.
═══════════════════════════════════════════════════════════ --}}
@php
  $statRow = array_filter([
      [(int) ($stats['courses'] ?? 0), __('site.stat_courses'), route('public.courses.index'), 'courses'],
      [(int) ($shelves['counts']['books'] ?? 0), __('site.stat_books'), route('public.library.index'), 'books'],
      [(int) ($shelves['counts']['items'] ?? 0), __('site.stat_items'), route('public.shop.index'), 'items'],
  ], fn ($stat) => $stat[0] > 0);
@endphp
@if ($statRow !== [])
<section style="background:#7C2D37;padding:2.5rem 0" data-testid="home-stats" aria-label="{{ __('site.akuru_in_numbers') }}">
  <div class="container mx-auto px-4">
    <div class="home-stats">
      @foreach ($statRow as [$count, $label, $href, $key])
      <a href="{{ $href }}" class="home-stat" data-testid="home-stat-{{ $key }}">
        <span class="home-stat-n">{{ number_format($count) }}</span>
        <span class="home-stat-l">{{ $label }}</span>
      </a>
      @endforeach
    </div>
  </div>
</section>
<style>
  .home-stats { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem 4rem; text-align: center; }
  .home-stat { display: flex; flex-direction: column; gap: .35rem; text-decoration: none; min-width: 7rem; }
  .home-stat-n { font-size: clamp(2rem, 5vw, 2.75rem); font-weight: 800; color: #E8C766; line-height: 1; font-variant-numeric: tabular-nums; }
  .home-stat-l { color: rgba(255,255,255,.8); font-size: .875rem; }
  .home-stat:hover .home-stat-l { color: #fff; text-decoration: underline; }
  @media (max-width: 639px) { .home-stats { gap: 1.25rem 2rem; } }
</style>
@endif

{{-- ═══════════════════════════════════════════════════════════
  SECTION 5 — GALLERY   bg: white
═══════════════════════════════════════════════════════════ --}}
@if(isset($galleryPhotos) && $galleryPhotos->count() > 0)
@php $lbData = $galleryPhotos->map(fn($p)=>['src'=>\Storage::url($p->file_path),'title'=>$p->title??''])->values(); @endphp
<script>
const _lb=@json($lbData);let _lbI=0,_lbX=null;
function oLb(i){_lbI=i;_rLb();document.getElementById('glb').style.display='flex';document.body.style.overflow='hidden';}
function cLb(){document.getElementById('glb').style.display='none';document.body.style.overflow='';}
function nLb(d){_lbI=(_lbI+d+_lb.length)%_lb.length;_rLb();}
function _rLb(){const p=_lb[_lbI],e=document.getElementById('glb-img');e.style.opacity=0;e.src=p.src;e.onload=()=>e.style.opacity=1;document.getElementById('glb-ttl').textContent=p.title;document.getElementById('glb-cnt').textContent=(_lbI+1)+'/'+_lb.length;}
document.addEventListener('keydown',e=>{if(!document.getElementById('glb')||document.getElementById('glb').style.display==='none')return;if(e.key==='Escape')cLb();if(e.key==='ArrowRight')nLb(1);if(e.key==='ArrowLeft')nLb(-1);});
</script>
<section style="background:#FFFFFF;padding:4rem 0">
  <div class="container mx-auto px-4">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
      <div>
        <span style="color:#7C2D37;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em">Life at Akuru</span>
        <h2 style="font-size:clamp(1.75rem,3vw,2.25rem);font-weight:800;color:#111827;margin:.25rem 0 0">Our Gallery</h2>
      </div>
      <a href="{{ route('public.gallery.index') }}" style="color:#7C2D37;font-weight:600;font-size:.875rem;text-decoration:none">View all <span class="rtl-flip" aria-hidden="true">→</span></a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:.5rem">
      @foreach($galleryPhotos as $idx => $photo)
      <button onclick="oLb({{ $idx }})" style="position:relative;aspect-ratio:1;overflow:hidden;border-radius:.5rem;border:none;padding:0;cursor:pointer;background:#F3F4F6"
              onmouseover="this.querySelector('img').style.transform='scale(1.1)';this.querySelector('.ov').style.opacity='1'" onmouseout="this.querySelector('img').style.transform='scale(1)';this.querySelector('.ov').style.opacity='0'">
        <img src="{{ \Storage::url($photo->thumbnail_path ?? $photo->file_path) }}" alt="{{ $photo->alt_text ?? '' }}" style="width:100%;height:100%;object-fit:cover;transition:transform .4s" loading="lazy">
        <div class="ov" style="position:absolute;inset:0;background:rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .3s">
          <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
        </div>
      </button>
      @endforeach
    </div>
  </div>
</section>
{{-- Lightbox --}}
<div id="glb" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.94);align-items:center;justify-content:center;padding:1rem" onclick="if(event.target===this)cLb()">
  <button onclick="cLb()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.12);border:none;border-radius:50%;width:2.5rem;height:2.5rem;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
  <span id="glb-cnt" style="position:absolute;top:1rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.45);font-size:.8rem"></span>
  <button onclick="nLb(-1)" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.12);border:none;border-radius:50%;width:2.75rem;height:2.75rem;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
  <div style="display:flex;flex-direction:column;align-items:center;max-width:900px;width:100%">
    <img id="glb-img" src="" alt="" style="max-height:74vh;max-width:100%;object-fit:contain;border-radius:.5rem;transition:opacity .25s" ontouchstart="_lbX=event.changedTouches?event.changedTouches[0].clientX:null" ontouchend="if(event.changedTouches){const d=event.changedTouches[0].clientX-_lbX;if(d>50)nLb(-1);else if(d<-50)nLb(1);}">
    <p id="glb-ttl" style="color:#fff;font-weight:600;margin-top:.75rem;text-align:center"></p>
  </div>
  <button onclick="nLb(1)" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.12);border:none;border-radius:50%;width:2.75rem;height:2.75rem;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════
  SECTION 6 — TESTIMONIALS   bg: warm gold #FDF3D8
═══════════════════════════════════════════════════════════ --}}
@if(isset($testimonials) && $testimonials->isNotEmpty())
<section style="background:#FDF3D8;padding:4rem 0;border-top:1px solid #F0D987">
  <div class="container mx-auto px-4">
    <div style="text-align:center;margin-bottom:2.5rem">
      <span style="color:#92400E;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em">Student voices</span>
      <h2 style="font-size:clamp(1.75rem,3vw,2.5rem);font-weight:800;color:#111827;margin:.25rem 0 0">What Our Students Say</h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:1.25rem;max-width:70rem;margin:0 auto">
      @foreach($testimonials as $t)
      <div style="background:#FFFFFF;border:1px solid rgba(201,162,39,.25);border-radius:.875rem;padding:1.5rem;position:relative">
        <div style="font-size:3.5rem;line-height:1;font-family:Georgia,serif;position:absolute;top:.5rem;right:1rem;color:#C9A227;opacity:.25">"</div>
        <p style="color:#374151;line-height:1.65;margin-bottom:1.25rem;font-size:.9rem;position:relative">"{{ $t->quote }}"</p>
        <div style="display:flex;align-items:center;gap:.75rem">
          <div style="width:2.5rem;height:2.5rem;border-radius:50%;background:#FAECED;display:flex;align-items:center;justify-content:center;color:#7C2D37;font-weight:700;font-size:.9rem;flex-shrink:0">{{ strtoupper(substr($t->name??'A',0,1)) }}</div>
          <div>
            <p style="font-weight:600;color:#111827;font-size:.875rem;margin:0">{{ $t->name }}</p>
            @if(!empty($t->role))<p style="font-size:.75rem;color:#9CA3AF;margin:0">{{ $t->role }}</p>@endif
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════════════
  SECTION 7 — NEWS & EVENTS   bg: white
═══════════════════════════════════════════════════════════ --}}
<section style="background:#FFFFFF;padding:4rem 0;border-top:1px solid #F3F4F6">
  <div class="container mx-auto px-4">
    <div style="display:grid;grid-template-columns:1fr;gap:3rem">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,400px),1fr));gap:3rem">
        {{-- News --}}
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
            <h2 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0">Latest News</h2>
            <a href="{{ route('public.news.index') }}" style="font-size:.825rem;color:#7C2D37;font-weight:600;text-decoration:none">All news <span class="rtl-flip" aria-hidden="true">→</span></a>
          </div>
          <div style="display:flex;flex-direction:column;gap:.75rem">
            @forelse($posts as $post)
            @php $ps = $post->slug ?? $post->id ?? null; @endphp
            <a href="{{ $ps ? route('public.news.show',$ps) : route('public.news.index') }}"
               style="display:flex;gap:1rem;padding:.875rem;border-radius:.75rem;text-decoration:none;background:#F9FAFB;transition:background .2s"
               onmouseover="this.style.background='#FDF7F8'" onmouseout="this.style.background='#F9FAFB'">
              <div style="width:3rem;height:3rem;background:#FAECED;border-radius:.5rem;flex-shrink:0;display:flex;align-items:center;justify-content:center">
                <svg width="20" height="20" fill="none" stroke="#7C2D37" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7"/></svg>
              </div>
              <div style="min-width:0">
                <p style="font-weight:600;color:#111827;font-size:.875rem;line-height:1.4;margin:0 0 .25rem;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{{ $post->title }}</p>
                <p style="font-size:.75rem;color:#9CA3AF;margin:0">{{ \Carbon\Carbon::parse($post->published_at ?? now())->format('d M Y') }}</p>
              </div>
            </a>
            @empty
            <p style="color:#9CA3AF;font-size:.875rem;padding:1rem 0">No news yet.</p>
            @endforelse
          </div>
        </div>
        {{-- Events --}}
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
            <h2 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0">Upcoming Events</h2>
            <a href="{{ route('public.events.index') }}" style="font-size:.825rem;color:#A8861F;font-weight:600;text-decoration:none">All events <span class="rtl-flip" aria-hidden="true">→</span></a>
          </div>
          <div style="display:flex;flex-direction:column;gap:.75rem">
            @forelse($events as $event)
            @php $es = $event->slug ?? $event->id ?? null; $ed = \Carbon\Carbon::parse($event->start_date ?? now()); @endphp
            <a href="{{ $es ? route('public.events.show',$es) : route('public.events.index') }}"
               style="display:flex;gap:1rem;padding:.875rem;border-radius:.75rem;text-decoration:none;border:1px solid #F3F4F6;transition:border-color .2s,background .2s"
               onmouseover="this.style.borderColor='#FDE68A';this.style.background='#FFFBEB'" onmouseout="this.style.borderColor='#F3F4F6';this.style.background='transparent'">
              <div style="flex-shrink:0;text-align:center;width:3rem">
                <div style="background:#7C2D37;color:white;border-radius:.375rem .375rem 0 0;padding:.125rem .25rem;font-size:.65rem;font-weight:700;text-transform:uppercase">{{ $ed->format('M') }}</div>
                <div style="border:1px solid #E5E7EB;border-top:none;border-radius:0 0 .375rem .375rem;padding:.25rem;font-size:1.25rem;font-weight:800;color:#111827;line-height:1.2">{{ $ed->format('d') }}</div>
              </div>
              <div>
                <p style="font-weight:600;color:#111827;font-size:.875rem;margin:0 0 .25rem">{{ $event->title }}</p>
                <p style="font-size:.75rem;color:#9CA3AF;margin:0">{{ $event->location ?? 'Akuru Institute' }}</p>
              </div>
            </a>
            @empty
            <div style="text-align:center;padding:2rem;color:#9CA3AF">
              <svg width="36" height="36" style="margin:0 auto .5rem;display:block;opacity:.3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <p style="font-size:.875rem;margin:0">No upcoming events scheduled.</p>
            </div>
            @endforelse
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

@endsection
