@extends('public.layouts.public')

@section('title', __('signin.set_new_password'))

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-8">
            <h1 class="text-xl font-bold text-brandMaroon-900 mb-1">{{ __('signin.set_new_password') }}</h1>
            <p class="text-gray-600 text-sm mb-6">{{ __('signin.choose_strong') }}</p>

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm" role="alert" data-testid="reset-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.otp.update') }}">
                @csrf
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('signin.new_password') }} <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input id="password" type="password" name="password" required autocomplete="new-password" minlength="8" aria-describedby="password-hint"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                    <p id="password-hint" class="text-xs text-gray-500 mt-1">{{ __('signin.at_least_8') }}</p>
                </div>
                <div class="mb-6">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('signin.confirm_password') }} <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>

                <button type="submit" class="btn-primary w-full py-3">{{ __('signin.reset_password') }}</button>
            </form>
        </div>
    </div>
</section>
@endsection
