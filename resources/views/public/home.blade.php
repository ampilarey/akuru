@extends('public.layouts.public')

@section('title', $text['title'] ?? __('public.Welcome to Akuru Institute'))
@section('description', $text['desc'] ?? __('public.Learn Quran, Arabic, and Islamic Studies'))

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .hero-section { height: 560px; }
    @media (max-width: 640px) { .hero-section { height: 480px; } }
    .section-divider { height: 4px; background: linear-gradient(90deg, #C9A227, #7C2D37, #C9A227); }
</style>
@endpush

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     1. HERO SLIDER
══════════════════════════════════════════════════════════════ --}}
@php $bannerCount = count($heroBanners); @endphp
<section
    x-data="{
        cur: 0,
        total: {{ $bannerCount }},
        t: null,
        init(){ this.t = setInterval(()=>{ this.cur=(this.cur+1)%this.total; }, 5500); },
        go(i){ this.cur=i; clearInterval(this.t); this.init(); }
    }"
    class="hero-section relative overflow-hidden bg-brandMaroon-900">

    {{-- Each slide: background + content together --}}
    @foreach($heroBanners as $i => $banner)
    @php
        $img      = is_object($banner) && !empty($banner->image_path)
                    ? (str_starts_with($banner->image_path,'http') ? $banner->image_path : asset('storage/'.$banner->image_path))
                    : null;
        $title    = is_object($banner) ? ($banner->title ?? $banner->headline ?? 'Welcome to Akuru Institute') : 'Welcome to Akuru Institute';
        $subtitle = is_object($banner) ? ($banner->subtitle ?? $banner->subheadline ?? 'Learn Quran, Arabic, and Islamic Studies') : 'Learn Quran, Arabic, and Islamic Studies';
    @endphp
    <div
        x-show="cur === {{ $i }}"
        x-transition:enter="transition-opacity duration-700"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-700 absolute inset-0"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @if($i > 0) x-cloak @endif
        class="absolute inset-0 flex items-center">

        {{-- Background --}}
        @if($img)
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image:url('{{ $img }}')"></div>
            <div class="absolute inset-0" style="background:linear-gradient(135deg, rgba(73,24,33,0.92) 0%, rgba(90,31,40,0.75) 50%, rgba(73,24,33,0.5) 100%)"></div>
        @else
            <div class="absolute inset-0" style="background:linear-gradient(135deg, #491821 0%, #5A1F28 40%, #7C2D37 70%, #6B2630 100%)"></div>
            {{-- Decorative Islamic geometric pattern --}}
            <div class="absolute inset-0 opacity-5" style="background-image: url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23C9A227' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")"></div>
        @endif

        {{-- Content --}}
        <div class="relative z-10 w-full container mx-auto px-4">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 bg-brandGold-500/25 border border-brandGold-400/50 text-brandGold-300 text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-full mb-5">
                    <span>🕌</span> Akuru Institute
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white leading-tight mb-4" style="text-shadow:0 2px 12px rgba(0,0,0,0.5)">{{ $title }}</h1>
                <p class="text-lg sm:text-xl text-white/80 mb-8 leading-relaxed max-w-xl">{{ $subtitle }}</p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('public.courses.index') }}"
                       class="inline-flex items-center justify-center gap-2 font-bold px-8 py-4 rounded-xl text-base shadow-lg transition-all hover:scale-105 active:scale-95"
                       style="background:#C9A227;color:#491821">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5"/></svg>
                        {{ __('public.Enroll') }}
                    </a>
                    <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to know about Akuru Institute.') }}"
                       class="inline-flex items-center justify-center gap-2 bg-purple-700 hover:bg-purple-600 text-white font-bold px-8 py-4 rounded-xl text-base shadow-lg transition-all hover:scale-105">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
                        Chat on Viber
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    {{-- Nav arrows (only if >1 banner) --}}
    @if($bannerCount > 1)
    <button @click="go((cur-1+total)%total)" class="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/15 hover:bg-white/30 text-white rounded-full flex items-center justify-center transition-colors border border-white/20">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <button @click="go((cur+1)%total)" class="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/15 hover:bg-white/30 text-white rounded-full flex items-center justify-center transition-colors border border-white/20">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-20 flex gap-2">
        @for($d = 0; $d < $bannerCount; $d++)
        <button @click="go({{ $d }})"
                :class="cur==={{ $d }} ? 'w-6 opacity-100' : 'w-2 opacity-50 hover:opacity-80'"
                class="h-2 rounded-full transition-all duration-300" style="background:#C9A227"></button>
        @endfor
    </div>
    @endif
