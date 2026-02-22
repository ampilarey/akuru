@extends('public.layouts.public')

@section('title', $text['title'] ?? __('public.Welcome to Akuru Institute'))
@section('description', $text['desc'] ?? __('public.Learn Quran, Arabic, and Islamic Studies'))

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     1. HERO  — simple static, always reliable
══════════════════════════════════════════════════════════════ --}}
@php
    $firstBanner = $heroBanners->first();
    $heroTitle    = $firstBanner ? ($firstBanner->title ?? $firstBanner->headline ?? $text['title']) : $text['title'];
    $heroSubtitle = $firstBanner ? ($firstBanner->subtitle ?? $firstBanner->subheadline ?? $text['desc']) : $text['desc'];
    $heroImg      = $firstBanner && !empty($firstBanner->image_path)
                    ? asset('storage/'.$firstBanner->image_path) : null;
@endphp
<section class="relative text-white overflow-hidden" style="min-height:520px;background:linear-gradient(135deg,#491821 0%,#7C2D37 50%,#5A1F28 100%)">
    @if($heroImg)
    <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ $heroImg }}')"></div>
    <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(73,24,33,0.90),rgba(90,31,40,0.75))"></div>
    @else
    {{-- Subtle pattern overlay --}}
    <div class="absolute inset-0 opacity-[0.04]" style="background-image:url(\"data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v5h5v5H0v5h20v-2.5h-10v-5h10v-5h-10zm-10 5h5v5h-5v-5zm15-5h5v5h-5v-5z' fill='%23C9A227' fill-opacity='1' fill-rule='evenodd'/%3E%3C/svg%3E\")"></div>
    @endif

    <div class="relative z-10 container mx-auto px-4 py-16 sm:py-24 flex flex-col items-center text-center" style="min-height:520px;justify-content:center">
        <div class="inline-flex items-center gap-2 border border-brandGold-500/50 text-brandGold-300 text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-full mb-6 bg-brandGold-500/10">
            🕌 Akuru Institute
        </div>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight mb-4 max-w-3xl" style="text-shadow:0 2px 16px rgba(0,0,0,0.4)">{{ $heroTitle }}</h1>
        <p class="text-lg sm:text-xl text-white/80 mb-10 max-w-2xl leading-relaxed">{{ $heroSubtitle }}</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('public.courses.index') }}"
               class="inline-flex items-center justify-center gap-2 font-bold px-8 py-4 rounded-xl text-base shadow-xl transition-all hover:scale-105"
               style="background:#C9A227;color:#491821">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13"/></svg>
                {{ __('public.Enroll') }}
            </a>
            <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to know about Akuru Institute.') }}"
               class="inline-flex items-center justify-center gap-2 bg-purple-700 hover:bg-purple-600 text-white font-semibold px-8 py-4 rounded-xl text-base transition-all hover:scale-105">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
                Chat on Viber
            </a>
        </div>
    </div>

    {{-- Gold divider at bottom --}}
    <div class="absolute bottom-0 left-0 right-0" style="height:4px;background:linear-gradient(90deg,transparent,#C9A227,transparent)"></div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     2. STATS  — white, clean
