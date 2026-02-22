<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 mb-3">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </div>
        <h2 class="text-lg font-bold text-gray-800">Admin Login</h2>
        <p class="text-sm text-gray-500 mt-1">Enter your email or phone — we'll send you a one-time code.</p>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('otp.request') }}">
        @csrf

        <div>
            <x-input-label for="identifier" :value="__('Email or Phone Number')" />
            <x-text-input id="identifier" class="block mt-1 w-full" type="text" name="identifier"
                          :value="old('identifier')" required autofocus
                          placeholder="admin@akuru.edu.mv  or  7972434" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
            <p class="mt-1 text-xs text-gray-400">For Maldives numbers, you can enter just the 7-digit number.</p>
        </div>

        <div class="flex items-center justify-between mt-5">
            <a class="underline text-sm text-gray-500 hover:text-gray-800" href="{{ route('login') }}">
                ← Staff / Student Login
            </a>
            <x-primary-button>
                {{ __('Send OTP') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
