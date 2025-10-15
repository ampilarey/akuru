<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Enter your phone number to receive a one-time password (OTP) for secure login.') }}
    </div>

    @if (session('success'))
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('otp.request') }}">
        @csrf

        <!-- Phone Number -->
        <div>
            <x-input-label for="phone" :value="__('Phone Number')" />
            <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone')" required autofocus placeholder="7972434 or 9607972434" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Enter your registered phone number') }}</p>
        </div>

        <div class="flex items-center justify-between mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Back to Email Login') }}
            </a>

            <x-primary-button>
                {{ __('Send OTP') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

