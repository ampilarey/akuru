@extends('public.layouts.public')
@section('title', $text['title'] ?? 'Welcome to Akuru Institute')
@section('description', $text['desc'] ?? 'Learn Quran, Arabic, and Islamic Studies in the Maldives')

@section('content')

{{-- ═══════════════════════════════════════════════════════════
  SECTION 1 — HERO   bg: deep maroon gradient
═══════════════════════════════════════════════════════════ --}}
@php $banner = $heroBanners->first(); @endphp
<section style="background:linear-gradient(135deg,#3D1219 0%,#7C2D37 55%,#5A1F28 100%);position:relative;overflow:hidden">
  {{-- subtle pattern --}}
  <div style="position:absolute;inset:0;opacity:.07;background-image:url(\"data:image/svg+xml,%3Csvg width='52' height='26' viewBox='0 0 52 26' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23C9A227' fill-opacity='1'%3E%3Cpath d='M10 10c0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6h2c0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4v2c-3.314 0-6-2.686-6-6 0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6zm25.464-1.95l8.486 8.486-1.414 1.414-8.486-8.486 1.414-1.414z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")"></div>
  <div class="container mx-auto px-4 py-20 sm:py-28 relative text-center text-white">
    <span style="display:inline-block;background:rgba(201,162,39,0.2);border:1px solid rgba(201,162,39,0.4);color:#E8BC3C;font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.375rem 1rem;border-radius:9999px;margin-bottom:1.25rem">
      🕌 Islamic Education in the Maldives
    </span>
    <h1 style="font-size:clamp(2rem,5vw,3.5rem);font-weight:800;line-height:1.15;margin-bottom:1.25rem;text-shadow:0 2px 16px rgba(0,0,0,.4)">
      {{ $banner->title ?? $text['title'] ?? 'Welcome to Akuru Institute' }}
    </h1>
    <p style="font-size:clamp(1rem,2vw,1.25rem);color:rgba(255,255,255,.8);max-width:40rem;margin:0 auto 2.5rem;line-height:1.7">
      {{ $banner->subtitle ?? $text['desc'] ?? 'Learn Quran, Arabic, and Islamic Studies in the Maldives' }}
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:.875rem;justify-content:center">
      <a href="{{ route('public.courses.index') }}"
         style="display:inline-flex;align-items:center;gap:.5rem;background:#C9A227;color:#3D1219;font-weight:700;padding:.875rem 2rem;border-radius:.75rem;font-size:1.05rem;text-decoration:none;transition:opacity .2s,transform .2s"
         onmouseover="this.style.opacity='.88';this.style.transform='scale(1.04)'" onmouseout="this.style.opacity='1';this.style.transform='scale(1)'">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13"/></svg>
        Enroll Now
      </a>
      <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to know about Akuru Institute.') }}"
         style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.12);color:white;border:2px solid rgba(255,255,255,.35);font-weight:600;padding:.875rem 2rem;border-radius:.75rem;font-size:1.05rem;text-decoration:none;transition:background .2s"
         onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
        Chat on Viber
      </a>
    </div>
  </div>
  {{-- White wave into next section --}}
  <div style="position:absolute;bottom:0;left:0;right:0;line-height:0">
    <svg viewBox="0 0 1440 56" preserveAspectRatio="none" style="width:100%;height:56px;display:block"><path d="M0 56h1440V28C1080 56 720 0 360 28 180 42 0 28 0 28v28z" fill="white"/></svg>
  </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
  SECTION 2 — OPEN COURSES   bg: white
