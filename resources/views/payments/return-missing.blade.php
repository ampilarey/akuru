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
    {{--
        This used to offer "resume your registration", linking to
        `courses.register.resume`. That link could never work: `registration_flows`
        has two readers and no writer anywhere in the application, so the resume
        route always answers "No active registration found. Please start again
        from a course page." A family who may have just paid was being sent to a
        dead end and told to start over.

        What replaces it is what actually happens. The bank's webhook is the
        authority on payment (rule 12), and confirming a payment activates the
        enrolment inside that same transaction — `PaymentConfirmed` is what
        domains listen to. So a lost return reference costs the family nothing
        and asks nothing of them.
    --}}
    <p class="text-gray-600 mb-4">
        <strong>If you completed the payment, it will still be confirmed.</strong>
        The bank notifies us directly, and your enrolment is activated when it does —
        this page only means the reference did not come back in your browser.
    </p>
    <p class="text-gray-600 mb-8">
        Check <a href="{{ route('my.enrollments') }}" class="text-blue-600 underline">your enrolments</a>
        in a few minutes. If the course has not appeared, contact us with the reference above
        and we will find the payment.
    </p>
    <a href="{{ route('public.courses.index') }}" class="inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
        Back to Courses
    </a>
</div>
@endsection
