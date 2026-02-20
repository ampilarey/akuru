@extends('public.layouts.public')

@section('title', 'Enter verification code')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-8">
            <h1 class="text-xl font-bold text-brandMaroon-900 mb-1">Enter verification code</h1>
            <p class="text-gray-600 text-sm mb-6">
                We sent a 6-digit code to
                <strong>{{ session('password_reset_contact_value') }}</strong>.
                Enter it below.
            </p>

            @if(session('status') || session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">
                    {{ session('status') ?? session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.otp.verify') }}">
                @csrf
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Verification code <span class="text-red-500">*</span>
                    </label>
                    <input id="code" type="text" name="code" required autofocus
                           maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                           placeholder="000000"
                           class="w-full text-center text-2xl tracking-[0.5em] font-mono rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>

                <button type="submit" class="btn-primary w-full py-3">Verify code</button>
            </form>

            <div class="flex justify-between text-sm text-gray-500 mt-5">
                <form method="POST" action="{{ route('password.otp.resend') }}">
                    @csrf
                    <button type="submit" class="hover:underline">Resend code</button>
                </form>
                <a href="{{ route('password.otp.request') }}" class="hover:underline">Use different number</a>
            </div>
        </div>
    </div>
</section>

<script>
document.getElementById('code').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
});
</script>
@endsection
