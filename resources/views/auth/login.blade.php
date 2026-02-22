<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-5 text-center">
        <p class="text-sm text-gray-500">Sign in with your email or phone number and password.</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Mobile or Email -->
        <div>
            <x-input-label for="identifier" :value="__('Email or Phone Number')" />
            <x-text-input id="identifier" class="block mt-1 w-full" type="text" name="identifier"
                          :value="old('identifier')" required autofocus autocomplete="username"
                          placeholder="email@example.com  or  7972434" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password"
                          name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                       name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-5">
            <a class="underline text-sm text-gray-500 hover:text-gray-800"
               href="{{ route('password.otp.request') }}">
                {{ __('Forgot password?') }}
            </a>
            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    <!-- Admin OTP Login -->
    <div class="mt-6 pt-5 border-t border-gray-200 text-center">
        <p class="text-xs text-gray-400 mb-2">Are you an admin?</p>
        <a href="{{ route('otp.login.form') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 text-sm font-medium hover:bg-indigo-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            Admin Login with OTP
        </a>
    </div>
</x-guest-layout>
