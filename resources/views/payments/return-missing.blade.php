@extends('public.layouts.public')

@section('title', __('payments.missing_title'))

@section('content')
<div class="max-w-lg mx-auto py-16 px-4 text-center">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">{{ __('payments.missing_title') }}</h1>
    <p class="text-gray-600 mb-6">{{ __('payments.missing_intro') }}</p>
    @if (!empty($ref))
        <p class="text-sm text-gray-500 mb-6">{{ __('payments.reference') }}: <code dir="ltr">{{ $ref }}</code></p>
    @endif
    {{--
        This used to offer "resume your registration", linking to
        `courses.register.resume` bare. At the time that link could never
        work — `registration_flows` had no writer — and since C16 slice N5
        (STATUS §5oa) it is a single-use token the family *asks for* on the
        continue form and receives at their verified contact. A bare link to
        the route is still a dead end, so none is offered here.

        What is offered is what actually happens. The bank's webhook is the
        authority on payment (rule 12), and confirming a payment activates the
        enrolment inside that same transaction — `PaymentConfirmed` is what
        domains listen to. So a lost return reference costs the family nothing
        and asks nothing of them.
    --}}
    <p class="text-gray-600 mb-4">
        <strong>{{ __('payments.missing_paid') }}</strong>
        {{ __('payments.missing_paid_hint') }}
    </p>
    {{-- The link sits where each language puts it in the sentence (slice PY1). --}}
    <p class="text-gray-600 mb-8">@foreach(preg_split('/(:enrollments)/', __('payments.missing_check'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if($part === ':enrollments')<a href="{{ route('my.enrollments') }}" class="text-blue-600 underline">{{ __('payments.your_enrollments') }}</a>@else{{ $part }}@endif{{ '' }}@endforeach</p>
    <a href="{{ route('public.courses.index') }}" class="inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
        {{ __('register.back_to_courses') }}
    </a>
</div>
@endsection
