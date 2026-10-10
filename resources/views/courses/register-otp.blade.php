@extends('public.layouts.public')

@section('title', __('register.code_title'))

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-2">{{ __('register.code_heading') }}</h1>
            <p class="text-gray-600 mb-6">
                {{ __($contactType === 'mobile' ? 'register.code_sent_phone' : 'register.code_sent_email') }}
                @if(!empty($contactDisplay))
                    <span class="font-medium text-gray-800" dir="ltr">({{ $contactDisplay }})</span>
                @endif
            </p>

            {{-- A code sent is flashed as success, which the layout shows (printed here too, it showed twice). A resume link lands with its welcome back as info, which the layout does not show, so the page does (slice RG1b). --}}
            @if(session('info'))
                <div class="mb-4 p-3 bg-blue-50 text-blue-800 rounded" role="status" data-testid="code-info">{{ session('info') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('courses.register.verify') }}">
                @csrf
                <div class="mb-4">
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.code') }}</label>
                    <input id="code" type="text" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                        placeholder="000000" required autofocus dir="ltr"
                        class="w-full text-center text-2xl tracking-widest rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>
                <button type="submit" class="btn-primary w-full py-3">{{ __('register.verify') }}</button>
            </form>

            {{-- Resend option --}}
            <div class="mt-4 text-center text-sm text-gray-500">
                {{ __('register.no_code') }}
                @if(!empty($isNewReg))
                    <form method="POST" action="{{ route('courses.register.otp.resend-new') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-brandMaroon-600 hover:underline">{{ __('register.resend') }}</button>
                    </form>
                @else
                    @php $pendingCourse = session('pending_course_id') ? \App\Domains\Courses\Models\Course::find(session('pending_course_id')) : null; @endphp
                    <a href="{{ $pendingCourse ? route('courses.register.show', $pendingCourse) : route('public.courses.index') }}" class="text-brandMaroon-600 hover:underline">{{ __('register.start_over') }}</a>
                @endif
            </div>

            {{-- Back to registration form (new reg only) --}}
            @if(!empty($isNewReg))
            <p class="mt-3 text-xs text-gray-400 text-center">
                {{ __('register.wrong_details') }}
                @php $pendingCourse = session('pending_course_id') ? \App\Domains\Courses\Models\Course::find(session('pending_course_id')) : null; @endphp
                @if($pendingCourse)
                    <a href="{{ route('courses.checkout.show', $pendingCourse) }}" class="text-brandMaroon-600 hover:underline">{{ __('register.go_back') }}</a>
                @else
                    <a href="{{ route('public.courses.index') }}" class="text-brandMaroon-600 hover:underline">{{ __('register.start_over') }}</a>
                @endif
            </p>
            @endif
        </div>
    </div>
</section>
@endsection