═══════════════════════════════════════════════════════════ --}}
<section style="background:#FFFFFF;padding:3rem 0 4rem">
  <div class="container mx-auto px-4">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:1rem;margin-bottom:2.5rem">
      <div>
        <span style="color:#7C2D37;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em">Enroll today</span>
        <h2 style="font-size:clamp(1.75rem,3vw,2.5rem);font-weight:800;color:#111827;margin:.25rem 0 .5rem">Open Courses</h2>
        <p style="color:#6b7280;font-size:.9rem">Secure your seat — limited places available</p>
      </div>
      <a href="{{ route('public.courses.index') }}" style="color:#7C2D37;font-weight:600;font-size:.875rem;text-decoration:none;display:flex;align-items:center;gap:.25rem">
        View all <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,280px),1fr));gap:1.5rem">
      @forelse($courses as $course)
      @php
        $isModel = is_object($course) && method_exists($course,'getAttribute');
        $slug = $isModel ? ($course->slug ?? '') : '';
        $title = $isModel ? ($course->title ?? '') : '';
        $desc = $isModel ? ($course->short_desc ?? '') : '';
        $fee = $isModel ? ($course->fee ?? null) : null;
        $status = $isModel ? ($course->status ?? 'open') : 'open';
        $startDate = $isModel && !empty($course->start_date) ? \Carbon\Carbon::parse($course->start_date) : null;
        $seats = $isModel ? ($course->available_seats ?? null) : null;
        $img = $isModel && !empty($course->cover_image) ? asset('storage/'.$course->cover_image) : null;
      @endphp
      <a href="{{ $slug ? route('public.courses.show',$slug) : route('public.courses.index') }}"
         style="display:flex;flex-direction:column;background:#fff;border:1.5px solid #E5E7EB;border-radius:1rem;overflow:hidden;text-decoration:none;transition:box-shadow .25s,transform .25s"
         onmouseover="this.style.boxShadow='0 12px 36px rgba(0,0,0,.12)';this.style.transform='translateY(-3px)'" onmouseout="this.style.boxShadow='none';this.style.transform='translateY(0)'">
        <div style="position:relative;height:11rem;background:linear-gradient(135deg,#F3EBE0,#FBEDC7);overflow:hidden">
          @if($img)
          <img src="{{ $img }}" alt="{{ $title }}" style="width:100%;height:100%;object-fit:cover">
          @else
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
            <svg width="52" height="52" fill="none" stroke="#C9A227" stroke-opacity=".4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/></svg>
          </div>
          @endif
          <span style="position:absolute;top:.625rem;left:.625rem;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px;{{ $status==='open' ? 'background:#DCFCE7;color:#15803D' : 'background:#FEF9C3;color:#92400E' }}">
            {{ $status==='open' ? '● Open' : '◷ Upcoming' }}
          </span>
          @if($seats !== null && $seats <= 5 && $seats > 0)
          <span style="position:absolute;top:.625rem;right:.625rem;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px;background:#FEE2E2;color:#B91C1C">{{ $seats }} left!</span>
          @elseif($seats === 0)
          <span style="position:absolute;top:.625rem;right:.625rem;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px;background:#F3F4F6;color:#6B7280">Full</span>
          @endif
        </div>
        <div style="padding:1.125rem;flex:1;display:flex;flex-direction:column">
          <h3 style="font-weight:700;color:#111827;font-size:1.05rem;line-height:1.3;margin-bottom:.375rem">{{ $title }}</h3>
          <p style="font-size:.825rem;color:#6B7280;line-height:1.5;flex:1;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{{ $desc }}</p>
          <div style="display:flex;justify-content:space-between;align-items:center;padding-top:.875rem;margin-top:.875rem;border-top:1px solid #F3F4F6">
            <span style="font-size:.78rem;color:#9CA3AF">
              @if($startDate)📅 {{ $startDate->format('d M Y') }}@else Date TBC@endif
            </span>
            <span style="font-weight:700;font-size:.9rem;color:{{ $fee && $fee > 0 ? '#7C2D37' : '#15803D' }}">
              {{ $fee && $fee > 0 ? 'MVR '.number_format($fee,0) : 'Free' }}
            </span>
          </div>
        </div>
      </a>
      @empty
      <div style="grid-column:1/-1;text-align:center;padding:3rem;color:#9CA3AF">
        No open courses right now. <a href="{{ route('public.admissions.create') }}" style="color:#7C2D37">Leave your details</a> and we'll notify you.
      </div>
      @endforelse
    </div>
    <div style="text-align:center;margin-top:2.5rem">
      <a href="{{ route('public.courses.index') }}"
         style="display:inline-flex;align-items:center;gap:.5rem;background:#7C2D37;color:white;font-weight:700;padding:.875rem 2rem;border-radius:.75rem;text-decoration:none;transition:background .2s"
         onmouseover="this.style.background='#6B2630'" onmouseout="this.style.background='#7C2D37'">
        View All Courses
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4-4 4m4-4H3"/></svg>
      </a>
    </div>
  </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
  SECTION 3 — WHY AKURU   bg: warm cream #F0E6D3
