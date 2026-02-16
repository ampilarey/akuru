@extends('public.layouts.public')

@section('title', 'Set your password')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-2">Create your password</h1>
            <p class="text-gray-600 mb-6">Choose a strong password for your account.</p>

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('courses.register.set-password.store', app()->getLocale()) }}">
                @csrf
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input id="password" type="password" name="password" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                    <p class="text-xs text-gray-500 mt-1">At least 8 characters</p>
                </div>
                <div class="mb-6">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>
                <button type="submit" class="btn-primary w-full py-3">Continue</button>
            </form>
        </div>
    </div>
</section>
@endsection
