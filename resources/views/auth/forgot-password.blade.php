<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? Enter your mobile number, email, or ID card number and we will send you a verification code.') }}
        <br><span class="text-xs text-gray-400 mt-1 block">Students: enter your ID card number — the code will be sent to your parent's mobile.</span>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="identifier" :value="__('Mobile, Email, or ID Card')" />
            <x-text-input id="identifier" class="block mt-1 w-full" type="text" name="identifier" :value="old('identifier')" required autofocus placeholder="e.g. 960 123 4567 · email@example.com · A211217" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Send verification code') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
