@extends('public.layouts.public')

@section('title', __('payments.checkout_title', ['course' => $course->title]))

@section('content')
{{--
    The course checkout (slice PY1). English throughout before this slice; it
    also said a refusal twice (the layout prints `error` already) and printed
    the form's refusals as escaped markup, `<li>…</li>` on the page.
--}}
<section class="py-12">
    <div class="container mx-auto px-4 max-w-2xl">
        <h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">{{ __('payments.checkout_title', ['course' => $course->title]) }}</h1>

        @if($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded" role="alert" data-testid="checkout-errors">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Compliance: description of goods/services --}}
        <div class="card p-6 mb-6">
            <h2 class="font-semibold text-brandMaroon-700 mb-2">{{ __('payments.course_registration') }}</h2>
            <p class="text-gray-600 text-sm mb-4">{{ $course->short_desc ?? $course->title }}</p>
            <p class="text-lg font-semibold">{{ __('payments.fee', ['amount' => number_format($fee, 2).' '.$currency]) }}</p>
            <p class="text-sm text-gray-500 mt-1">{{ __('payments.outlet_country', ['country' => __('payments.maldives')]) }}</p>
        </div>

        {{-- Compliance: All card brand marks in full colour and equal prominence (Req 1) --}}
        <div class="border border-gray-200 rounded-lg p-4 mb-6 bg-gray-50">
            <p class="text-center text-sm text-gray-600 mb-3">{{ __('payments.we_accept') }}</p>
            <div class="flex flex-wrap items-center justify-center gap-6">
                {{-- Visa brand mark (full colour) --}}
                <div class="flex items-center justify-center h-10" aria-hidden="true">
                    <svg viewBox="0 0 48 16" class="h-8 w-auto" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Visa">
                        <rect width="48" height="16" rx="2" fill="#1A1F71"/>
                        <text x="24" y="11" text-anchor="middle" fill="#FFF" font-family="Arial,sans-serif" font-size="10" font-weight="bold">VISA</text>
                    </svg>
                </div>
                {{-- Mastercard brand mark (full colour: red and orange circles) --}}
                <div class="flex items-center justify-center h-10" aria-hidden="true">
                    <svg viewBox="0 0 24 16" class="h-8 w-auto" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mastercard">
                        <circle cx="9" cy="8" r="6" fill="#EB001B"/>
                        <circle cx="15" cy="8" r="6" fill="#F79E1B" fill-opacity="0.9"/>
                        <path d="M12 4.5a6 6 0 0 1 0 7 6 6 0 0 1 0-7" fill="#FF5F00"/>
                    </svg>
                </div>
                <span class="text-sm text-gray-600">{{ __('payments.other_cards') }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('payments.course.start', $course) }}" class="card p-6" id="checkout-form">
            @csrf

            {{-- Compliance: terms and policies before checkout --}}
            <div class="mb-6 space-y-3 text-sm">
                <p>{{ __('payments.read_first') }}</p>
                <ul class="list-disc list-inside text-gray-600">
                    <li><a href="{{ route('public.terms') }}" class="text-brandMaroon-600 hover:underline" target="_blank" rel="noopener">{{ __('public.Terms of Service') }}</a></li>
                    <li><a href="{{ route('public.refunds') }}" class="text-brandMaroon-600 hover:underline" target="_blank" rel="noopener">{{ __('public.Refund Policy') }}</a></li>
                    <li><a href="{{ route('public.privacy') }}" class="text-brandMaroon-600 hover:underline" target="_blank" rel="noopener">{{ __('public.Privacy Policy') }}</a></li>
                </ul>
                <p class="text-gray-600">{{ __('payments.no_duties') }}</p>
            </div>

            {{-- Required acceptance checkbox; each policy's link sits where the language puts it. --}}
            @php($policyLinks = [
                ':terms' => [route('public.terms'), __('public.Terms of Service')],
                ':refunds' => [route('public.refunds'), __('public.Refund Policy')],
                ':privacy' => [route('public.privacy'), __('public.Privacy Policy')],
            ])
            <div class="mb-6">
                <label for="accept_terms" class="inline-flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" id="accept_terms" name="accept_terms" value="1" required
                        class="rounded border-gray-300 text-brandMaroon-600 focus:ring-brandMaroon-500 mt-0.5"
                        {{ old('accept_terms') ? 'checked' : '' }}>
                    <span class="text-gray-700">@foreach(preg_split('/(:terms|:refunds|:privacy)/', __('payments.agree'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if(isset($policyLinks[$part]))<a href="{{ $policyLinks[$part][0] }}" class="text-brandMaroon-600 hover:underline">{{ $policyLinks[$part][1] }}</a>@else{{ $part }}@endif{{ '' }}@endforeach</span>
                </label>
            </div>

            <p class="text-xs text-gray-500 mb-4">{{ __('payments.secure') }}</p>

            <button type="submit" class="w-full bg-brandMaroon-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-brandMaroon-700">
                {{ __('payments.pay', ['amount' => number_format($fee, 2).' '.$currency]) }}
            </button>
        </form>

        {{-- Corporate / contact info (compliance) --}}
        <div class="mt-8 text-sm text-gray-600">
            <p><strong>{{ __('public.Akuru Institute') }}</strong></p>
            <p>{{ __('public.address') }}</p>
            <p>{{ __('public.Phone') }}: <span dir="ltr">{{ $siteSettings['phone'] ?? '+960 797 2434' }}</span></p>
            <p>{{ __('public.Email') }}: <span dir="ltr">{{ $siteSettings['email'] ?? 'info@akuru.edu.mv' }}</span></p>
        </div>
    </div>
</section>
@endsection
