@extends('public.layouts.public')

@section('title', $text['title'] ?? __('public.Welcome to Akuru Institute'))
@section('description', $text['desc'] ?? __('public.Learn Quran, Arabic, and Islamic Studies'))

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     1. HERO SLIDER
══════════════════════════════════════════════════════════════ --}}
<section x-data="{
    current: 0,
    total: {{ count($heroBanners) }},
    timer: null,
    start() {
        this.timer = setInterval(() => { this.next(); }, 5500);
    },
    next() { this.current = (this.current + 1) % this.total; },
    prev() { this.current = (this.current - 1 + this.total) % this.total; },
    go(i) { this.current = i; clearInterval(this.timer); this.start(); }
}" x-init="start()" class="relative overflow-hidden bg-brandMaroon-900" style="min-height:520px;">

    {{-- Slides --}}
    @foreach($heroBanners as $i => $banner)
    @php
        $img = is_object($banner) && !empty($banner->image_path)
            ? (str_starts_with($banner->image_path, 'http') ? $banner->image_path : asset('storage/'.$banner->image_path))
            : null;
        $title    = is_object($banner) ? ($banner->title ?? $banner->headline ?? '') : '';
        $subtitle = is_object($banner) ? ($banner->subtitle ?? $banner->subheadline ?? '') : '';
    @endphp
    <div x-show="current === {{ $i }}"
         x-transition:enter="transition-opacity duration-700"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-700"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute inset-0">
        {{-- Background image or gradient --}}
        @if($img)
            <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ $img }}')"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-brandMaroon-900/85 via-brandMaroon-800/60 to-transparent"></div>
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-brandMaroon-900 via-brandMaroon-700 to-brandMaroon-800"></div>
        @endif
    </div>
    @endforeach

    {{-- Content --}}
    <div class="relative z-10 container mx-auto px-4 flex items-center" style="min-height:520px;">
        <div class="max-w-2xl py-20">
            @foreach($heroBanners as $i => $banner)
            @php
                $title    = is_object($banner) ? ($banner->title ?? $banner->headline ?? '') : '';
                $subtitle = is_object($banner) ? ($banner->subtitle ?? $banner->subheadline ?? '') : '';
            @endphp
            <div x-show="current === {{ $i }}"
                 x-transition:enter="transition duration-700 delay-200"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <span class="inline-block bg-brandGold-500/20 border border-brandGold-400/40 text-brandGold-300 text-xs font-bold uppercase tracking-widest px-3 py-1.5 rounded-full mb-4">
                    🕌 Akuru Institute
                </span>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white leading-tight mb-4 drop-shadow-lg">{{ $title }}</h1>
                <p class="text-lg sm:text-xl text-white/80 mb-8 leading-relaxed max-w-xl">{{ $subtitle }}</p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('public.courses.index') }}"
                       class="inline-flex items-center justify-center gap-2 bg-brandGold-500 hover:bg-brandGold-400 text-brandMaroon-900 font-bold px-7 py-3.5 rounded-xl text-base shadow-lg transition-all hover:scale-105">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13"/></svg>
                        {{ __('public.Enroll') }}
                    </a>
                    <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to know about Akuru Institute.') }}"
                       class="inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-500 text-white font-bold px-7 py-3.5 rounded-xl text-base shadow-lg transition-all hover:scale-105">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
                        Chat on Viber
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Prev / Next arrows --}}
    @if(count($heroBanners) > 1)
    <button @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/20 hover:bg-white/30 text-white rounded-full flex items-center justify-center transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <button @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/20 hover:bg-white/30 text-white rounded-full flex items-center justify-center transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    {{-- Dots --}}
    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex gap-2">
        @foreach($heroBanners as $i => $banner)
        <button @click="go({{ $i }})"
                :class="current === {{ $i }} ? 'w-6 bg-brandGold-400' : 'w-2 bg-white/50 hover:bg-white/80'"
                class="h-2 rounded-full transition-all duration-300"></button>
        @endforeach
    </div>
    @endif
</section>

