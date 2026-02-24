@extends('public.layouts.public')

@section('title', 'Register for ' . $course->title)

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
                        Registration fee: {{ config('bml.default_currency', 'MVR') }} {{ number_format($fee, 2) }}
                    </p>
                @else
                    <p class="mt-1 text-sm text-green-700 font-medium">Free enrollment</p>
                @endif
            </div>
        </div>

        <div class="card p-6" x-data="checkoutStart()" x-cloak>

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
            @endif
            @if(session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('success') }}</div>
            @endif
            @if(session('info'))
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 text-blue-800 rounded text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('info') }}
                </div>
            @endif

            {{-- Existing account notice --}}
            @if(session('existing_account'))
                <div class="mb-4 p-4 bg-amber-50 border border-amber-300 rounded-lg text-sm">
                    <p class="font-semibold text-amber-800 mb-1">Account already registered</p>
                    <p class="text-amber-700">
                        <strong>{{ session('existing_account') }}</strong> is already registered.
                        Please log in below to continue your enrollment.
                    </p>
                </div>
            @endif

            {{-- Tab switcher --}}
            <div class="flex border-b mb-6">
                <button type="button"
                        @click="tab = 'new'"
                        :class="tab === 'new' ? 'border-b-2 border-brandMaroon-600 text-brandMaroon-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="pb-3 pr-6 text-sm transition">
                    New registration
                </button>
                <button type="button"
                        @click="tab = 'login'"
                        :class="tab === 'login' ? 'border-b-2 border-brandMaroon-600 text-brandMaroon-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="pb-3 px-6 text-sm transition">
                    I have an account
                </button>
            </div>

            {{-- ── NEW REGISTRATION TAB ── --}}
            <div x-show="tab === 'new'" x-cloak>
                <p class="text-sm text-gray-600 mb-5">
                    Fill in your details below. We'll send a verification code as the <strong>last step</strong> to confirm your contact and create your account.
                </p>

                <form method="POST" action="{{ route('courses.register.start') }}">
                    @csrf
                    <input type="hidden" name="course_id" value="{{ $course->id }}">
                    <input type="hidden" name="flow_type" x-model="flowType">

                    <div x-data="{ contactType: '{{ old('contact_type', 'mobile') }}' }">

                        {{-- ── Contact method ── --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact method <span class="text-red-500">*</span></label>
                            <div class="flex gap-4 mb-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="contact_type" value="mobile" x-model="contactType" {{ old('contact_type','mobile') === 'mobile' ? 'checked' : '' }}>
                                    <span class="text-sm">Mobile (SMS)</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="contact_type" value="email" x-model="contactType" {{ old('contact_type') === 'email' ? 'checked' : '' }}>
                                    <span class="text-sm">Email</span>
                                </label>
                            </div>

                            <div x-show="contactType === 'mobile'">
                                <input type="tel" name="contact_value" value="{{ old('contact_value') }}"
                                       placeholder="7654321" autocomplete="tel"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500"
                                       :disabled="contactType !== 'mobile'">
                                <p class="text-xs text-gray-500 mt-1">Maldives number — country code added automatically</p>
                            </div>
                            <div x-show="contactType === 'email'" x-cloak>
                                <input type="email" name="contact_value" value="{{ old('contact_value') }}"
                                       placeholder="you@example.com" autocomplete="email"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500"
                                       :disabled="contactType !== 'email'">
                                <p class="text-xs text-gray-500 mt-1">OTP will be sent to this email address</p>
                            </div>
                        </div>

                        {{-- ── Who is enrolling ── --}}
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Who is enrolling? <span class="text-red-500">*</span></label>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <button type="button" @click="flowType = 'adult'"
                                        :class="flowType === 'adult' ? 'ring-2 ring-brandMaroon-500 bg-brandMaroon-50' : 'border border-gray-200'"
                                        class="p-3 rounded-lg text-left hover:bg-gray-50 transition">
                                    <span class="font-medium text-sm block">I am enrolling myself</span>
                                    <span class="text-xs text-gray-500">Must be 18 or older</span>
                                </button>
                                <button type="button" @click="flowType = 'parent'"
                                        :class="flowType === 'parent' ? 'ring-2 ring-brandMaroon-500 bg-brandMaroon-50' : 'border border-gray-200'"
                                        class="p-3 rounded-lg text-left hover:bg-gray-50 transition">
                                    <span class="font-medium text-sm block">I am a parent / guardian</span>
                                    <span class="text-xs text-gray-500">Enrolling a child</span>
                                </button>
                            </div>
                            <p x-show="!flowType" x-cloak class="text-xs text-red-500 mt-1">Please select one</p>
                        </div>

                        <hr class="my-5 border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Your details</p>

                        {{-- ── Name ── --}}
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" value="{{ old('first_name') }}" required
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                                @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" value="{{ old('last_name') }}" required
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                                @error('last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ── Gender + DOB ── --}}
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500 bg-white">
                                    <option value="">Select</option>
                                    <option value="male"   {{ old('gender') === 'male'   ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                                </select>
                                @error('gender')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of birth <span class="text-red-500">*</span></label>
                                <input type="date" name="dob" value="{{ old('dob') }}" required max="{{ date('Y-m-d') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                                @error('dob')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ── ID Document ── --}}
                        <div class="mb-4" x-data="{ idType: '{{ old('id_type','national_id') }}' }">
                            <label class="block text-sm font-medium text-gray-700 mb-2">ID document <span class="text-red-500">*</span></label>
                            <div class="flex gap-3 mb-2">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="id_type" value="national_id" x-model="idType" {{ old('id_type','national_id') === 'national_id' ? 'checked' : '' }}>
                                    <span class="text-sm">Maldivian ID</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="id_type" value="passport" x-model="idType" {{ old('id_type') === 'passport' ? 'checked' : '' }}>
                                    <span class="text-sm">Passport</span>
                                </label>
                            </div>
                            <div x-show="idType === 'national_id'">
                                <input type="text" name="national_id" value="{{ old('national_id') }}" placeholder="e.g. A123456"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500 uppercase"
                                       :disabled="idType !== 'national_id'">
                                @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div x-show="idType === 'passport'" x-cloak>
                                <input type="text" name="passport" value="{{ old('passport') }}" placeholder="Passport number"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500 uppercase"
                                       :disabled="idType !== 'passport'">
                                @error('passport')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ── Optional email (only shown when mobile is the primary contact) ── --}}
                        <div class="mb-4" x-show="contactType === 'mobile'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email address <span class="text-xs font-normal text-gray-400">(optional — for notifications)</span>
                            </label>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <hr class="my-5 border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Create a password</p>

                        {{-- ── Password ── --}}
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                            <input type="password" name="password" required placeholder="Min 8 characters" autocomplete="new-password"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password <span class="text-red-500">*</span></label>
                            <input type="password" name="password_confirmation" required placeholder="Repeat password" autocomplete="new-password"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        </div>

                    </div>{{-- end x-data contactType --}}

                    <button type="submit"
                            :disabled="!flowType"
                            class="btn-primary w-full py-3 disabled:opacity-50 disabled:cursor-not-allowed">
                        Send verification code →
                    </button>
                    <p class="text-xs text-gray-400 text-center mt-2">Your account will be created after OTP is verified.</p>
                </form>
            </div>

            {{-- ── RETURNING USER (LOGIN) TAB ── --}}
            <div x-show="tab === 'login'">

                {{-- Password login --}}
                <div x-show="!otpLogin">
                    <p class="text-sm text-gray-600 mb-5">
                        Log in with your mobile number or email and password.
                    </p>

                    <form method="POST" action="{{ route('courses.checkout.login', $course) }}">
                        @csrf

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile or email <span class="text-red-500">*</span></label>
                            <input type="text" name="login_contact" value="{{ old('login_contact') }}"
                                   placeholder="7654321 or you@example.com" autocomplete="username"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        </div>

                        <div class="mb-5">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                            <input type="password" name="password" autocomplete="current-password"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        </div>

                        <button type="submit" class="btn-primary w-full py-3">
                            Log in &amp; continue
                        </button>

                        <p class="text-center text-sm text-gray-500 mt-4">
                            <a href="{{ route('password.otp.request') }}" class="text-brandMaroon-600 hover:underline">Forgot password?</a>
                            &nbsp;·&nbsp;
                            <a href="#" @click.prevent="otpLogin = true" class="text-brandMaroon-600 hover:underline">Use OTP instead</a>
                        </p>
                    </form>
                </div>

                {{-- OTP login fallback --}}
                <div x-show="otpLogin">
                    <p class="text-sm text-gray-600 mb-5">
                        Enter your registered mobile number or email — we'll send you a verification code to log in.
                    </p>

                    <form method="POST" action="{{ route('courses.register.start') }}">
                        @csrf
                        <input type="hidden" name="course_id" value="{{ $course->id }}">
                        {{-- flow_type omitted — continueForm() will derive it from existing profile --}}

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile or email <span class="text-red-500">*</span></label>
                            <input type="text" name="contact_value" value="{{ old('contact_value') }}"
                                   placeholder="7654321 or you@example.com" autocomplete="username"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                            {{-- Determine type from value --}}
                            <input type="hidden" name="contact_type" id="otp_contact_type" value="mobile">
                        </div>

                        <button type="submit" class="btn-primary w-full py-3">Send verification code</button>
                    </form>

                    <p class="text-center text-sm text-gray-500 mt-4">
                        <a href="#" @click.prevent="otpLogin = false" class="text-brandMaroon-600 hover:underline">← Back to password login</a>
                    </p>
                </div>

            </div>

        </div>
    </div>

</section>

<script>
function checkoutStart() {
    return {
        tab: '{{ session("existing_account") || $errors->has("login_contact") ? "login" : "new" }}',
        flowType: '{{ old("flow_type", "") }}',
        otpLogin: false,
        init() {
            @if(session('existing_account'))
            // Pre-fill the login contact field with the number they entered
            this.$nextTick(() => {
                const f = document.querySelector('[name="login_contact"]');
                if (f && !f.value) f.value = '{{ session("existing_account") }}';
            });
            @endif
        }
    }
}

// Auto-set the hidden contact_type field based on whether the value looks like an email
document.addEventListener('DOMContentLoaded', function () {
    const field = document.querySelector('[name="contact_value"]');
    const typeField = document.getElementById('otp_contact_type');
    if (field && typeField) {
        field.addEventListener('input', function () {
            typeField.value = this.value.includes('@') ? 'email' : 'mobile';
        });
    }
});
</script>
@endsection