══════════════════════════════════════════════════════════════ --}}
@if(isset($stats))
<section class="py-10 bg-white border-b border-gray-100">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center max-w-3xl mx-auto">
            <div>
                <div class="text-3xl sm:text-4xl font-bold text-brandMaroon-600">{{ number_format($stats['students']) }}+</div>
                <div class="text-gray-500 text-sm mt-1">Students</div>
            </div>
            <div>
                <div class="text-3xl sm:text-4xl font-bold text-brandMaroon-600">{{ $stats['courses'] }}</div>
                <div class="text-gray-500 text-sm mt-1">Courses</div>
            </div>
            <div>
                <div class="text-3xl sm:text-4xl font-bold text-brandGold-600">5+</div>
                <div class="text-gray-500 text-sm mt-1">Years</div>
            </div>
            <div>
                <div class="text-3xl sm:text-4xl font-bold text-brandGold-600">{{ $stats['teachers'] ?? 10 }}+</div>
                <div class="text-gray-500 text-sm mt-1">Teachers</div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     3. OPEN COURSES  — warm cream
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20" style="background:#EDE0CF">
    <div class="container mx-auto px-4">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
            <div>
                <span class="inline-block bg-green-700/10 text-green-800 font-bold text-xs uppercase tracking-widest px-3 py-1 rounded-full mb-2 border border-green-700/20">Now Enrolling</span>
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Open Courses</h2>
                <p class="text-gray-600 mt-1 text-sm">Secure your seat — places are limited.</p>
            </div>
            <a href="{{ route('public.courses.index') }}" class="shrink-0 text-brandMaroon-700 hover:text-brandMaroon-900 font-semibold text-sm flex items-center gap-1">
                View all <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($courses as $course)
            @php
                $isModel   = is_object($course) && method_exists($course,'getAttribute');
                $slug      = $isModel ? ($course->slug ?? '') : '';
                $title     = $isModel ? ($course->title ?? '') : '';
                $shortDesc = $isModel ? ($course->short_desc ?? '') : '';
                $fee       = $isModel ? ($course->fee ?? null) : null;
                $status    = $isModel ? ($course->status ?? 'open') : 'open';
                $startDate = $isModel && !empty($course->start_date) ? \Carbon\Carbon::parse($course->start_date) : null;
                $seats     = $isModel ? ($course->available_seats ?? null) : null;
                $image     = $isModel && !empty($course->cover_image) ? asset('storage/'.$course->cover_image) : null;
            @endphp
            <a href="{{ $slug ? route('public.courses.show', $slug) : route('public.courses.index') }}"
               class="group bg-white rounded-2xl overflow-hidden shadow hover:shadow-lg border border-white/80 hover:-translate-y-1 transition-all duration-300 flex flex-col">
                <div class="relative h-44 overflow-hidden" style="background:linear-gradient(135deg,#EDE0CF,#F3EBE0)">
                    @if($image)
                    <img src="{{ $image }}" alt="{{ $title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @else
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 opacity-30 text-brandGold-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5"/></svg>
                    </div>
                    @endif
                    <span class="absolute top-3 left-3 text-xs font-bold px-2.5 py-1 rounded-full {{ $status==='open' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $status==='open' ? '● Open' : '◷ Upcoming' }}
                    </span>
                    @if($seats !== null && $seats <= 5 && $seats > 0)
                    <span class="absolute top-3 right-3 text-xs font-bold px-2.5 py-1 rounded-full bg-red-100 text-red-700">{{ $seats }} left!</span>
                    @elseif($seats === 0)
                    <span class="absolute top-3 right-3 text-xs font-bold px-2.5 py-1 rounded-full bg-gray-200 text-gray-500">Full</span>
                    @endif
                </div>
                <div class="p-5 flex flex-col flex-1">
                    <h3 class="font-bold text-gray-900 text-lg leading-snug mb-2 group-hover:text-brandMaroon-700 transition-colors">{{ $title }}</h3>
                    <p class="text-sm text-gray-500 line-clamp-2 flex-1">{{ $shortDesc }}</p>
                    <div class="flex items-center justify-between pt-3 mt-3 border-t border-gray-100">
                        <span class="text-xs text-gray-400 flex items-center gap-1">
                            @if($startDate)
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $startDate->format('d M Y') }}
                            @endif
                        </span>
                        <span class="font-bold text-sm {{ $fee && $fee > 0 ? 'text-brandMaroon-700' : 'text-green-600' }}">
                            {{ $fee && $fee > 0 ? 'MVR '.number_format($fee,0) : 'Free' }}
                        </span>
                    </div>
                </div>
            </a>
            @empty
            <div class="col-span-3 text-center py-12 text-gray-400">No open courses right now.</div>
            @endforelse
        </div>
        <div class="text-center mt-10">
            <a href="{{ route('public.courses.index') }}"
               class="inline-flex items-center gap-2 font-bold px-8 py-4 rounded-xl shadow transition-all hover:scale-105 text-white"
               style="background:#7C2D37">
                View All Courses
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     4. WHY AKURU  — dark maroon
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20" style="background:linear-gradient(160deg,#491821,#6B2630)">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <span class="text-brandGold-400 font-bold text-xs uppercase tracking-widest">Why choose us</span>
            <h2 class="text-3xl sm:text-4xl font-bold text-white mt-2">Why Akuru Institute?</h2>
            <p class="text-white/55 mt-2 max-w-xl mx-auto text-sm">Trusted by hundreds of Maldivian families for authentic Islamic education.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 max-w-5xl mx-auto">
            @foreach([
                ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title'=>'Qualified Instructors', 'desc'=>'All teachers hold recognised Islamic qualifications with years of experience.'],
                ['icon'=>'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5', 'title'=>'Structured Curriculum', 'desc'=>'Planned programmes for Quran, Arabic & Islamic Studies from beginner to advanced.'],
                ['icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'title'=>'Flexible Schedules', 'desc'=>'Morning, evening & weekend classes to fit around school, work, and family.'],
                ['icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'title'=>'All Ages Welcome', 'desc'=>'Classes for children, teens, and adults — everyone learns at the right pace.'],
                ['icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'title'=>'Affordable Fees', 'desc'=>'Quality Islamic education accessible to all with fair and transparent fees.'],
                ['icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'title'=>'Certificates', 'desc'=>'Earn recognised certificates upon completion of your programme.'],
            ] as $f)
            <div class="flex gap-4 p-5 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition-colors">
                <div class="w-10 h-10 rounded-lg shrink-0 flex items-center justify-center" style="background:rgba(201,162,39,0.2)">
                    <svg class="w-5 h-5" style="color:#C9A227" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-white text-sm mb-1">{{ $f['title'] }}</h3>
                    <p class="text-white/55 text-xs leading-relaxed">{{ $f['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     5. GALLERY  — white with bordered images
══════════════════════════════════════════════════════════════ --}}
@if(isset($galleryPhotos) && $galleryPhotos->count() > 0)
@php
    $lbData = $galleryPhotos->map(fn($p) => ['src'=>\Storage::url($p->file_path),'title'=>$p->title??'','caption'=>$p->caption??''])->values();
@endphp
<script>
const _hLb=@json($lbData),_hLbLen=_hLb.length;let _hLbI=0,_hLbX=null;
function openHLb(i){_hLbI=i;_hLbRend();document.getElementById('hlb').style.display='flex';document.body.style.overflow='hidden';}
function closeHLb(){document.getElementById('hlb').style.display='none';document.body.style.overflow='';}
function navHLb(d){_hLbI=(_hLbI+d+_hLbLen)%_hLbLen;_hLbRend();}
function _hLbRend(){const p=_hLb[_hLbI],el=document.getElementById('hlb-img');el.style.opacity=0;el.src=p.src;el.onload=()=>el.style.opacity=1;document.getElementById('hlb-ttl').textContent=p.title;document.getElementById('hlb-cpt').textContent=p.caption;document.getElementById('hlb-cnt').textContent=(_hLbI+1)+' / '+_hLbLen;}
document.addEventListener('keydown',e=>{if(document.getElementById('hlb').style.display==='none')return;if(e.key==='Escape')closeHLb();if(e.key==='ArrowRight')navHLb(1);if(e.key==='ArrowLeft')navHLb(-1);});
</script>
<section class="py-14 bg-white">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <span class="text-brandMaroon-600 font-bold text-xs uppercase tracking-widest">Life at Akuru</span>
                <h2 class="text-3xl font-bold text-gray-900 mt-1">Our Gallery</h2>
            </div>
            <a href="{{ route('public.gallery.index') }}" class="text-sm font-semibold text-brandMaroon-600 hover:underline">View all →</a>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
            @foreach($galleryPhotos as $idx => $photo)
            <button onclick="openHLb({{ $idx }})" class="group relative aspect-square overflow-hidden rounded-lg">
                <img src="{{ \Storage::url($photo->thumbnail_path ?? $photo->file_path) }}"
                     alt="{{ $photo->alt_text ?? '' }}"
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy">
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/35 flex items-center justify-center transition-all">
                    <svg class="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                </div>
            </button>
            @endforeach
        </div>
    </div>
</section>
{{-- Lightbox --}}
<div id="hlb" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.94);flex-direction:column;align-items:center;justify-content:center;padding:1rem" onclick="if(event.target===this)closeHLb()">
    <button onclick="closeHLb()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,0.12);border:none;border-radius:50%;width:2.5rem;height:2.5rem;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center"><svg style="width:1.1rem;height:1.1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
    <span id="hlb-cnt" style="position:absolute;top:1rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.5);font-size:.8rem"></span>
    <button onclick="navHLb(-1)" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.12);border:none;border-radius:50%;width:2.75rem;height:2.75rem;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center"><svg style="width:1.25rem;height:1.25rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
    <div style="display:flex;flex-direction:column;align-items:center;max-width:900px;width:100%">
        <img id="hlb-img" src="" alt="" style="max-height:74vh;max-width:100%;object-fit:contain;border-radius:.5rem;transition:opacity .25s"
             ontouchstart="_hLbX=event.changedTouches[0].clientX" ontouchend="const dx=event.changedTouches[0].clientX-_hLbX;if(dx>50)navHLb(-1);else if(dx<-50)navHLb(1)">
        <p id="hlb-ttl" style="color:white;font-weight:600;margin-top:.75rem;text-align:center"></p>
        <p id="hlb-cpt" style="color:rgba(255,255,255,.5);font-size:.8rem;margin-top:.2rem;text-align:center"></p>
    </div>
    <button onclick="navHLb(1)" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.12);border:none;border-radius:50%;width:2.75rem;height:2.75rem;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center"><svg style="width:1.25rem;height:1.25rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     6. TESTIMONIALS  — warm gold
══════════════════════════════════════════════════════════════ --}}
@if(isset($testimonials) && $testimonials->isNotEmpty())
<section class="py-14 sm:py-20" style="background:#FBEDC7"
    x-data="{
        cur: 0,
        total: {{ $testimonials->count() }},
        t: null,
        init(){ this.t=setInterval(()=>{ this.cur=(this.cur+1)%this.total; },5000); },
        go(i){ this.cur=i; clearInterval(this.t); this.init(); }
    }">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <span class="font-bold text-xs uppercase tracking-widest" style="color:#7C2D37">Student voices</span>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-2">What Our Students Say</h2>
        </div>
        <div class="relative max-w-2xl mx-auto">
            @foreach($testimonials as $idx => $t)
            <div x-show="cur === {{ $idx }}"
                 x-transition:enter="transition-opacity duration-500"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-500"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="bg-white rounded-2xl p-8 text-center shadow-md"
                 style="border:1px solid rgba(201,162,39,0.3)">
                <p class="text-gray-700 text-lg leading-relaxed mb-6">"{{ $t->quote }}"</p>
                <div class="flex items-center justify-center gap-3">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold text-lg" style="background:#7C2D37">
                        {{ strtoupper(substr($t->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-gray-900">{{ $t->name }}</p>
                        @if(!empty($t->role))<p class="text-xs text-gray-500">{{ $t->role }}</p>@endif
                    </div>
                </div>
            </div>
            @endforeach
            @if($testimonials->count() > 1)
            <div class="flex justify-center gap-2 mt-6">
                @foreach($testimonials as $idx => $t)
                <button @click="go({{ $idx }})"
                        :class="cur === {{ $idx }} ? 'w-6' : 'w-2 opacity-40'"
                        class="h-2 rounded-full transition-all duration-300" style="background:#7C2D37"></button>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     7. NEWS & EVENTS  — white
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 bg-white border-t border-gray-100">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-12">
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Latest News</h2>
                    <a href="{{ route('public.news.index') }}" class="text-sm text-brandMaroon-600 font-semibold hover:underline">All news →</a>
                </div>
                <div class="space-y-3">
                    @forelse($posts as $post)
                    @php $postSlug = $post->slug ?? $post->id ?? null; @endphp
                    <a href="{{ $postSlug ? route('public.news.show', $postSlug) : route('public.news.index') }}"
                       class="flex gap-4 p-4 rounded-xl bg-gray-50 hover:bg-brandMaroon-50 border border-transparent hover:border-brandMaroon-100 transition-colors group">
                        <div class="w-12 h-12 rounded-lg shrink-0 flex items-center justify-center" style="background:#F4D8DB">
                            <svg class="w-5 h-5 text-brandMaroon-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 text-sm leading-snug line-clamp-2">{{ $post->title }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($post->published_at ?? now())->format('d M Y') }}</p>
                        </div>
                    </a>
                    @empty
                    <p class="text-sm text-gray-400">No news yet.</p>
                    @endforelse
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Upcoming Events</h2>
                    <a href="{{ route('public.events.index') }}" class="text-sm font-semibold hover:underline" style="color:#A8861F">All events →</a>
                </div>
                <div class="space-y-3">
                    @forelse($events as $event)
                    @php $evSlug = $event->slug ?? $event->id ?? null; @endphp
                    <a href="{{ $evSlug ? route('public.events.show', $evSlug) : route('public.events.index') }}"
                       class="flex gap-4 p-4 rounded-xl bg-gray-50 hover:bg-brandGold-50 border border-transparent hover:border-brandGold-200 transition-colors group">
                        @php $evDate = \Carbon\Carbon::parse($event->start_date ?? now()); @endphp
                        <div class="shrink-0 text-center w-12">
                            <div class="text-white text-xs font-bold uppercase px-1 py-0.5 rounded-t" style="background:#7C2D37">{{ $evDate->format('M') }}</div>
                            <div class="border border-gray-200 rounded-b text-lg font-bold text-gray-900 leading-tight py-0.5">{{ $evDate->format('d') }}</div>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 text-sm">{{ $event->title }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $event->location ?? 'Akuru Institute' }}</p>
                        </div>
                    </a>
                    @empty
                    <div class="text-center py-8 text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-sm">No upcoming events scheduled.</p>
                        <a href="{{ route('public.contact.create') }}" class="text-xs mt-1 block text-brandMaroon-500 hover:underline">Enquire about workshops →</a>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     8. LOCATION + CONTACT  — dark maroon
══════════════════════════════════════════════════════════════ --}}
<section class="py-14" style="background:linear-gradient(160deg,#491821,#5A1F28)">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <span style="color:#C9A227" class="font-bold text-xs uppercase tracking-widest">Find us</span>
            <h2 class="text-3xl font-bold text-white mt-1">Visit Akuru Institute</h2>
        </div>
        <div class="grid lg:grid-cols-2 gap-8 items-center max-w-5xl mx-auto">
            <div class="rounded-2xl overflow-hidden shadow-xl h-64 lg:h-72" style="background:#3D1219">
                <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d15882!2d73.5093!3d4.1755!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3b3f7f!2sMale!5e0!3m2!1sen!2smv!4v1"
                    width="100%" height="100%" style="border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            <div class="space-y-4">
                <div class="flex items-start gap-4 p-4 rounded-xl" style="background:rgba(255,255,255,0.08)">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:#C9A227">
                        <svg class="w-5 h-5" style="color:#491821" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Address</p>
                        <p class="text-white/60 text-sm">{{ $siteSettings['address'] ?? 'Malé, Republic of Maldives' }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 p-4 rounded-xl" style="background:rgba(255,255,255,0.08)">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:#C9A227">
                        <svg class="w-5 h-5" style="color:#491821" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Phone</p>
                        <a href="tel:{{ $siteSettings['phone'] ?? '+9607972434' }}" class="text-white/60 hover:text-white text-sm">{{ $siteSettings['phone'] ?? '+960 797 2434' }}</a>
                    </div>
                </div>
                <div class="flex items-start gap-4 p-4 rounded-xl" style="background:rgba(255,255,255,0.08)">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-purple-600">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Viber</p>
                        <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to know about Akuru Institute.') }}"
                           class="text-white/60 hover:text-white text-sm">Chat with us on Viber</a>
                    </div>
                </div>
                <a href="{{ route('public.contact.create') }}"
                   class="flex items-center justify-center gap-2 font-bold px-6 py-3.5 rounded-xl transition-all hover:opacity-90 w-full"
                   style="background:#C9A227;color:#491821">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Send us a Message
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     9. FINAL CTA  — gold gradient (contrasts with dark maroon Location above)
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-16" style="background:linear-gradient(135deg,#A8861F 0%,#C9A227 50%,#E8BC3C 100%)">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold mb-3" style="color:#3D1219">Ready to Start Your Journey?</h2>
        <p class="mb-8 max-w-xl mx-auto" style="color:rgba(61,18,25,0.65)">Join hundreds of students who chose Akuru Institute for their Islamic education.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('public.courses.index') }}"
               class="inline-flex items-center justify-center gap-2 font-bold px-8 py-4 rounded-xl text-lg shadow-lg transition-all hover:scale-105"
               style="background:#491821;color:white">
                {{ __('public.Enroll') }}
            </a>
            <a href="{{ route('public.contact.create') }}"
               class="inline-flex items-center justify-center gap-2 font-semibold px-8 py-4 rounded-xl text-lg transition-all hover:scale-105"
               style="background:rgba(61,18,25,0.12);color:#3D1219;border:2px solid rgba(61,18,25,0.25)">
                {{ __('public.Contact Us') }}
            </a>
        </div>
    </div>
</section>

@endsection
