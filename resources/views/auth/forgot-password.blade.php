<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('signin.forgot_intro') }}
        <span class="text-xs text-gray-400 mt-1 block">{{ __('signin.forgot_students') }}</span>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="identifier" :value="__('signin.forgot_identifier')" />
            <x-text-input id="identifier" class="block mt-1 w-full" type="text" name="identifier" dir="ltr" :value="old('identifier')" required autofocus autocomplete="username" placeholder="7000000 · email@example.com · A000000" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" role="alert" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('signin.send_verification_code') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