{{-- ══════════════════════════════════════════════════════════════
     2. STATS STRIP
══════════════════════════════════════════════════════════════ --}}
@if(isset($stats) && ($stats['courses'] > 0 || $stats['students'] > 0))
<section class="py-8 bg-white border-b border-gray-100 shadow-sm">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
            <div>
                <div class="text-3xl sm:text-4xl font-bold text-brandMaroon-600">{{ number_format($stats['students']) }}+</div>
                <div class="text-gray-500 text-sm mt-0.5">Students Enrolled</div>
            </div>
            <div>
                <div class="text-3xl sm:text-4xl font-bold text-brandMaroon-600">{{ $stats['courses'] }}</div>
                <div class="text-gray-500 text-sm mt-0.5">Courses Offered</div>
            </div>
            <div>
                <div class="text-3xl sm:text-4xl font-bold text-brandGold-600">5+</div>
                <div class="text-gray-500 text-sm mt-0.5">Years of Service</div>
            </div>
            <div>
                <div class="text-3xl sm:text-4xl font-bold text-brandGold-600">{{ $stats['teachers'] ?? 10 }}+</div>
                <div class="text-gray-500 text-sm mt-0.5">Qualified Teachers</div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     3. OPEN COURSES
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20 bg-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
            <div>
                <span class="text-brandMaroon-600 font-bold text-xs uppercase tracking-widest">Now enrolling</span>
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-1">Open Courses</h2>
                <p class="text-gray-500 mt-1.5 text-sm">Secure your seat — places are limited</p>
            </div>
            <a href="{{ route('public.courses.index') }}" class="shrink-0 text-brandMaroon-600 hover:text-brandMaroon-800 font-semibold text-sm flex items-center gap-1">
                All courses <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
               class="group block bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 hover:-translate-y-1 transition-all duration-300">
                <div class="relative h-44 overflow-hidden bg-gradient-to-br from-brandBeige-300 to-brandGold-200">
                    @if($image)
                        <img src="{{ $image }}" alt="{{ $title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="w-14 h-14 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/></svg>
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
                        <span class="text-sm text-gray-400">
                            @if($startDate)
                                <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                {{ $startDate->format('d M Y') }}
                            @endif
                        </span>
                        <span class="font-bold {{ $fee && $fee > 0 ? 'text-brandMaroon-700' : 'text-green-600' }}">
                            {{ $fee && $fee > 0 ? number_format($fee,0).' MVR' : 'Free' }}
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
               class="inline-flex items-center gap-2 bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white font-bold px-8 py-3.5 rounded-xl shadow transition-all hover:scale-105">
                View All Courses
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     4. WHY AKURU
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 sm:py-20 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <span class="text-brandMaroon-600 font-bold text-xs uppercase tracking-widest">Why choose us</span>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-1">Why Akuru Institute?</h2>
            <p class="text-gray-500 mt-2 max-w-xl mx-auto">Trusted by hundreds of Maldivian families for authentic, structured Islamic education.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 max-w-5xl mx-auto">
            @foreach([
                ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title'=>'Qualified Instructors', 'desc'=>'All teachers hold recognised Islamic education qualifications with years of teaching experience.', 'color'=>'bg-brandMaroon-100 text-brandMaroon-700'],
                ['icon'=>'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'title'=>'Structured Curriculum', 'desc'=>'Well-planned programmes for Quran, Arabic, and Islamic Studies from beginner to advanced.', 'color'=>'bg-brandGold-100 text-brandGold-700'],
                ['icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'title'=>'Flexible Schedules', 'desc'=>'Morning, evening, and weekend classes designed to fit around school, work, and family.', 'color'=>'bg-brandMaroon-100 text-brandMaroon-700'],
                ['icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'title'=>'All Ages Welcome', 'desc'=>'Dedicated programmes for children, teenagers, and adults — everyone learns at the right pace.', 'color'=>'bg-brandGold-100 text-brandGold-700'],
                ['icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'title'=>'Affordable Fees', 'desc'=>'Quality Islamic education should be accessible to all. Our fees are fair and transparent.', 'color'=>'bg-brandMaroon-100 text-brandMaroon-700'],
                ['icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'title'=>'Recognised Certificates', 'desc'=>'Earn certificates upon completion that recognise your achievement and dedication.', 'color'=>'bg-brandGold-100 text-brandGold-700'],
            ] as $f)
            <div class="flex gap-4 p-5 rounded-xl border border-gray-100 hover:border-brandMaroon-200 hover:shadow-sm transition-all">
                <div class="w-11 h-11 {{ $f['color'] }} rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 mb-1 text-sm">{{ $f['title'] }}</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">{{ $f['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     5. PHOTO GALLERY STRIP
══════════════════════════════════════════════════════════════ --}}
@if(isset($galleryPhotos) && $galleryPhotos->count() > 0)
<section class="py-14 bg-brandMaroon-800">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <span class="text-brandGold-400 font-bold text-xs uppercase tracking-widest">Life at Akuru</span>
                <h2 class="text-3xl font-bold text-white mt-1">Our Gallery</h2>
            </div>
            <a href="{{ route('public.gallery.index') }}" class="text-white/60 hover:text-white text-sm font-medium transition-colors">View all →</a>
        </div>

        @php
            $lbData = $galleryPhotos->map(fn($p) => [
                'src'     => \Storage::url($p->file_path),
                'title'   => $p->title ?? '',
                'caption' => $p->caption ?? '',
            ])->values();
        @endphp
        <script>const _homeLb = @json($lbData); let _homeLbOpen=false, _homeLbIdx=0, _homeLbStartX=null;
        function openHomeLb(i){ _homeLbIdx=i; _homeLbRender(); document.getElementById('hlb').classList.remove('hidden'); document.getElementById('hlb').classList.add('flex'); document.body.style.overflow='hidden'; }
        function closeHomeLb(){ document.getElementById('hlb').classList.add('hidden'); document.getElementById('hlb').classList.remove('flex'); document.body.style.overflow=''; }
        function homeLbNav(d){ _homeLbIdx=(_homeLbIdx+d+_homeLb.length)%_homeLb.length; _homeLbRender(); }
        function _homeLbRender(){ const p=_homeLb[_homeLbIdx]; const el=document.getElementById('hlb-img'); el.style.opacity=0; el.src=p.src; el.onload=()=>el.style.opacity=1; document.getElementById('hlb-title').textContent=p.title; document.getElementById('hlb-caption').textContent=p.caption; document.getElementById('hlb-counter').textContent=(_homeLbIdx+1)+' / '+_homeLb.length; }
        document.addEventListener('keydown',e=>{ if(document.getElementById('hlb').classList.contains('hidden'))return; if(e.key==='Escape')closeHomeLb(); if(e.key==='ArrowRight')homeLbNav(1); if(e.key==='ArrowLeft')homeLbNav(-1); });
        </script>

        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
            @foreach($galleryPhotos as $idx => $photo)
            <button onclick="openHomeLb({{ $idx }})"
                    class="group relative aspect-square overflow-hidden rounded-lg focus:outline-none focus:ring-2 focus:ring-brandGold-400">
                <img src="{{ \Storage::url($photo->thumbnail_path ?? $photo->file_path) }}"
                     alt="{{ $photo->alt_text ?? $photo->title ?? '' }}"
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                     loading="lazy">
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-300 flex items-center justify-center">
                    <svg class="w-7 h-7 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                </div>
            </button>
            @endforeach
        </div>

        {{-- Lightbox --}}
        <div id="hlb" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/95 p-4" onclick="if(event.target===this)closeHomeLb()">
            <button onclick="closeHomeLb()" class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            <span id="hlb-counter" class="absolute top-4 left-1/2 -translate-x-1/2 text-white/50 text-sm"></span>
            <button onclick="homeLbNav(-1)" class="absolute left-3 sm:left-6 w-11 h-11 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
            <div class="flex flex-col items-center max-w-4xl w-full">
                <img id="hlb-img" src="" alt="" class="max-h-[75vh] max-w-full object-contain rounded-lg transition-opacity duration-300"
                     ontouchstart="if(event.changedTouches)_homeLbStartX=event.changedTouches[0].clientX"
                     ontouchend="if(event.changedTouches){const dx=event.changedTouches[0].clientX-_homeLbStartX;if(dx>50)homeLbNav(-1);else if(dx<-50)homeLbNav(1);}">
                <p id="hlb-title" class="text-white font-semibold mt-3"></p>
                <p id="hlb-caption" class="text-white/50 text-sm mt-1"></p>
            </div>
            <button onclick="homeLbNav(1)" class="absolute right-3 sm:right-6 w-11 h-11 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     6. TESTIMONIALS CAROUSEL
══════════════════════════════════════════════════════════════ --}}
@if(isset($testimonials) && $testimonials->isNotEmpty())
<section class="py-14 sm:py-20 bg-white" x-data="{
    current: 0,
    total: {{ $testimonials->count() }},
    timer: null,
    start(){ this.timer = setInterval(()=>{ this.current=(this.current+1)%this.total; },4500); },
    go(i){ this.current=i; clearInterval(this.timer); this.start(); }
}" x-init="start()">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <span class="text-brandMaroon-600 font-bold text-xs uppercase tracking-widest">Student voices</span>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-1">What Our Students Say</h2>
        </div>

        <div class="relative max-w-2xl mx-auto">
            @foreach($testimonials as $idx => $t)
            <div x-show="current === {{ $idx }}"
                 x-transition:enter="transition duration-500"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="bg-brandBeige-200 border border-brandBeige-300 rounded-2xl p-8 text-center relative">
                <div class="text-brandGold-400 text-6xl font-serif absolute -top-2 left-6 leading-none opacity-30">"</div>
                <p class="text-gray-700 text-lg leading-relaxed mb-6 relative">"{{ $t->quote }}"</p>
                <div class="flex items-center justify-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-brandMaroon-600 flex items-center justify-center text-white font-bold shrink-0 text-lg">
                        {{ strtoupper(substr($t->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-gray-900">{{ $t->name }}</p>
                        @if($t->role)<p class="text-xs text-gray-500">{{ $t->role }}</p>@endif
                    </div>
                </div>
            </div>
            @endforeach

            {{-- Dots --}}
            <div class="flex justify-center gap-2 mt-6">
                @foreach($testimonials as $idx => $t)
                <button @click="go({{ $idx }})"
                        :class="current === {{ $idx }} ? 'w-5 bg-brandMaroon-600' : 'w-2 bg-gray-300 hover:bg-gray-400'"
                        class="h-2 rounded-full transition-all duration-300"></button>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     7. NEWS & EVENTS
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 bg-brandBeige-200 border-t border-brandBeige-300">
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
                       class="flex gap-4 p-4 bg-white rounded-xl hover:shadow-md border border-gray-100 hover:border-brandMaroon-200 transition-all group">
                        <div class="w-14 h-14 rounded-lg bg-brandMaroon-100 shrink-0 flex items-center justify-center text-brandMaroon-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2"/></svg>
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
                    <a href="{{ route('public.events.index') }}" class="text-sm text-brandGold-700 font-semibold hover:underline">All events →</a>
                </div>
                <div class="space-y-3">
                    @forelse($events as $event)
                    @php $evSlug = $event->slug ?? $event->id ?? null; @endphp
                    <a href="{{ $evSlug ? route('public.events.show', $evSlug) : route('public.events.index') }}"
                       class="flex gap-4 p-4 bg-white rounded-xl hover:shadow-md border border-gray-100 hover:border-brandGold-300 transition-all group">
                        @php $evDate = \Carbon\Carbon::parse($event->start_date ?? now()); @endphp
                        <div class="shrink-0 w-13 text-center">
                            <div class="bg-brandMaroon-600 text-white rounded-t-lg py-0.5 text-xs font-bold uppercase px-2">{{ $evDate->format('M') }}</div>
                            <div class="border border-t-0 border-gray-200 rounded-b-lg text-xl font-bold text-gray-900 py-1 px-2">{{ $evDate->format('d') }}</div>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 group-hover:text-brandMaroon-700 transition-colors text-sm">{{ $event->title }}</p>
                            <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
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
     8. UPCOMING INTAKES
══════════════════════════════════════════════════════════════ --}}
@php
    $upcomingCourses = $courses->filter(fn($c)=>is_object($c)&&method_exists($c,'getAttribute')&&in_array($c->status??'',['open','upcoming'])&&!empty($c->start_date));
@endphp
@if($upcomingCourses->count() > 0)
<section class="py-10 bg-brandGold-50 border-t border-brandGold-200">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-bold text-gray-900">📅 Upcoming Intakes</h2>
            <a href="{{ route('public.courses.index') }}" class="text-sm text-brandMaroon-600 font-semibold hover:underline">View all →</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($upcomingCourses->take(6) as $uc)
            @php $ucDate = \Carbon\Carbon::parse($uc->start_date); @endphp
            <a href="{{ $uc->slug ? route('public.courses.show', $uc->slug) : route('public.courses.index') }}"
               class="flex items-center gap-4 bg-white rounded-xl px-4 py-3 border border-brandGold-200 hover:border-brandMaroon-300 hover:shadow-sm transition-all">
                <div class="text-center shrink-0">
                    <div class="bg-brandMaroon-600 text-white rounded-t text-xs font-bold uppercase px-2 py-0.5">{{ $ucDate->format('M') }}</div>
                    <div class="border border-t-0 border-gray-200 rounded-b text-lg font-bold text-gray-900 px-2 py-0.5">{{ $ucDate->format('d') }}</div>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">{{ $uc->title }}</p>
                    <p class="text-xs {{ $uc->fee > 0 ? 'text-brandMaroon-600' : 'text-green-600' }} font-medium">{{ $uc->fee > 0 ? number_format($uc->fee,0).' MVR' : 'Free' }}</p>
                </div>
                <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded-full {{ $uc->status==='open' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ ucfirst($uc->status) }}</span>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════════
     9. EDUCATIONAL ARTICLES
══════════════════════════════════════════════════════════════ --}}
@if(isset($articles) && $articles->count() > 0)
<section class="py-14 bg-white border-t border-gray-100">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <span class="text-brandMaroon-600 font-bold text-xs uppercase tracking-widest">Learn & grow</span>
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
     10. LOCATION + CONTACT
