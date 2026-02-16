@extends('public.layouts.public')

@section('title', 'Verify your code')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-2">Enter verification code</h1>
            <p class="text-gray-600 mb-6">
                We sent a 6-digit code to your {{ $contact->type === 'mobile' ? 'phone' : 'email' }}.
            </p>

            @if(session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('courses.register.verify', app()->getLocale()) }}">
                @csrf
                <div class="mb-4">
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Verification code</label>
                    <input id="code" type="text" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                        placeholder="000000" required autofocus
                        class="w-full text-center text-2xl tracking-widest rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                </div>
                <button type="submit" class="btn-primary w-full py-3">Verify</button>
            </form>

            @php $pendingCourse = session('pending_course_id') ? \App\Models\Course::find(session('pending_course_id')) : null; @endphp
            <p class="mt-4 text-sm text-gray-500 text-center">
                @if($pendingCourse)
                    <a href="{{ route('courses.register.show', [app()->getLocale(), $pendingCourse]) }}" class="text-brandMaroon-600 hover:underline">Use a different contact</a>
                @else
                    <a href="{{ route('public.courses.index', app()->getLocale()) }}" class="text-brandMaroon-600 hover:underline">Start over</a>
                @endif
            </p>
        </div>
    </div>
</section>
@endsection
