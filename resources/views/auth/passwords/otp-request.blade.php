@extends('public.layouts.public')

@section('title', __('signin.reset_title'))

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-8">
            <h1 class="text-xl font-bold text-brandMaroon-900 mb-1">{{ __('signin.reset_title') }}</h1>
            <p class="text-gray-600 text-sm mb-6">
                {{ __('signin.reset_intro') }}
            </p>

            {{-- A saved message is the layout's to say; it said it twice (slice SI1). --}}
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm" role="alert" data-testid="reset-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.otp.send') }}">
                @csrf
                <div class="mb-5">
                    <label for="contact" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('signin.mobile_or_email') }} <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input id="contact" type="text" name="contact" value="{{ old('contact') }}" required autofocus dir="ltr" autocomplete="username"
                           placeholder="7000000 · email@example.com"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>

                <button type="submit" class="btn-primary w-full py-3">{{ __('signin.send_verification_code') }}</button>
            </form>

            {{-- Only the forgot-password page takes an ID card number, which a child signs in with; nothing linked to it (slice SI1). --}}
            <p class="text-center text-sm text-gray-600 mt-5">
                <a href="{{ route('password.request') }}" class="text-brandMaroon-600 hover:underline" data-testid="reset-with-id-card">{{ __('signin.reset_with_id_card') }}</a>
            </p>
            <p class="text-center text-sm text-gray-500 mt-3">
                <a href="{{ route('login') }}" class="hover:underline">{{ __('signin.back_to_sign_in') }}</a>
            </p>
        </div>
    </div>
</section>
@endsection
