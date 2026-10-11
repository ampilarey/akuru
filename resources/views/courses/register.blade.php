@extends('public.layouts.public')

@section('title', __('register.title', ['course' => $course->title]))

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-2">{{ __('register.title', ['course' => $course->title]) }}</h1>

            @if(!empty($closed))
                {{-- Enrolment is closed (the course's status, or its deadline): no form to start (slice RG1a). --}}
                <div class="mb-4 p-4 bg-amber-50 border border-amber-300 rounded-lg text-sm" data-testid="enrolment-closed">
                    <p class="font-semibold text-amber-800 mb-1">{{ __('register.closed_title') }}</p>
                    <p class="text-amber-700">{{ __('register.closed_body') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('public.courses.index') }}" class="btn-primary">{{ __('register.closed_courses') }}</a>
                    <a href="{{ route('public.contact.create') }}" class="btn-secondary">{{ __('register.closed_contact') }}</a>
                </div>
            @else
            <p class="text-gray-600 mb-6">{{ __('register.contact_intro') }}</p>

            {{-- The layout says the thanks once; this page said it a second time (slice FL1). --}}
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('courses.register.start') }}">
                @csrf
                <input type="hidden" name="course_id" value="{{ $course->id }}">

                <div x-data="{ contactType: 'mobile' }">
                    <fieldset class="mb-4">
                        <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('register.contact_method') }}</legend>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="contact_type" value="mobile" x-model="contactType" checked class="rounded border-gray-300">
                                <span class="ms-2">{{ __('register.mobile') }}</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="contact_type" value="email" x-model="contactType" class="rounded border-gray-300">
                                <span class="ms-2">{{ __('register.contact_email') }}</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="mb-4" x-show="contactType === 'mobile'">
                        <label for="contact_value" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.mobile_number') }}</label>
                        <input id="contact_value" type="tel" name="contact_value" value="{{ old('contact_value') }}"
                            placeholder="960 123 4567" required dir="ltr"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500"
                            :disabled="contactType !== 'mobile'">
                        <p class="text-xs text-gray-500 mt-1">{{ __('register.country_code_hint') }}</p>
                    </div>
                    <div class="mb-4" x-show="contactType === 'email'" style="display: none;">
                        <label for="contact_value_email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.email_address') }}</label>
                        <input id="contact_value_email" type="email" name="contact_value" value="{{ old('contact_value') }}"
                            placeholder="you@example.com" dir="ltr"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500"
                            :disabled="contactType !== 'email'"
                            x-bind:required="contactType === 'email'">
                        <p class="text-xs text-gray-500 mt-1">{{ __('register.outside_hint') }}</p>
                    </div>

                    <p class="text-xs text-gray-500 mb-4" x-show="contactType === 'mobile'" x-transition>
                        <a href="#" @click.prevent="contactType = 'email'" class="text-brandMaroon-600 hover:underline">{{ __('register.use_email') }}</a>
                    </p>
                </div>

                @if($fee > 0)
                    <div class="mb-4 p-3 bg-amber-50 rounded">
                        {{ __('register.fee', ['amount' => __('register.money', ['amount' => number_format($fee, 2)])]) }}
                    </div>
                @endif

                <button type="submit" class="btn-primary w-full py-3">
                    {{ __('register.send_code') }}
                </button>
            </form>
            @endif
        </div>
    </div>
</section>
@endsection
