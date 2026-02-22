<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 mb-3">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h2 class="text-lg font-bold text-gray-800">Enter Verification Code</h2>
        <p class="text-sm text-gray-500 mt-1">
            We sent a 6-digit code to
            <strong class="text-gray-700">{{ session('otp_login_identifier', 'your contact') }}</strong>
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('otp.verify') }}">
        @csrf

        <div>
            <x-input-label for="code" :value="__('6-Digit Code')" />
            <x-text-input id="code"
                          class="block mt-1 w-full text-center text-3xl tracking-[.5em] font-bold"
                          type="text" name="code" required autofocus
                          placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                          inputmode="numeric" autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="mt-5">
            <x-primary-button class="w-full justify-center">
                {{ __('Verify & Log In') }}
            </x-primary-button>
        </div>

        <div class="flex items-center justify-between mt-4">
            <a href="{{ route('otp.login.form') }}" class="text-sm text-gray-500 hover:text-gray-800 underline">
                ← Use different account
            </a>
            <form method="POST" action="{{ route('otp.resend') }}" class="inline">
                @csrf
                <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800 underline">
                    Resend code
                </button>
            </form>
        </div>
    </form>

    <script>
        const input = document.getElementById('code');
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length === 6) this.form.submit();
        });
    </script>
</x-guest-layout>