══════════════════════════════════════════════════════════════ --}}
<section class="py-14 bg-brandMaroon-800 text-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <span class="text-brandGold-400 font-bold text-xs uppercase tracking-widest">Find us</span>
            <h2 class="text-3xl font-bold text-white mt-1">Visit Akuru Institute</h2>
        </div>
        <div class="grid lg:grid-cols-2 gap-8 items-center max-w-5xl mx-auto">
            {{-- Map embed --}}
            <div class="rounded-2xl overflow-hidden shadow-xl h-64 lg:h-80 bg-brandMaroon-700">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15882.05!2d73.5093!3d4.1755!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3b3f7f3f3f3f3f3f%3A0x0!2sMal%C3%A9%2C+Maldives!5e0!3m2!1sen!2smv!4v1234567890"
                    width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" title="Akuru Institute Location">
                </iframe>
            </div>
            {{-- Contact cards --}}
            <div class="space-y-4">
                <div class="flex items-start gap-4 bg-white/10 rounded-xl p-4">
                    <div class="w-10 h-10 bg-brandGold-500 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-brandMaroon-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Address</p>
                        <p class="text-white/70 text-sm">{{ $siteSettings['address'] ?? 'Malé, Republic of Maldives' }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 bg-white/10 rounded-xl p-4">
                    <div class="w-10 h-10 bg-brandGold-500 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-brandMaroon-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Phone</p>
                        <a href="tel:{{ $siteSettings['phone'] ?? '+9607972434' }}" class="text-white/70 hover:text-white text-sm transition-colors">{{ $siteSettings['phone'] ?? '+960 797 2434' }}</a>
                    </div>
                </div>
                <div class="flex items-start gap-4 bg-white/10 rounded-xl p-4">
                    <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-white text-sm">Viber</p>
                        <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to know about Akuru Institute.') }}"
                           class="text-white/70 hover:text-white text-sm transition-colors">
                            Chat with us on Viber
                        </a>
                    </div>
                </div>
                <a href="{{ route('public.contact.create') }}"
                   class="flex items-center justify-center gap-2 bg-brandGold-500 hover:bg-brandGold-400 text-brandMaroon-900 font-bold px-6 py-3 rounded-xl transition-all hover:scale-105 w-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Send us a Message
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     11. FINAL CTA
══════════════════════════════════════════════════════════════ --}}
<section class="bg-gradient-to-r from-brandMaroon-700 to-brandMaroon-900 text-white py-14 sm:py-16">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold mb-3 drop-shadow-lg">Ready to Start Your Journey?</h2>
        <p class="text-white/75 text-lg mb-8 max-w-xl mx-auto">Join hundreds of students who chose Akuru Institute for their Islamic education.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('public.courses.index') }}"
               class="inline-flex items-center justify-center gap-2 bg-brandGold-500 hover:bg-brandGold-400 text-brandMaroon-900 font-bold px-8 py-4 rounded-xl text-lg shadow-lg transition-all hover:scale-105">
                {{ __('public.Enroll') }}
            </a>
            <a href="{{ route('public.contact.create') }}"
               class="inline-flex items-center justify-center gap-2 border-2 border-white/40 hover:bg-white/10 font-semibold px-8 py-4 rounded-xl text-lg transition-all">
                {{ __('public.Contact Us') }}
            </a>
        </div>
    </div>
</section>

@endsection