═══════════════════════════════════════════════════════════ --}}
<section style="background:#F0E6D3;padding:4rem 0">
  <div class="container mx-auto px-4">
    <div style="text-align:center;margin-bottom:2.75rem">
      <span style="color:#7C2D37;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em">Why choose us</span>
      <h2 style="font-size:clamp(1.75rem,3vw,2.5rem);font-weight:800;color:#111827;margin:.25rem 0 .5rem">Why Akuru Institute?</h2>
      <p style="color:#6B7280;max-width:36rem;margin:0 auto;font-size:.9rem">Trusted by hundreds of families across the Maldives for quality Islamic education.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,290px),1fr));gap:1.25rem;max-width:70rem;margin:0 auto">
      @foreach([
        ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z','Qualified Instructors','All our teachers hold recognised Islamic qualifications with years of teaching experience.'],
        ['M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5','Structured Curriculum','Well-planned programmes for Quran, Arabic, and Islamic Studies — beginner to advanced.'],
        ['M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z','Flexible Schedules','Morning, evening and weekend classes to fit around school, work, and family life.'],
        ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z','All Ages Welcome','Classes for children, teenagers, and adults — everyone learns at the right pace.'],
        ['M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z','Affordable Fees','Quality Islamic education accessible to all with fair, transparent fees.'],
        ['M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4','Recognised Certificates','Earn certificates upon completion that recognise your achievement.'],
      ] as [$icon,$title,$desc])
      <div style="background:#FFFFFF;border-radius:.875rem;padding:1.5rem;border:1px solid rgba(124,45,55,.1);box-shadow:0 1px 4px rgba(0,0,0,.06)">
        <div style="width:2.75rem;height:2.75rem;background:#FAECED;border-radius:.625rem;display:flex;align-items:center;justify-content:center;margin-bottom:1rem">
          <svg width="20" height="20" fill="none" stroke="#7C2D37" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
        </div>
        <h3 style="font-weight:700;color:#111827;margin-bottom:.375rem;font-size:.95rem">{{ $title }}</h3>
        <p style="font-size:.82rem;color:#6B7280;line-height:1.55">{{ $desc }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
  SECTION 4 — STATS   bg: brand maroon #7C2D37
═══════════════════════════════════════════════════════════ --}}
@if(isset($stats))
<section style="background:#7C2D37;padding:3rem 0">
  <div class="container mx-auto px-4">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:2rem;text-align:center">
      <div><div style="font-size:2.75rem;font-weight:800;color:#C9A227;line-height:1">{{ number_format($stats['students']) }}+</div><div style="color:rgba(255,255,255,.65);font-size:.82rem;margin-top:.375rem">Students enrolled</div></div>
      <div><div style="font-size:2.75rem;font-weight:800;color:#C9A227;line-height:1">{{ $stats['courses'] }}</div><div style="color:rgba(255,255,255,.65);font-size:.82rem;margin-top:.375rem">Courses offered</div></div>
      <div><div style="font-size:2.75rem;font-weight:800;color:#C9A227;line-height:1">5+</div><div style="color:rgba(255,255,255,.65);font-size:.82rem;margin-top:.375rem">Years of service</div></div>
      <div><div style="font-size:2.75rem;font-weight:800;color:#C9A227;line-height:1">100%</div><div style="color:rgba(255,255,255,.65);font-size:.82rem;margin-top:.375rem">Qualified teachers</div></div>
    </div>
  </div>
</section>
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
      <a href="{{ route('public.gallery.index') }}" style="color:#7C2D37;font-weight:600;font-size:.875rem;text-decoration:none">View all →</a>
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
            <a href="{{ route('public.news.index') }}" style="font-size:.825rem;color:#7C2D37;font-weight:600;text-decoration:none">All news →</a>
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
            <a href="{{ route('public.events.index') }}" style="font-size:.825rem;color:#A8861F;font-weight:600;text-decoration:none">All events →</a>
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

{{-- ═══════════════════════════════════════════════════════════
  SECTION 8 — CTA   bg: deep maroon gradient
═══════════════════════════════════════════════════════════ --}}
<section style="background:linear-gradient(135deg,#5A1F28 0%,#7C2D37 50%,#491821 100%);padding:4.5rem 0;text-align:center">
  <div class="container mx-auto px-4">
    <h2 style="font-size:clamp(1.75rem,4vw,2.75rem);font-weight:800;color:white;margin:0 0 1rem">Ready to Start Your Journey?</h2>
    <p style="color:rgba(255,255,255,.72);font-size:1.05rem;max-width:36rem;margin:0 auto 2.5rem;line-height:1.65">Join hundreds of students who chose Akuru Institute for their Islamic education.</p>
    <div style="display:flex;flex-wrap:wrap;gap:1rem;justify-content:center">
      <a href="{{ route('public.courses.index') }}"
         style="display:inline-flex;align-items:center;gap:.5rem;background:#C9A227;color:#3D1219;font-weight:700;padding:.875rem 2.25rem;border-radius:.75rem;font-size:1.05rem;text-decoration:none;transition:opacity .2s"
         onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
        {{ __('public.Enroll') }}
      </a>
      <a href="{{ route('public.contact.create') }}"
         style="display:inline-flex;align-items:center;gap:.5rem;border:2px solid rgba(255,255,255,.35);color:white;font-weight:600;padding:.875rem 2.25rem;border-radius:.75rem;font-size:1.05rem;text-decoration:none;transition:background .2s"
         onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">
        {{ __('public.Contact Us') }}
      </a>
    </div>
  </div>
</section>

@endsection
