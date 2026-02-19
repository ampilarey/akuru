@extends('public.layouts.public')

@section('title', 'Payment Reference Missing')

@section('content')
<div class="max-w-lg mx-auto py-16 px-4 text-center">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Payment Reference Not Found</h1>
    <p class="text-gray-600 mb-6">
        We could not find a payment reference for your return.
        This may happen if your browser did not pass the reference in the URL.
    </p>
    @if (!empty($ref))
        <p class="text-sm text-gray-500 mb-6">Reference: <code>{{ $ref }}</code></p>
    @endif
    <p class="text-gray-600 mb-8">
        If you completed a payment, please
        <a href="{{ route('courses.register.resume') }}" class="text-blue-600 underline">resume your registration</a>
        or contact us for assistance.
    </p>
    <a href="{{ route('public.courses.index') }}" class="inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
        Back to Courses
    </a>
</div>
@endsection
