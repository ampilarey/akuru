@extends('public.layouts.public')

@section('title', 'Set your password')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-6">
            <h1 class="text-xl font-bold text-brandMaroon-900 mb-1">Create a password</h1>
            <p class="text-gray-600 text-sm mb-6">
                Set a password so you can log in easily next time without needing an OTP.
            </p>

            @if(session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('account.set-password.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">New password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" autocomplete="new-password" required minlength="8"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                    <p class="text-xs text-gray-500 mt-1">At least 8 characters</p>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" autocomplete="new-password" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>
                <button type="submit" class="btn-primary w-full py-3">Save password</button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-4">
                <a href="{{ route('public.home') }}" class="hover:underline">Skip for now</a>
            </p>
        </div>
    </div>
</section>
@endsection
