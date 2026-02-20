@extends('public.layouts.public')

@section('title', 'Reset Password')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-8">
            <h1 class="text-xl font-bold text-brandMaroon-900 mb-1">Reset your password</h1>
            <p class="text-gray-600 text-sm mb-6">
                Enter your mobile number or email address and we'll send you a verification code.
            </p>

            @if(session('status') || session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">
                    {{ session('status') ?? session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.otp.send') }}">
                @csrf
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Mobile number or email <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="contact" value="{{ old('contact') }}" required autofocus
                           placeholder="7XXXXXXX or email@example.com"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>

                <button type="submit" class="btn-primary w-full py-3">Send verification code</button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-5">
                <a href="{{ route('login') }}" class="hover:underline">Back to login</a>
            </p>
        </div>
    </div>
</section>
@endsection
