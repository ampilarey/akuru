@extends('public.layouts.public')

@section('title', $course->title)
@section('description', $course->short_desc)

@push('scripts')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@type": "Course",
  "name": {{ json_encode($course->title) }},
  "description": {{ json_encode($course->short_desc ?? '') }},
  "provider": {"@type": "Organization", "name": "Akuru Institute", "url": "{{ config('app.url') }}"},
  "offers": {"@type": "Offer", "price": "{{ $course->fee ?? 0 }}", "priceCurrency": "MVR"}
}
</script>
@endpush

@section('content')
<!-- Course Header -->
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-12">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-8 items-center">
            <!-- Course Info -->
            <div>
                @if($course->category)
                    <span class="inline-block px-3 py-1 text-sm font-medium bg-brandMaroon-600 text-white rounded-full mb-4">
                        {{ $course->category->name }}
                    </span>
                @endif

                <h1 class="text-4xl lg:text-5xl font-bold text-brandMaroon-900 mb-4">{{ $course->title }}</h1>
                <p class="text-xl text-brandGray-700 mb-6">{{ $course->short_desc }}</p>

                <!-- Course Meta -->
                <div class="flex flex-wrap gap-4 mb-6">
                    <div class="flex items-center gap-2 text-gray-700 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
                        <span>@if($course->language==='en') English @elseif($course->language==='ar') العربية @elseif($course->language==='dv') ދިވެހި @else Mixed @endif</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-700 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span class="capitalize">{{ $course->level }}</span>
                    </div>
                    <span class="flex items-center gap-2 text-sm font-medium px-3 py-1 rounded-full
                        {{ $course->status === 'open' ? 'bg-green-100 text-green-800' : ($course->status === 'upcoming' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                        <span class="w-2 h-2 rounded-full {{ $course->status === 'open' ? 'bg-green-500' : ($course->status === 'upcoming' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                        {{ ucfirst($course->status) }}
                    </span>
                    @if($course->available_seats !== null && $course->available_seats <= 5 && $course->available_seats > 0)
                        <span class="flex items-center gap-1 text-sm font-bold px-3 py-1 rounded-full bg-red-100 text-red-700">
                            ⚠ Only {{ $course->available_seats }} seat{{ $course->available_seats == 1 ? '' : 's' }} left
                        </span>
                    @endif
                </div>

                <!-- Fee -->
                @if($course->fee)
                    <div class="mb-6">
                        <div class="text-3xl font-bold text-brandMaroon-600">{{ number_format($course->fee, 2) }} MVR</div>
                        @if($course->start_date)
                            <p class="text-sm text-gray-500 mt-1">Starts {{ \Carbon\Carbon::parse($course->start_date)->format('d M Y') }}</p>
                        @endif
                    </div>
                @endif

                <!-- CTA -->
                <div class="space-y-3">
                    @if($course->status !== 'open')
                        <div class="inline-flex items-center gap-2 px-6 py-3 bg-gray-100 text-gray-500 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $course->status === 'upcoming' ? 'Enrollment opening soon' : 'Enrollment closed' }}
                        </div>
                    @elseif($course->isFull())
                        <div class="inline-flex items-center gap-2 px-6 py-3 bg-red-50 text-red-700 border border-red-200 rounded-lg font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            Fully booked — no seats available
                        </div>
                        <a href="{{ route('public.admissions.create', [app()->getLocale(), 'course' => $course->id]) }}"
                           class="text-sm text-brandMaroon-600 hover:underline">
                            Submit an inquiry to join the waitlist →
                        </a>
                    @else
                        <a href="{{ route('courses.checkout.show', $course) }}"
                           class="btn-primary inline-flex items-center px-8 py-4 text-lg">
                            {{ __('public.Enroll in this course') }}
                            @if($course->hasRegistrationFee())
                                <span class="ml-2">({{ number_format($course->getRegistrationFeeAmount(), 2) }} MVR)</span>
                            @endif
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="{{ route('public.admissions.create', [app()->getLocale(), 'course' => $course->id]) }}"
                           class="text-sm text-gray-500 hover:text-brandMaroon-600 block">
                            Or submit an inquiry →
                        </a>
                    @endif
                </div>
            </div>

            <!-- Course Image -->
            <div>
                @if($course->cover_image)
                    <x-public.picture :src="$course->cover_image" :alt="$course->title" class="rounded-2xl shadow-xl w-full"/>
                @else
                    <div class="aspect-[4/3] bg-gradient-to-br from-brandBeige-200 to-brandGold-300 rounded-2xl shadow-xl flex items-center justify-center">
                        <svg class="w-32 h-32 text-brandGold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<!-- Course Details -->
<section class="py-12">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Description -->
                <div class="card p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Course Description</h2>
                    <div class="prose max-w-none text-gray-700 leading-relaxed">
                        {!! $course->body !!}
                    </div>
                </div>

                <!-- Instructors -->
                @if($course->instructors->count() > 0)
                <div class="card p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Your Instructors</h2>
                    <div class="space-y-5">
                        @foreach($course->instructors as $instructor)
                        <div class="flex items-start gap-4 p-4 bg-brandBeige-50 rounded-xl">
                            @if($instructor->photo)
                                <img src="{{ asset('storage/'.$instructor->photo) }}" alt="{{ $instructor->name }}" class="w-16 h-16 rounded-full object-cover shrink-0 ring-2 ring-brandGold-300">
                            @else
                                <div class="w-16 h-16 rounded-full bg-brandMaroon-100 flex items-center justify-center shrink-0 text-brandMaroon-700 font-bold text-2xl ring-2 ring-brandMaroon-200">
                                    {{ strtoupper(substr($instructor->name, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <p class="font-bold text-gray-900">{{ $instructor->name }}</p>
                                @if($instructor->qualification)
                                    <p class="text-sm text-brandMaroon-600 font-medium">{{ $instructor->qualification }}</p>
                                @endif
                                @if($instructor->bio)
                                    <p class="text-sm text-gray-600 mt-1 leading-relaxed">{{ Str::limit($instructor->bio, 200) }}</p>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Schedule -->
                @if($course->schedule && is_array($course->schedule) && count($course->schedule) > 0)
                <div class="card p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Class Schedule</h2>
                    <div class="space-y-2">
                        @foreach($course->schedule as $item)
                        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                            <svg class="w-5 h-5 text-brandMaroon-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-gray-700">{{ $item }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- FAQ Accordion -->
                <div class="card p-6" x-data="{open: null}">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Frequently Asked Questions</h2>
                    <div class="space-y-2">
                        @foreach([
                            ['q'=>'Who is this course for?', 'a'=>'This course is open to anyone interested in Islamic education — students, adults, and professionals. No prior knowledge is required for beginner levels.'],
                            ['q'=>'How do I enroll?', 'a'=>'Click the "Enroll in this course" button above to start the enrollment process. You will need to verify your mobile number via OTP and complete a short registration form.'],
                            ['q'=>'What is the payment method?', 'a'=>'We accept payments online via BML Internet Banking and debit/credit cards. All payments are processed securely through the Bank of Maldives payment portal.'],
                            ['q'=>'Can I get a refund if I cannot attend?', 'a'=>'Please review our refund policy on the website or contact us directly for guidance specific to your situation.'],
                            ['q'=>'Will I receive a certificate?', 'a'=>'Yes, students who successfully complete the course requirements will receive a certificate of completion from Akuru Institute.'],
                            ['q'=>'Are classes online or in-person?', 'a'=>'We offer both online and in-person sessions depending on the course. Check the schedule section above or contact us for details on this course.'],
                        ] as $i => $faq)
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <button @click="open = (open === {{ $i }}) ? null : {{ $i }}"
                                    class="w-full flex items-center justify-between p-4 text-left font-semibold text-gray-900 hover:bg-brandBeige-50 transition-colors">
                                <span>{{ $faq['q'] }}</span>
                                <svg class="w-5 h-5 text-brandMaroon-600 shrink-0 transition-transform duration-300" :class="open === {{ $i }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open === {{ $i }}" x-collapse class="px-4 pb-4 text-gray-600 text-sm leading-relaxed border-t border-gray-100">
                                <p class="pt-3">{{ $faq['a'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <p class="text-sm text-gray-400 mt-4">Have more questions? <a href="{{ route('public.contact.create') }}" class="text-brandMaroon-600 hover:underline">Contact us</a></p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Quick Info Card -->
                <div class="card p-6 mb-6 sticky top-24">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Course Information</h3>
                    <dl class="space-y-3 mb-6">
                        <div>
                            <dt class="text-xs font-medium text-gray-400 uppercase tracking-wider">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium
                                    {{ $course->status === 'open' ? 'bg-green-100 text-green-800' : ($course->status === 'upcoming' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    <span class="w-2 h-2 rounded-full {{ $course->status === 'open' ? 'bg-green-500' : ($course->status === 'upcoming' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                                    {{ ucfirst($course->status) }}
                                </span>
                            </dd>
                        </div>
                        @if($course->start_date)
                        <div>
                            <dt class="text-xs font-medium text-gray-400 uppercase tracking-wider">Start Date</dt>
                            <dd class="mt-1 text-gray-900 font-semibold">{{ \Carbon\Carbon::parse($course->start_date)->format('d M Y') }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium text-gray-400 uppercase tracking-wider">Language</dt>
                            <dd class="mt-1 text-gray-900 font-medium">
                                @if($course->language==='en') English @elseif($course->language==='ar') العربية @elseif($course->language==='dv') ދިވެހި @else Mixed @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-400 uppercase tracking-wider">Level</dt>
                            <dd class="mt-1 text-gray-900 font-medium capitalize">{{ $course->level }}</dd>
                        </div>
                        @if($course->fee)
                        <div>
                            <dt class="text-xs font-medium text-gray-400 uppercase tracking-wider">Course Fee</dt>
                            <dd class="mt-1 text-2xl font-bold text-brandMaroon-600">{{ number_format($course->fee, 2) }} MVR</dd>
                        </div>
                        @endif
                        @if($course->seats)
                        <div>
                            <dt class="text-xs font-medium text-gray-400 uppercase tracking-wider">Available Seats</dt>
                            <dd class="mt-1 text-gray-900 font-medium">
                                @if($course->available_seats !== null && $course->available_seats <= 5 && $course->available_seats > 0)
                                    <span class="text-red-600 font-bold">⚠ {{ $course->available_seats }} left</span>
                                @elseif($course->available_seats === 0)
                                    <span class="text-red-500">Fully booked</span>
                                @else
                                    {{ $course->seats }} seats
                                @endif
                            </dd>
                        </div>
                        @endif
                    </dl>

                    {{-- CTA inside sidebar card --}}
                    @if($course->status !== 'open')
                        <span class="block w-full text-center py-3 px-4 rounded-xl bg-gray-100 text-gray-500 text-sm font-medium">
                            {{ $course->status === 'upcoming' ? 'Opening soon' : 'Enrollment closed' }}
                        </span>
                    @elseif($course->isFull())
                        <span class="block w-full text-center py-3 px-4 rounded-xl bg-red-100 text-red-700 text-sm font-semibold">
                            Fully booked
                        </span>
                        <a href="{{ route('public.admissions.create', [app()->getLocale(), 'course' => $course->id]) }}" class="text-xs text-center block mt-3 text-brandMaroon-600 hover:underline">Join waitlist →</a>
                    @else
                        @if($course->available_seats !== null && $course->available_seats <= 5)
                            <p class="text-sm text-amber-600 font-medium mb-3 text-center">⚠ Only {{ $course->available_seats }} seat{{ $course->available_seats == 1 ? '' : 's' }} left</p>
                        @endif
                        <a href="{{ route('courses.checkout.show', $course) }}" class="btn-primary w-full text-center block">
                            Enroll Now
                        </a>
                        <a href="{{ route('public.admissions.create', [app()->getLocale(), 'course' => $course->id]) }}" class="text-xs text-center block mt-3 text-gray-400 hover:text-brandMaroon-600">Or submit an inquiry</a>
                    @endif

                    <div class="mt-5 pt-5 border-t border-gray-100">
                        <p class="text-xs text-gray-400 text-center mb-3">Have questions? Chat with us on Viber.</p>
                        <a href="viber://chat?number=%2B{{ $siteSettings['viber'] ?? '9607972434' }}&text={{ urlencode('Assalaamu alaikum, I want to apply for '.$course->title.'. Please send me more details.') }}"
                           class="flex items-center justify-center gap-2 w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 rounded-xl text-sm transition-colors mb-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.993 0C5.5 0 .527 4.972.527 11.473c0 3.107 1.2 5.943 3.17 8.053V23l2.953-1.628A11.03 11.03 0 0011.993 22.736c6.457 0 11.43-4.972 11.43-11.472C23.459 4.813 18.487 0 11.993 0z"/></svg>
                            Chat on Viber
                        </a>
                        <a href="{{ route('public.contact.create') }}" class="btn-secondary w-full text-center block text-sm">Send a Message</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Related Courses -->
@if(isset($relatedCourses) && $relatedCourses->count() > 0)
<section class="py-12 bg-brandBeige-50 border-t border-brandBeige-200">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Related Courses</h2>
            <a href="{{ route('public.courses.index') }}" class="text-sm text-brandMaroon-600 hover:underline font-medium">View all →</a>
        </div>
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-6">
            @foreach($relatedCourses as $rc)
            <a href="{{ route('public.courses.show', $rc->slug) }}"
               class="group block bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg border border-gray-100 hover:-translate-y-1 transition-all duration-300">
                <div class="h-40 bg-gradient-to-br from-brandBeige-200 to-brandGold-200 overflow-hidden">
                    @if($rc->cover_image)
                        <img src="{{ asset('storage/'.$rc->cover_image) }}" alt="{{ $rc->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @endif
                </div>
                <div class="p-4">
                    <span class="text-xs font-bold {{ $rc->status === 'open' ? 'text-green-700' : 'text-amber-700' }}">{{ ucfirst($rc->status) }}</span>
                    <h3 class="font-bold text-gray-900 mt-1 group-hover:text-brandMaroon-700 transition-colors">{{ $rc->title }}</h3>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                        <span class="text-sm text-gray-500">{{ $rc->level ? ucfirst($rc->level) : '' }}</span>
                        @if($rc->fee > 0)
                            <span class="text-sm font-bold text-brandMaroon-700">{{ number_format($rc->fee, 0) }} MVR</span>
                        @else
                            <span class="text-sm font-bold text-green-600">Free</span>
                        @endif
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@else
<section class="py-10 bg-brandBeige-50 border-t border-brandBeige-200 text-center">
    <p class="text-gray-500">
        Explore <a href="{{ route('public.courses.index') }}" class="text-brandMaroon-600 hover:underline font-semibold">all our courses</a>
    </p>
</section>
@endif

{{-- ── Sticky mobile enroll bar ─────────────────────────────────── --}}
@if($course->status === 'open' && !$course->isFull())
<div class="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-white border-t border-gray-200 shadow-2xl px-4 py-3 flex items-center gap-3">
    <div class="flex-1 min-w-0">
        <p class="text-xs text-gray-500 truncate">{{ $course->title }}</p>
        @if($course->fee > 0)
            <p class="font-bold text-brandMaroon-700 text-sm leading-none">{{ number_format($course->fee, 2) }} MVR</p>
        @else
            <p class="font-bold text-green-600 text-sm leading-none">Free</p>
        @endif
    </div>
    <a href="{{ route('courses.checkout.show', $course) }}"
       class="shrink-0 bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white font-bold px-6 py-3 rounded-xl text-sm transition-colors shadow-lg">
        Enroll Now
        @if($course->available_seats !== null && $course->available_seats <= 5)
            · {{ $course->available_seats }} left
        @endif
    </a>
</div>
{{-- Spacer so content isn't hidden behind sticky bar on mobile --}}
<div class="h-20 lg:hidden"></div>
@elseif($course->status === 'upcoming')
<div class="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-amber-50 border-t border-amber-200 px-4 py-3 flex items-center justify-between gap-3">
    <div>
        <p class="text-xs text-amber-600 font-semibold">Enrollment opening soon</p>
        <p class="text-sm font-bold text-gray-900 truncate">{{ $course->title }}</p>
    </div>
    <a href="{{ route('public.admissions.create', [app()->getLocale(), 'course' => $course->id]) }}"
       class="shrink-0 bg-amber-500 hover:bg-amber-600 text-white font-bold px-5 py-3 rounded-xl text-sm transition-colors">
        Notify Me
    </a>
</div>
<div class="h-20 lg:hidden"></div>
@endif

@endsection
