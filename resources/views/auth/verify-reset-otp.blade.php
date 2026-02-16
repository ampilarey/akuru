<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Enter the 6-digit verification code we sent to your mobile or email.') }}
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.reset.verify.store') }}">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Verification code')" />
            <x-text-input id="code" class="block mt-1 w-full text-center text-xl tracking-widest" type="text" name="code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required autofocus />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Verify') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
