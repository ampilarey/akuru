<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? Enter your mobile number or email and we will send you a verification code.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="identifier" :value="__('Mobile or Email')" />
            <x-text-input id="identifier" class="block mt-1 w-full" type="text" name="identifier" :value="old('identifier')" required autofocus placeholder="960 123 4567 or email@example.com" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Send verification code') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
