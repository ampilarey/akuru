@extends('public.layouts.public')

@section('title', __('register.title', ['course' => $course->title]))

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-lg">

        {{-- Course summary card --}}
        <div class="card p-5 mb-6 flex gap-4 items-start">
            @if($course->cover_image)
                <img src="{{ $course->cover_image }}" alt="{{ $course->title }}"
                     class="w-16 h-16 rounded-lg object-cover shrink-0">
            @endif
            <div>
                <h2 class="font-bold text-gray-900">{{ $course->title }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $course->category->name ?? '' }}</p>
                @if($fee > 0)
                    <p class="mt-1 text-sm font-semibold text-brandMaroon-700">
                        {{ __('register.fee', ['amount' => __('register.money', ['amount' => number_format($fee, 2)])]) }}
                    </p>
                @else
                    <p class="mt-1 text-sm text-green-700 font-medium">{{ __('register.free') }}</p>
                @endif
            </div>
        </div>

        <div class="card p-6" x-data="checkoutStart()" x-cloak>

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm" role="alert">{{ $errors->first() }}</div>
            @endif
            {{-- The layout says the thanks once; this page said it a second time (slice FL1). --}}
            @if(session('info'))
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 text-blue-800 rounded text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('info') }}
                </div>
            @endif

            {{-- Existing account notice --}}
            @if(session('existing_account'))
                <div class="mb-4 p-4 bg-amber-50 border border-amber-300 rounded-lg text-sm">
                    <p class="font-semibold text-amber-800 mb-1">{{ __('register.existing_title') }}</p>
                    <p class="text-amber-700">
                        {{ __('register.existing_body', ['contact' => session('existing_account')]) }}
                    </p>
                </div>
            @endif

            {{-- Tab switcher --}}
            <div class="flex border-b mb-6">
                <button type="button"
                        @click="tab = 'new'"
                        :class="tab === 'new' ? 'border-b-2 border-brandMaroon-600 text-brandMaroon-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="pb-3 pe-6 text-sm transition">
                    {{ __('register.tab_new') }}
                </button>
                <button type="button"
                        @click="tab = 'login'"
                        :class="tab === 'login' ? 'border-b-2 border-brandMaroon-600 text-brandMaroon-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="pb-3 px-6 text-sm transition">
                    {{ __('register.tab_login') }}
                </button>
            </div>

            {{-- ── NEW REGISTRATION TAB ── --}}
            <div x-show="tab === 'new'" x-cloak>
                <p class="text-sm text-gray-600 mb-5">{{ __('register.new_intro') }}</p>

                <form method="POST" action="{{ route('courses.register.start') }}">
                    @csrf
                    <input type="hidden" name="course_id" value="{{ $course->id }}">
                    <input type="hidden" name="flow_type" x-model="flowType">

                    <div x-data="{ contactType: @js(old('contact_type', 'mobile')) }">

                        {{-- ── Contact method ── --}}
                        <fieldset class="mb-4">
                            <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('register.contact_method') }} <span class="text-red-500">*</span></legend>
                            <div class="flex gap-4 mb-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="contact_type" value="mobile" x-model="contactType" {{ old('contact_type','mobile') === 'mobile' ? 'checked' : '' }}>
                                    <span class="text-sm">{{ __('register.contact_mobile_sms') }}</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="contact_type" value="email" x-model="contactType" {{ old('contact_type') === 'email' ? 'checked' : '' }}>
                                    <span class="text-sm">{{ __('register.contact_email') }}</span>
                                </label>
                            </div>

                            <div x-show="contactType === 'mobile'">
                                <input type="tel" name="contact_value" value="{{ old('contact_value') }}"
                                       placeholder="7654321" autocomplete="tel" dir="ltr" aria-label="{{ __('register.mobile_number') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500"
                                       :disabled="contactType !== 'mobile'">
                                <p class="text-xs text-gray-500 mt-1">{{ __('register.mobile_hint') }}</p>
                            </div>
                            <div x-show="contactType === 'email'" x-cloak>
                                <input type="email" name="contact_value" value="{{ old('contact_value') }}"
                                       placeholder="you@example.com" autocomplete="email" dir="ltr" aria-label="{{ __('register.email_address') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500"
                                       :disabled="contactType !== 'email'">
                                <p class="text-xs text-gray-500 mt-1">{{ __('register.email_hint') }}</p>
                            </div>
                        </fieldset>

                        {{-- ── Who is enrolling ── --}}
                        <div class="mb-5">
                            <p class="block text-sm font-medium text-gray-700 mb-2">{{ __('register.who') }} <span class="text-red-500">*</span></p>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <button type="button" @click="flowType = 'adult'"
                                        :class="flowType === 'adult' ? 'ring-2 ring-brandMaroon-500 bg-brandMaroon-50' : 'border border-gray-200'"
                                        :aria-pressed="flowType === 'adult'"
                                        class="p-3 rounded-lg text-start hover:bg-gray-50 transition" data-testid="flow-adult">
                                    <span class="font-medium text-sm block">{{ __('register.who_self') }}</span>
                                    <span class="text-xs text-gray-500">{{ __('register.who_self_hint') }}</span>
                                </button>
                                <button type="button" @click="flowType = 'parent'"
                                        :class="flowType === 'parent' ? 'ring-2 ring-brandMaroon-500 bg-brandMaroon-50' : 'border border-gray-200'"
                                        :aria-pressed="flowType === 'parent'"
                                        class="p-3 rounded-lg text-start hover:bg-gray-50 transition" data-testid="flow-parent">
                                    <span class="font-medium text-sm block">{{ __('register.who_parent') }}</span>
                                    <span class="text-xs text-gray-500">{{ __('register.who_parent_hint') }}</span>
                                </button>
                            </div>
                            <p x-show="!flowType" x-cloak class="text-xs text-red-500 mt-1">{{ __('register.who_required') }}</p>
                        </div>

                        <hr class="my-5 border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('register.your_details') }}</p>

                        {{-- C17 slice R2: fill these from a photo of the ID card, read on the device and never submitted. --}}
                        @include('courses.partials.id-scan', ['mode' => 'photo'])

                        {{-- ── Name: first, middle (optional), last — Maldivian names (C17 slice R4) ── --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <div>
                                <label for="co_first_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.first_name') }} <span class="text-red-500">*</span></label>
                                <input id="co_first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                                @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="co_middle_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('account.name_middle') }}</label>
                                <input id="co_middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}" data-testid="middle-name"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                                @error('middle_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="co_last_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.last_name') }} <span class="text-red-500">*</span></label>
                                <input id="co_last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                                @error('last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ── Gender + DOB ── --}}
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div>
                                <label for="co_gender" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.gender') }} <span class="text-red-500">*</span></label>
                                <select id="co_gender" name="gender" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500 bg-white">
                                    <option value="">{{ __('register.choose') }}</option>
                                    <option value="male"   {{ old('gender') === 'male'   ? 'selected' : '' }}>{{ __('register.male') }}</option>
                                    <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>{{ __('register.female') }}</option>
                                </select>
                                @error('gender')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="co_dob" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.dob') }} <span class="text-red-500">*</span></label>
                                <input id="co_dob" type="date" name="dob" value="{{ old('dob') }}" required max="{{ date('Y-m-d') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                                @error('dob')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ── ID Document ── --}}
                        <fieldset class="mb-4" x-data="{ idType: @js(old('id_type', 'national_id')) }">
                            <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('register.id_document') }} <span class="text-red-500">*</span></legend>
                            <div class="flex gap-3 mb-2">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="id_type" value="national_id" x-model="idType" {{ old('id_type','national_id') === 'national_id' ? 'checked' : '' }}>
                                    <span class="text-sm">{{ __('register.id_card') }}</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="id_type" value="passport" x-model="idType" {{ old('id_type') === 'passport' ? 'checked' : '' }}>
                                    <span class="text-sm">{{ __('register.passport') }}</span>
                                </label>
                            </div>
                            <div x-show="idType === 'national_id'">
                                <input type="text" name="national_id" value="{{ old('national_id') }}" placeholder="{{ __('register.id_card_example') }}" aria-label="{{ __('register.id_card_number') }}" dir="ltr"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500 uppercase"
                                       :disabled="idType !== 'national_id'">
                                @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div x-show="idType === 'passport'" x-cloak>
                                <input type="text" name="passport" value="{{ old('passport') }}" placeholder="{{ __('register.passport_number') }}" aria-label="{{ __('register.passport_number') }}" dir="ltr"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500 uppercase"
                                       :disabled="idType !== 'passport'">
                                @error('passport')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </fieldset>

                        {{-- ── Optional email (only shown when mobile is the primary contact) ── --}}
                        <div class="mb-4" x-show="contactType === 'mobile'">
                            <label for="co_email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.email_optional') }}</label>
                            <input id="co_email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" dir="ltr"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <hr class="my-5 border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('register.create_password') }}</p>

                        {{-- ── Password ── --}}
                        <div class="mb-3">
                            <label for="co_password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.password') }} <span class="text-red-500">*</span></label>
                            <input id="co_password" type="password" name="password" required placeholder="{{ __('register.password_hint') }}" autocomplete="new-password"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-6">
                            <label for="co_password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.confirm_password') }} <span class="text-red-500">*</span></label>
                            <input id="co_password_confirmation" type="password" name="password_confirmation" required placeholder="{{ __('register.confirm_hint') }}" autocomplete="new-password"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        </div>

                    </div>{{-- end x-data contactType --}}

                    <button type="submit"
                            :disabled="!flowType"
                            class="btn-primary w-full py-3 disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ __('register.send_code') }} <span class="rtl-flip" aria-hidden="true">→</span>
                    </button>
                    <p class="text-xs text-gray-400 text-center mt-2">{{ __('register.account_after_code') }}</p>
                </form>
            </div>

            {{-- ── RETURNING USER (LOGIN) TAB ── --}}
            <div x-show="tab === 'login'">

                {{-- Password login --}}
                <div x-show="!otpLogin">
                    <p class="text-sm text-gray-600 mb-5">{{ __('register.login_intro') }}</p>
                    <form method="POST" action="{{ route('courses.checkout.login', $course) }}">
                        @csrf
                        <div class="mb-4">
                            <label for="co_login_contact" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.mobile_or_email') }} <span class="text-red-500">*</span></label>
                            <input id="co_login_contact" type="text" name="login_contact" value="{{ old('login_contact') }}"
                                   placeholder="{{ __('register.mobile_or_email_example') }}" autocomplete="username" dir="ltr"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        </div>
                        <div class="mb-5">
                            <label for="co_login_password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.password') }} <span class="text-red-500">*</span></label>
                            <input id="co_login_password" type="password" name="password" autocomplete="current-password"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        </div>
                        <button type="submit" class="btn-primary w-full py-3">{{ __('register.log_in') }}</button>
                        <p class="text-center text-sm text-gray-500 mt-4">
                            <a href="{{ route('password.otp.request') }}" class="text-brandMaroon-600 hover:underline">{{ __('register.forgot_password') }}</a>
                            <span class="mx-1" aria-hidden="true">·</span>
                            <a href="#" @click.prevent="otpLogin = true" class="text-brandMaroon-600 hover:underline">{{ __('register.use_code') }}</a>
                        </p>
                    </form>
                </div>

                {{-- OTP login fallback --}}
                <div x-show="otpLogin">
                    <p class="text-sm text-gray-600 mb-5">{{ __('register.code_intro') }}</p>
                    <form method="POST" action="{{ route('courses.register.start') }}">
                        @csrf
                        <input type="hidden" name="course_id" value="{{ $course->id }}">
                        {{-- flow_type omitted — continueForm() will derive it from existing profile --}}
                        <div class="mb-4">
                            <label for="otp_contact_value" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.mobile_or_email') }} <span class="text-red-500">*</span></label>
                            <input id="otp_contact_value" type="text" name="contact_value" value="{{ old('contact_value') }}"
                                   placeholder="{{ __('register.mobile_or_email_example') }}" autocomplete="username" dir="ltr"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                            {{-- Determine type from value --}}
                            <input type="hidden" name="contact_type" id="otp_contact_type" value="mobile">
                        </div>
                        <button type="submit" class="btn-primary w-full py-3">{{ __('register.send_code') }}</button>
                    </form>
                    <p class="text-center text-sm text-gray-500 mt-4">
                        <a href="#" @click.prevent="otpLogin = false" class="text-brandMaroon-600 hover:underline"><span class="rtl-flip" aria-hidden="true">←</span> {{ __('register.back_to_password') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function checkoutStart() {
    return {
        tab: @js(session('existing_account') || $errors->has('login_contact') ? 'login' : 'new'),
        flowType: @js(old('flow_type', '')),
        otpLogin: false,
        init() {
            @if(session('existing_account'))
            // Pre-fill the login contact field with the number they entered
            this.$nextTick(() => {
                const f = document.querySelector('[name="login_contact"]');
                if (f && !f.value) f.value = @js((string) session('existing_account'));
            });
            @endif
        }
    }
}

// Auto-set the hidden contact_type field based on whether the value looks like an email
document.addEventListener('DOMContentLoaded', function () {
    const field = document.getElementById('otp_contact_value');
    const typeField = document.getElementById('otp_contact_type');
    if (field && typeField) {
        field.addEventListener('input', function () {
            typeField.value = this.value.includes('@') ? 'email' : 'mobile';
        });
    }
});
</script>
@endsection
