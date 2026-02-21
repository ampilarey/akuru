@extends('public.layouts.public')

@section('title', 'About Us — Akuru Institute')
@section('description', 'Learn about Akuru Institute — our mission, values, and the team behind quality Islamic education in the Maldives.')

@section('content')

{{-- Hero --}}
<section class="bg-gradient-to-br from-brandMaroon-800 to-brandMaroon-900 text-white py-16">
    <div class="container mx-auto px-4 text-center">
        <span class="inline-block bg-white/10 border border-white/20 text-white/80 text-sm font-semibold px-4 py-1.5 rounded-full mb-4">Est. 2020</span>
        <h1 class="text-4xl sm:text-5xl font-bold mb-4">About Akuru Institute</h1>
        <p class="text-white/75 text-lg max-w-2xl mx-auto leading-relaxed">
            Nurturing hearts and minds through authentic Islamic education in the Maldives.
        </p>
    </div>
</section>

{{-- Animated Stats --}}
<section class="py-14 bg-white" x-data="{counted: false}" x-intersect.once="counted = true">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-8 max-w-4xl mx-auto text-center">
            @foreach([
                ['val' => $stats['students'], 'suffix' => '+', 'label' => 'Students enrolled', 'color' => 'text-brandMaroon-700'],
                ['val' => $stats['courses'],  'suffix' => '',  'label' => 'Courses offered',   'color' => 'text-brandGold-600'],
                ['val' => $stats['teachers'], 'suffix' => '+', 'label' => 'Expert teachers',   'color' => 'text-brandMaroon-700'],
                ['val' => $stats['years'],    'suffix' => '+', 'label' => 'Years of service',  'color' => 'text-brandGold-600'],
            ] as $s)
            <div>
                <div class="{{ $s['color'] }} text-5xl font-bold tabular-nums"
                     x-data="{ display: 0 }"
                     x-effect="if(counted) { let t=0, d={{ (int)$s['val'] }}, n=Math.ceil(d/40); let iv=setInterval(()=>{t=Math.min(t+n,d);display=t;if(t>=d)clearInterval(iv);},30); }"
                     x-text="display + '{{ $s['suffix'] }}'">0</div>
                <div class="text-gray-500 mt-1 text-sm">{{ $s['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CMS Body (if page exists) --}}
@if($page && $page->body)
<section class="py-12 bg-brandBeige-50 border-t border-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto prose prose-lg prose-headings:text-brandMaroon-800 prose-a:text-brandMaroon-600">
            {!! nl2br(e($page->body)) !!}
        </div>
    </div>
</section>
@else
{{-- Default about content if no CMS page --}}
<section class="py-12 bg-brandBeige-50 border-t border-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-12 items-center max-w-5xl mx-auto">
            <div>
                <h2 class="text-3xl font-bold text-brandMaroon-900 mb-4">Our Mission</h2>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Akuru Institute was founded with a simple but powerful mission: to make quality Islamic education accessible to every Maldivian — regardless of age, background, or schedule.
                </p>
                <p class="text-gray-600 leading-relaxed mb-4">
                    We offer structured, engaging courses in Quran recitation and memorisation, Arabic language, and Islamic Studies — taught by qualified instructors who are passionate about education.
                </p>
                <p class="text-gray-600 leading-relaxed">
                    With classes for children, teenagers, and adults in the morning, evening, and on weekends, we make it easy to learn at your own pace.
                </p>
            </div>
            <div class="space-y-4">
                @foreach([
                    ['title'=>'Our Vision', 'text'=>'A Maldives where every family has access to authentic, structured Islamic education.'],
                    ['title'=>'Our Values', 'text'=>'Excellence, accessibility, integrity, and a genuine love for the Deen guide everything we do.'],
                    ['title'=>'Our Commitment', 'text'=>'We are committed to continuous improvement, qualified teaching staff, and students who truly benefit.'],
                ] as $v)
                <div class="bg-white border border-brandBeige-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="font-bold text-brandMaroon-800 mb-2">{{ $v['title'] }}</h3>
                    <p class="text-sm text-gray-600">{{ $v['text'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- Team / Instructors --}}
@if($instructors->count() > 0)
<section class="py-14 bg-white border-t border-gray-100">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <span class="text-brandMaroon-600 font-semibold text-sm uppercase tracking-wider">Meet our team</span>
            <h2 class="text-3xl font-bold text-gray-900 mt-1">Our Instructors</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
            @foreach($instructors as $instructor)
            <div class="text-center group">
                <div class="w-24 h-24 mx-auto mb-4 rounded-full overflow-hidden ring-4 ring-brandBeige-200 group-hover:ring-brandMaroon-300 transition-all">
                    @if($instructor->photo)
                        <img src="{{ asset('storage/'.$instructor->photo) }}" alt="{{ $instructor->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full bg-brandMaroon-100 flex items-center justify-center text-brandMaroon-700 font-bold text-2xl">
                            {{ strtoupper(substr($instructor->name, 0, 1)) }}
                        </div>
                    @endif
                </div>
                <p class="font-bold text-gray-900">{{ $instructor->name }}</p>
                @if($instructor->qualification)
                    <p class="text-sm text-brandMaroon-600">{{ $instructor->qualification }}</p>
                @endif
                @if($instructor->specialization)
                    <p class="text-xs text-gray-400 mt-0.5">{{ $instructor->specialization }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Testimonials --}}
@if($testimonials->count() > 0)
<section class="py-14 bg-brandBeige-50 border-t border-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="text-center mb-10">
            <span class="text-brandMaroon-600 font-semibold text-sm uppercase tracking-wider">What our students say</span>
            <h2 class="text-3xl font-bold text-gray-900 mt-1">Student Testimonials</h2>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
            @foreach($testimonials as $t)
            <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm relative">
                <div class="text-5xl text-brandGold-300 font-serif absolute top-4 right-5 leading-none opacity-50">"</div>
                <p class="text-gray-700 leading-relaxed mb-5 relative">"{{ $t->quote }}"</p>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-brandMaroon-100 flex items-center justify-center text-brandMaroon-700 font-bold shrink-0">
                        {{ strtoupper(substr($t->name ?? 'A', 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 text-sm">{{ $t->name }}</p>
                        @if($t->role)<p class="text-xs text-gray-400">{{ $t->role }}</p>@endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- CTA --}}
<section class="py-14 bg-brandMaroon-700 text-white text-center">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-3">Join Our Community</h2>
        <p class="text-white/70 mb-8 max-w-xl mx-auto">Hundreds of students have already begun their journey. Start yours today.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('public.admissions.create') }}" class="inline-flex items-center justify-center gap-2 bg-brandGold-500 hover:bg-brandGold-400 text-brandMaroon-900 font-bold px-8 py-4 rounded-xl text-lg shadow transition-all hover:scale-105">
                Apply Now
            </a>
            <a href="{{ route('public.courses.index') }}" class="inline-flex items-center justify-center gap-2 border-2 border-white/40 hover:bg-white/10 font-semibold px-8 py-4 rounded-xl text-lg transition-all">
                View Courses
            </a>
        </div>
    </div>
</section>

@endsection
