@extends('public.layouts.public')

@section('title', 'Verify your code')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-2">Enter verification code</h1>
            <p class="text-gray-600 mb-6">
                We sent a 6-digit code to your {{ $contactType === 'mobile' ? 'phone' : 'email' }}
                @if(!empty($contactDisplay))
                    <span class="font-medium text-gray-800">({{ $contactDisplay }})</span>
                @endif.
            </p>

            @if(session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('courses.register.verify') }}">
                @csrf
                <div class="mb-4">
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Verification code</label>
                    <input id="code" type="text" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                        placeholder="000000" required autofocus
                        class="w-full text-center text-2xl tracking-widest rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>
                <button type="submit" class="btn-primary w-full py-3">Verify &amp; continue</button>
            </form>

            {{-- Resend option --}}
            <div class="mt-4 text-center text-sm text-gray-500">
                Didn't receive a code?
                @if(!empty($isNewReg))
                    <form method="POST" action="{{ route('courses.register.otp.resend-new') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-brandMaroon-600 hover:underline">Resend code</button>
                    </form>
                @else
                    @php $pendingCourse = session('pending_course_id') ? \App\Models\Course::find(session('pending_course_id')) : null; @endphp
                    @if($pendingCourse)
                        <a href="{{ route('courses.register.show', $pendingCourse) }}" class="text-brandMaroon-600 hover:underline">Start over</a>
                    @else
                        <a href="{{ route('public.courses.index') }}" class="text-brandMaroon-600 hover:underline">Start over</a>
                    @endif
                @endif
            </div>

            {{-- Back to registration form (new reg only) --}}
            @if(!empty($isNewReg))
            <p class="mt-3 text-xs text-gray-400 text-center">
                Entered wrong details?
                @php $pendingCourse = session('pending_course_id') ? \App\Models\Course::find(session('pending_course_id')) : null; @endphp
                @if($pendingCourse)
                    <a href="{{ route('courses.checkout.show', $pendingCourse) }}" class="text-brandMaroon-600 hover:underline">Go back</a>
                @else
                    <a href="{{ route('public.courses.index') }}" class="text-brandMaroon-600 hover:underline">Start over</a>
                @endif
            </p>
            @endif
        </div>
    </div>
</section>
@endsection
