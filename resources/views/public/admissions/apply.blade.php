@extends('public.layouts.public')

@section('title', __('public.Apply').' — '.__('public.Akuru Institute'))
@section('description', __('public.Express your interest in our courses. Quick, free, and no commitment required.'))

@section('content')

{{-- Hero --}}
<section class="bg-gradient-to-br from-brandMaroon-800 to-brandMaroon-900 text-white py-14">
    <div class="container mx-auto px-4 text-center">
        <span class="inline-block bg-white/10 border border-white/20 text-white/80 text-sm font-semibold px-4 py-1.5 rounded-full mb-4">{{ __('public.Free') }} • {{ __('public.No commitment') }}</span>
        <h1 class="text-4xl sm:text-5xl font-bold mb-3">{{ __('public.Apply to Akuru Institute') }}</h1>
        <p class="text-white/75 text-lg max-w-xl mx-auto">{{ __('public.Tell us which course you\'re interested in and we\'ll get in touch with all the details.') }}</p>
    </div>
</section>

{{-- Form + Side info --}}
<section class="py-14 bg-brandBeige-50">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-3 gap-10 max-w-5xl mx-auto">

            {{-- Form --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">{{ __('public.Your Details') }}</h2>

                    @if($errors->any())
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                    @endif

                    <form method="POST" action="{{ route('public.apply.store') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="apply-full-name" class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('public.Full Name') }} <span class="text-red-500">*</span></label>
                            <input type="text" id="apply-full-name" name="full_name" value="{{ old('full_name') }}" required
                                   placeholder="{{ __('public.Your full name') }}"
                                   class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brandMaroon-300 focus:border-transparent @error('full_name') border-red-400 @enderror">
                        </div>

                        <div>
                            <label for="apply-phone" class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('public.Mobile Number') }} <span class="text-red-500">*</span></label>
                            <input type="tel" id="apply-phone" name="phone" value="{{ old('phone') }}" required
                                   placeholder="7xxxxxxx"
                                   class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brandMaroon-300 @error('phone') border-red-400 @enderror">
                        </div>

                        <div>
                            <label for="apply-email" class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('public.Email Address') }}</label>
                            <input type="email" id="apply-email" name="email" value="{{ old('email') }}"
                                   placeholder="you@example.com"
                                   class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brandMaroon-300">
                        </div>

                        <div>
                            <label for="apply-course" class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('public.Course Interested In') }}</label>
                            <select id="apply-course" name="course_id" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brandMaroon-300 bg-white">
                                <option value="">-- {{ __('public.Select a course (optional)') }} --</option>
                                @foreach($courses as $c)
                                <option value="{{ $c->id }}" {{ (old('course_id') == $c->id || ($selectedCourse && $selectedCourse->id == $c->id)) ? 'selected' : '' }}>
                                    {{ $c->title }}
                                    @if($c->status === 'upcoming') ({{ __('public.Opening soon') }}) @endif
                                    @if($c->fee > 0) · {{ number_format($c->fee, 0) }} MVR @else · {{ __('public.Free') }} @endif
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="apply-message" class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('public.Message / Questions') }}</label>
                            <textarea id="apply-message" name="message" rows="3" placeholder="{{ __('public.Any questions or notes for us…') }}"
                                      class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brandMaroon-300 resize-none">{{ old('message') }}</textarea>
                        </div>

                        @include('public.admissions._custom-fields')

                        <input type="hidden" name="source" value="web">
                        {{-- Honeypot --}}
                        <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" aria-hidden="true" autocomplete="off"></div>

                        <button type="submit"
                                class="w-full bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white font-bold py-4 rounded-xl text-base transition-colors shadow-lg hover:shadow-xl">
                            {{ __('public.Submit Application') }}
                        </button>
                        <p class="text-xs text-gray-400 text-center">{{ __('public.We\'ll contact you within 1–2 business days.') }}</p>
                    </form>
                </div>
            </div>

            {{-- Side info --}}
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h3 class="font-bold text-gray-900 mb-4">{{ __('public.What happens next?') }}</h3>
                    <ol class="space-y-4">
                        @foreach([
                            ['n'=>'1', 'title'=>__('public.We review your application'), 'desc'=>__('public.Our team will review your details, usually within 1–2 business days.')],
                            ['n'=>'2', 'title'=>__('public.We contact you'), 'desc'=>__('public.We will reach out via mobile or email with the next steps and any info you need.')],
                            ['n'=>'3', 'title'=>__('public.Complete enrollment'), 'desc'=>__('public.Once confirmed, you complete a short online form and pay the course fee.')],
                        ] as $step)
                        <li class="flex gap-3">
                            <span class="w-7 h-7 rounded-full bg-brandMaroon-100 text-brandMaroon-700 font-bold text-sm flex items-center justify-center shrink-0">{{ $step['n'] }}</span>
                            <div>
                                <p class="font-semibold text-sm text-gray-900">{{ $step['title'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $step['desc'] }}</p>
                            </div>
                        </li>
                        @endforeach
                    </ol>
                </div>

                <div class="bg-brandMaroon-50 border border-brandMaroon-100 rounded-2xl p-6">
                    <h3 class="font-bold text-brandMaroon-900 mb-3">{{ __('public.Questions?') }}</h3>
                    <p class="text-sm text-gray-600 mb-4">{{ __('public.Our admissions team is happy to help.') }}</p>
                    <a href="{{ route('public.contact.create') }}" class="block w-full text-center bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white font-semibold text-sm py-3 rounded-xl transition-colors">
                        {{ __('public.Contact Us') }}
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

@endsection
