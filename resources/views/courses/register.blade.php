@extends('public.layouts.public')

@section('title', 'Register for ' . $course->title)

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-2">Register for {{ $course->title }}</h1>
            <p class="text-gray-600 mb-6">Enter your mobile number or email. We'll send a verification code.</p>

            @if(session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('courses.register.start', app()->getLocale()) }}">
                @csrf
                <input type="hidden" name="course_id" value="{{ $course->id }}">

                <div x-data="{ contactType: 'mobile' }">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact method</label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="contact_type" value="mobile" x-model="contactType" checked class="rounded border-gray-300">
                                <span class="ml-2">Mobile</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="contact_type" value="email" x-model="contactType" class="rounded border-gray-300">
                                <span class="ml-2">Email</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-4" x-show="contactType === 'mobile'">
                        <label for="contact_value" class="block text-sm font-medium text-gray-700 mb-1">Mobile number</label>
                        <input id="contact_value" type="tel" name="contact_value" value="{{ old('contact_value') }}"
                            placeholder="960 123 4567" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        <p class="text-xs text-gray-500 mt-1">Include country code (e.g. +960 or 960)</p>
                    </div>
                    <div class="mb-4" x-show="contactType === 'email'" style="display: none;">
                        <label for="contact_value_email" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                        <input id="contact_value_email" type="email" name="contact_value" value="{{ old('contact_value') }}"
                            placeholder="you@example.com"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500"
                            x-bind:required="contactType === 'email'">
                        <p class="text-xs text-gray-500 mt-1">For students outside Maldives</p>
                    </div>

                    <p class="text-xs text-gray-500 mb-4" x-show="contactType === 'mobile'" x-transition>
                        <a href="#" @click.prevent="contactType = 'email'" class="text-brandMaroon-600 hover:underline">Outside Maldives? Use email instead</a>
                    </p>
                </div>

                @if($fee > 0)
                    <div class="mb-4 p-3 bg-amber-50 rounded">
                        <span class="font-medium">Registration fee:</span> MVR {{ number_format($fee, 2) }}
                    </div>
                @endif

                <button type="submit" class="btn-primary w-full py-3">
                    Send verification code
                </button>
            </form>
        </div>
    </div>
</section>
@endsection
