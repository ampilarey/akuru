<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Enter the 6-digit OTP code sent to') }}: <strong>{{ session('password_reset_phone') }}</strong>
    </div>

    @if (session('success'))
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.otp.verify') }}">
        @csrf

        <!-- OTP Code -->
        <div>
            <x-input-label for="code" :value="__('OTP Code')" />
            <x-text-input id="code" class="block mt-1 w-full text-center text-2xl tracking-widest" 
                          type="text" name="code" required autofocus 
                          placeholder="000000" maxlength="6" 
                          pattern="[0-9]{6}" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Enter the 6-digit code') }}</p>
        </div>

        <div class="flex items-center justify-between mt-4">
            <form method="POST" action="{{ route('password.otp.resend') }}" class="inline">
                @csrf
                <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('Resend OTP') }}
                </button>
            </form>

            <x-primary-button>
                {{ __('Verify OTP') }}
            </x-primary-button>
        </div>

        <div class="mt-4 text-center">
            <a class="text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.otp.request') }}">
                {{ __('Use different number') }}
            </a>
        </div>
    </form>

    <script>
        // Only allow numbers
        document.getElementById('code').addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    </script>
</x-guest-layout>