</section>
<div class="section-divider"></div>

{{-- ══════════════════════════════════════════════════════════════
     2. STATS STRIP  (Gold — strong contrast after dark hero)
══════════════════════════════════════════════════════════════ --}}
@if(isset($stats) && ($stats['courses'] > 0 || $stats['students'] > 0))
<section class="py-10" style="background:linear-gradient(90deg,#A8861F,#C9A227,#A8861F)">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
            <div>
                <div class="text-4xl font-bold" style="color:#491821">{{ number_format($stats['students']) }}+</div>
                <div class="text-sm mt-1 font-medium" style="color:#5A1F28">Students Enrolled</div>
            </div>
            <div>
                <div class="text-4xl font-bold" style="color:#491821">{{ $stats['courses'] }}</div>
                <div class="text-sm mt-1 font-medium" style="color:#5A1F28">Courses Offered</div>
            </div>
            <div>
                <div class="text-4xl font-bold" style="color:#491821">5+</div>
                <div class="text-sm mt-1 font-medium" style="color:#5A1F28">Years of Service</div>
            </div>
            <div>
                <div class="text-4xl font-bold" style="color:#491821">{{ $stats['teachers'] ?? 10 }}+</div>
                <div class="text-sm mt-1 font-medium" style="color:#5A1F28">Qualified Teachers</div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     3. OPEN COURSES  (White — clean, fresh)
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20 bg-white">
    <div class="container mx-auto px-4">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
            <div>
                <span class="inline-block bg-green-100 text-green-800 font-bold text-xs uppercase tracking-widest px-3 py-1 rounded-full mb-2">Now Enrolling</span>
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Open Courses</h2>
                <p class="text-gray-500 mt-1 text-sm">Secure your seat — places are limited</p>
            </div>
            <a href="{{ route('public.courses.index') }}" class="shrink-0 text-brandMaroon-600 hover:text-brandMaroon-800 font-semibold text-sm flex items-center gap-1">
                View all courses <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
               class="group block bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl border border-gray-200 hover:-translate-y-1 transition-all duration-300">
                <div class="relative h-44 overflow-hidden" style="background:linear-gradient(135deg,#F3EBE0,#FBEDC7)">
                    @if($image)
                        <img src="{{ $image }}" alt="{{ $title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="w-16 h-16 text-brandGold-500 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253"/></svg>
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
                <div class="p-5">
                    <h3 class="font-bold text-gray-900 text-lg leading-snug mb-1 group-hover:text-brandMaroon-700 transition-colors">{{ $title }}</h3>
                    <p class="text-sm text-gray-500 line-clamp-2 mb-4">{{ $shortDesc }}</p>
                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <span class="text-xs text-gray-400 flex items-center gap-1">
                            @if($startDate)
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                {{ $startDate->format('d M Y') }}
                            @else
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Enroll anytime
                            @endif
                        </span>
                        <span class="font-bold text-sm {{ $fee && $fee > 0 ? 'text-brandMaroon-700' : 'text-green-600' }}">
                            {{ $fee && $fee > 0 ? 'MVR '.number_format($fee,0) : 'Free' }}
                        </span>
                    </div>
                </div>
            </a>
            @empty
            <div class="col-span-3 text-center py-10 text-gray-400">No open courses right now. Check back soon.</div>
            @endforelse
        </div>

        <div class="text-center mt-10">
            <a href="{{ route('public.courses.index') }}"
               class="inline-flex items-center gap-2 font-bold px-8 py-4 rounded-xl shadow-lg transition-all hover:scale-105 text-base"
               style="background:#7C2D37;color:white">
                View All Courses
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     4. WHY AKURU  (Dark maroon — rich contrast after white)
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20" style="background:linear-gradient(160deg,#491821 0%,#5A1F28 50%,#3D1219 100%)">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <span class="inline-block border border-brandGold-500/50 text-brandGold-400 font-bold text-xs uppercase tracking-widest px-4 py-2 rounded-full mb-3">Why choose us</span>
            <h2 class="text-3xl sm:text-4xl font-bold text-white mt-1">Why Akuru Institute?</h2>
            <p class="text-white/55 mt-2 max-w-xl mx-auto text-sm">Trusted by hundreds of Maldivian families for authentic, structured Islamic education.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 max-w-5xl mx-auto">
            @foreach([
                ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title'=>'Qualified Instructors', 'desc'=>'All teachers hold recognised Islamic education qualifications with years of teaching experience.'],
                ['icon'=>'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5', 'title'=>'Structured Curriculum', 'desc'=>'Well-planned programmes for Quran, Arabic, and Islamic Studies from beginner to advanced.'],
                ['icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'title'=>'Flexible Schedules', 'desc'=>'Morning, evening, and weekend classes designed to fit around school, work, and family.'],
                ['icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'title'=>'All Ages Welcome', 'desc'=>'Dedicated programmes for children, teenagers, and adults — everyone learns at the right pace.'],
                ['icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'title'=>'Affordable Fees', 'desc'=>'Quality Islamic education should be accessible to all. Our fees are fair and transparent.'],
                ['icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'title'=>'Recognised Certificates', 'desc'=>'Earn certificates upon completion that recognise your achievement and dedication.'],
            ] as $f)
            <div class="flex gap-4 p-5 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 hover:border-brandGold-500/40 transition-all">
                <div class="w-10 h-10 rounded-lg shrink-0 flex items-center justify-center" style="background:rgba(201,162,39,0.2)">
                    <svg class="w-5 h-5" style="color:#C9A227" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-white mb-1 text-sm">{{ $f['title'] }}</h3>
                    <p class="text-xs text-white/55 leading-relaxed">{{ $f['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
<div class="section-divider"></div>

{{-- ══════════════════════════════════════════════════════════════
     5. PHOTO GALLERY  (Warm cream — light after dark maroon)
══════════════════════════════════════════════════════════════ --}}
@if(isset($galleryPhotos) && $galleryPhotos->count() > 0)
<section class="py-14" style="background:#F3EBE0">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <span class="text-brandMaroon-700 font-bold text-xs uppercase tracking-widest">Life at Akuru</span>
                <h2 class="text-3xl font-bold text-gray-900 mt-1">Our Gallery</h2>
            </div>
            <a href="{{ route('public.gallery.index') }}" class="text-brandMaroon-600 hover:text-brandMaroon-800 text-sm font-semibold transition-colors">View all →</a>
        </div>

        @php
            $lbData = $galleryPhotos->map(fn($p) => [
                'src'     => \Storage::url($p->file_path),
                'title'   => $p->title ?? '',
                'caption' => $p->caption ?? '',
            ])->values();
        @endphp
        <script>
        const _homeLb = @json($lbData);
        let _homeLbIdx = 0, _homeLbStartX = null;
        function openHomeLb(i){ _homeLbIdx=i; _homeLbRender(); document.getElementById('hlb').style.display='flex'; document.body.style.overflow='hidden'; }
        function closeHomeLb(){ document.getElementById('hlb').style.display='none'; document.body.style.overflow=''; }
        function homeLbNav(d){ _homeLbIdx=(_homeLbIdx+d+_homeLb.length)%_homeLb.length; _homeLbRender(); }
        function _homeLbRender(){ const p=_homeLb[_homeLbIdx]; const el=document.getElementById('hlb-img'); el.style.opacity=0; el.src=p.src; el.onload=()=>{ el.style.opacity=1; }; document.getElementById('hlb-title').textContent=p.title; document.getElementById('hlb-caption').textContent=p.caption; document.getElementById('hlb-counter').textContent=(_homeLbIdx+1)+' / '+_homeLb.length; }
        document.addEventListener('keydown',function(e){ if(document.getElementById('hlb').style.display==='none')return; if(e.key==='Escape')closeHomeLb(); if(e.key==='ArrowRight')homeLbNav(1); if(e.key==='ArrowLeft')homeLbNav(-1); });
        </script>

        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
            @foreach($galleryPhotos as $idx => $photo)
            <button onclick="openHomeLb({{ $idx }})"
                    class="group relative aspect-square overflow-hidden rounded-lg focus:outline-none">
                <img src="{{ \Storage::url($photo->thumbnail_path ?? $photo->file_path) }}"
                     alt="{{ $photo->alt_text ?? $photo->title ?? '' }}"
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy">
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all duration-300 flex items-center justify-center">
                    <svg class="w-7 h-7 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                </div>
            </button>
            @endforeach
        </div>

        {{-- Lightbox --}}
        <div id="hlb" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.95);align-items:center;justify-content:center;padding:1rem" onclick="if(event.target===this)closeHomeLb()">
            <button onclick="closeHomeLb()" style="position:absolute;top:1rem;right:1rem;width:2.5rem;height:2.5rem;display:flex;align-items:center;justify-content:center;border-radius:9999px;background:rgba(255,255,255,0.15);color:white;border:none;cursor:pointer"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            <span id="hlb-counter" style="position:absolute;top:1rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,0.5);font-size:.875rem"></span>
            <button onclick="homeLbNav(-1)" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;border-radius:9999px;background:rgba(255,255,255,0.15);color:white;border:none;cursor:pointer"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
            <div style="display:flex;flex-direction:column;align-items:center;max-width:900px;width:100%">
                <img id="hlb-img" src="" alt="" style="max-height:75vh;max-width:100%;object-fit:contain;border-radius:.5rem;transition:opacity .3s"
                     ontouchstart="if(event.changedTouches)_homeLbStartX=event.changedTouches[0].clientX"
                     ontouchend="if(event.changedTouches){const dx=event.changedTouches[0].clientX-_homeLbStartX;if(dx>50)homeLbNav(-1);else if(dx<-50)homeLbNav(1);}">
                <p id="hlb-title" style="color:white;font-weight:600;margin-top:.75rem"></p>
                <p id="hlb-caption" style="color:rgba(255,255,255,0.5);font-size:.875rem;margin-top:.25rem"></p>
            </div>
            <button onclick="homeLbNav(1)" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;border-radius:9999px;background:rgba(255,255,255,0.15);color:white;border:none;cursor:pointer"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     6. TESTIMONIALS  (Gold — warm & inviting)
══════════════════════════════════════════════════════════════ --}}
@if(isset($testimonials) && $testimonials->isNotEmpty())
<section class="py-14 sm:py-20" style="background:linear-gradient(160deg,#C9A227 0%,#A8861F 100%)"
    x-data="{
        cur: 0,
        total: {{ $testimonials->count() }},
        t: null,
        init(){ this.t = setInterval(()=>{ this.cur=(this.cur+1)%this.total; }, 5000); },
        go(i){ this.cur=i; clearInterval(this.t); this.init(); }
    }">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <span class="inline-block bg-brandMaroon-800/30 border border-brandMaroon-700/40 text-brandMaroon-900 font-bold text-xs uppercase tracking-widest px-4 py-2 rounded-full mb-2">Student voices</span>
            <h2 class="text-3xl sm:text-4xl font-bold" style="color:#491821">What Our Students Say</h2>
        </div>

        <div class="relative max-w-2xl mx-auto">
            @foreach($testimonials as $idx => $t)
            <div x-show="cur === {{ $idx }}"
                 x-transition:enter="transition duration-500"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 @if($idx > 0) x-cloak @endif
                 class="bg-white rounded-2xl p-8 text-center shadow-xl relative"
                 style="border:1px solid rgba(73,24,33,0.15)">
                <div class="text-6xl font-serif absolute -top-2 left-6 leading-none opacity-20" style="color:#491821">"</div>
                <p class="text-gray-700 text-lg leading-relaxed mb-6 relative">"{{ $t->quote }}"</p>
                <div class="flex items-center justify-center gap-3">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold shrink-0 text-lg" style="background:#7C2D37">
                        {{ strtoupper(substr($t->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-gray-900">{{ $t->name }}</p>
                        @if($t->role)<p class="text-xs text-gray-500">{{ $t->role }}</p>@endif
                    </div>
                </div>
            </div>
            @endforeach
            <div class="flex justify-center gap-2 mt-6">
                @foreach($testimonials as $idx => $t)
                <button @click="go({{ $idx }})"
                        :class="cur === {{ $idx }} ? 'w-5 opacity-100' : 'w-2 opacity-50 hover:opacity-80'"
                        class="h-2 rounded-full transition-all duration-300" style="background:#491821"></button>
                @endforeach
            </div>
        </div>
    </div>
</section>
<div class="section-divider"></div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     7. NEWS & EVENTS  (White — clean contrast after gold)
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-12">
            {{-- News --}}
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Latest News</h2>
                    <a href="{{ route('public.news.index') }}" class="text-sm text-brandMaroon-600 font-semibold hover:underline">All news →</a>
                </div>
                <div class="space-y-3">
                    @forelse($posts as $post)
                    @php $postSlug = $post->slug ?? $post->id ?? null; @endphp
                    <a href="{{ $postSlug ? route('public.news.show', $postSlug) : route('public.news.index') }}"
                       class="flex gap-4 p-4 bg-gray-50 hover:bg-brandMaroon-50 rounded-xl transition-colors group border border-transparent hover:border-brandMaroon-200">
                        <div class="w-14 h-14 rounded-lg shrink-0 flex items-center justify-center" style="background:#F4D8DB">
                            <svg class="w-6 h-6 text-brandMaroon-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 transition-colors text-sm leading-snug line-clamp-2">{{ $post->title }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($post->published_at ?? now())->format('d M Y') }}</p>
                        </div>
                    </a>
                    @empty
                    <p class="text-gray-400 text-sm">No news yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Events --}}
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Upcoming Events</h2>
                    <a href="{{ route('public.events.index') }}" class="text-sm font-semibold hover:underline" style="color:#A8861F">All events →</a>
                </div>
                <div class="space-y-3">
                    @forelse($events as $event)
                    @php $evSlug = $event->slug ?? $event->id ?? null; @endphp
                    <a href="{{ $evSlug ? route('public.events.show', $evSlug) : route('public.events.index') }}"
                       class="flex gap-4 p-4 bg-gray-50 hover:bg-brandGold-50 rounded-xl transition-colors group border border-transparent hover:border-brandGold-300">
                        @php $evDate = \Carbon\Carbon::parse($event->start_date ?? now()); @endphp
                        <div class="shrink-0 w-14 text-center">
                            <div class="text-white rounded-t-lg py-0.5 text-xs font-bold uppercase px-2" style="background:#7C2D37">{{ $evDate->format('M') }}</div>
                            <div class="border border-t-0 border-gray-200 rounded-b-lg text-xl font-bold text-gray-900 py-1 px-2">{{ $evDate->format('d') }}</div>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 transition-colors text-sm">{{ $event->title }}</p>
                            <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                {{ $event->location ?? 'Akuru Institute' }}
                            </p>
                        </div>
                    </a>
                    @empty
                    <p class="text-gray-400 text-sm">No upcoming events.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     8. UPCOMING INTAKES  (Dark maroon strip)
══════════════════════════════════════════════════════════════ --}}
@php
    $upcomingCourses = $courses->filter(fn($c)=>is_object($c)&&method_exists($c,'getAttribute')&&in_array($c->status??'',['open','upcoming'])&&!empty($c->start_date));
@endphp
@if($upcomingCourses->count() > 0)
<section class="py-10" style="background:#5A1F28">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Upcoming Intakes
            </h2>
            <a href="{{ route('public.courses.index') }}" class="text-sm text-brandGold-400 font-semibold hover:text-brandGold-300">View all →</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($upcomingCourses->take(6) as $uc)
            @php $ucDate = \Carbon\Carbon::parse($uc->start_date); @endphp
            <a href="{{ $uc->slug ? route('public.courses.show', $uc->slug) : route('public.courses.index') }}"
               class="flex items-center gap-4 rounded-xl px-4 py-3 transition-all hover:bg-white/10"
               style="background:rgba(255,255,255,0.08);border:1px solid rgba(201,162,39,0.25)">
                <div class="text-center shrink-0">
                    <div class="text-white rounded-t text-xs font-bold uppercase px-2 py-0.5" style="background:#C9A227;color:#491821">{{ $ucDate->format('M') }}</div>
                    <div class="border border-t-0 rounded-b text-lg font-bold text-white px-2 py-0.5" style="border-color:rgba(201,162,39,0.4)">{{ $ucDate->format('d') }}</div>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-white text-sm truncate">{{ $uc->title }}</p>
                    <p class="text-xs font-medium mt-0.5 {{ $uc->fee > 0 ? 'text-brandGold-400' : 'text-green-400' }}">{{ $uc->fee > 0 ? 'MVR '.number_format($uc->fee,0) : 'Free' }}</p>
                </div>
                <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded-full {{ $uc->status==='open' ? 'bg-green-500/20 text-green-300' : 'bg-amber-500/20 text-amber-300' }}">{{ ucfirst($uc->status) }}</span>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     9. EDUCATIONAL ARTICLES  (Warm cream)
══════════════════════════════════════════════════════════════ --}}
@if(isset($articles) && $articles->count() > 0)
<section class="py-14" style="background:#F3EBE0">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <span class="text-brandMaroon-700 font-bold text-xs uppercase tracking-widest">Learn & grow</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">Educational Articles</h2>
            </div>
            <a href="{{ route('public.articles.index') }}" class="hidden sm:block text-sm text-brandMaroon-600 font-medium hover:underline">View all →</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($articles as $article)
            <a href="{{ route('public.articles.show', $article->slug) }}" class="group block bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md border border-gray-100 transition-shadow">
                @if($article->cover_image)
                <div class="aspect-video overflow-hidden"><img src="{{ asset('storage/'.$article->cover_image) }}" alt="{{ $article->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"></div>
                @endif
                <div class="p-5">
                    <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 transition-colors leading-snug mb-1">{{ $article->title }}</p>
                    <p class="text-sm text-gray-500 line-clamp-2">{{ $article->excerpt }}</p>
                    <p class="text-xs text-gray-400 mt-3">{{ $article->published_at->format('d M Y') }}</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     10. LOCATION + CONTACT  (Dark maroon)
══════════════════════════════════════════════════════════════ --}}
<section class="py-14" style="background:linear-gradient(160deg,#491821 0%,#5A1F28 100%)">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <span class="inline-block border border-brandGold-500/40 text-brandGold-400 font-bold text-xs uppercase tracking-widest px-4 py-2 rounded-full mb-3">Find us</span>
            <h2 class="text-3xl font-bold text-white mt-1">Visit Akuru Institute</h2>
        </div>
        <div class="grid lg:grid-cols-2 gap-8 items-center max-w-5xl mx-auto">
            <div class="rounded-2xl overflow-hidden shadow-xl h-64 lg:h-80 bg-brandMaroon-700">
                <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d3966.0!2d73.5093!3d4.1755!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2smv!4v1234567890"
                    width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Akuru Institute Location"></iframe>
            </div>
            <div class="space-y-4">
                <div class="flex items-start gap-4 rounded-xl p-4" style="background:rgba(255,255,255,0.08)">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:#C9A227">
                        <svg class="w-5 h-5" style="color:#491821" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Address</p>
                        <p class="text-white/60 text-sm">{{ $siteSettings['address'] ?? 'Malé, Republic of Maldives' }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 rounded-xl p-4" style="background:rgba(255,255,255,0.08)">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:#C9A227">
                        <svg class="w-5 h-5" style="color:#491821" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Phone</p>
                        <a href="tel:{{ $siteSettings['phone'] ?? '+9607972434' }}" class="text-white/60 hover:text-white text-sm transition-colors">{{ $siteSettings['phone'] ?? '+960 797 2434' }}</a>
                    </div>
                </div>
                <div class="flex items-start gap-4 rounded-xl p-4" style="background:rgba(255,255,255,0.08)">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-purple-600">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Viber</p>
                        <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to know about Akuru Institute.') }}"
                           class="text-white/60 hover:text-white text-sm transition-colors">Chat with us on Viber</a>
                    </div>
                </div>
                <a href="{{ route('public.contact.create') }}"
                   class="flex items-center justify-center gap-2 font-bold px-6 py-3.5 rounded-xl transition-all hover:scale-105 w-full"
                   style="background:#C9A227;color:#491821">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Send us a Message
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     11. FINAL CTA  (Gold gradient — bright, energetic)
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-16" style="background:linear-gradient(135deg,#A8861F 0%,#C9A227 50%,#A8861F 100%)">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold mb-3" style="color:#491821">Ready to Start Your Journey?</h2>
        <p class="text-base mb-8 max-w-xl mx-auto" style="color:rgba(73,24,33,0.7)">Join hundreds of students who chose Akuru Institute for their Islamic education.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('public.courses.index') }}"
               class="inline-flex items-center justify-center gap-2 font-bold px-8 py-4 rounded-xl text-lg shadow-lg transition-all hover:scale-105"
               style="background:#491821;color:white">
                {{ __('public.Enroll') }}
            </a>
            <a href="{{ route('public.contact.create') }}"
               class="inline-flex items-center justify-center gap-2 font-semibold px-8 py-4 rounded-xl text-lg transition-all hover:scale-105"
               style="background:rgba(73,24,33,0.15);color:#491821;border:2px solid rgba(73,24,33,0.3)">
                {{ __('public.Contact Us') }}
            </a>
        </div>
    </div>
</section>

@endsection
