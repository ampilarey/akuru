{{-- COMMERCE_PARITY_PLAN P7b: stop the Bookstore's SMS offers. Reached from the link at the end of every offer;
     confirmed with a button, so a link preview opening it stops nobody. The same shape as the newsletter's (B9c). --}}
@extends('public.layouts.public')

@section('title', __('shop.sms_stop_title'))

@section('content')
<div class="container mx-auto max-w-xl px-4 py-12" data-testid="sms-stop">
    <h1 class="mb-3 text-2xl font-bold text-brandMaroon-900">{{ __('shop.sms_stop_title') }}</h1>
    @if($done || ! $optin['subscribed'])
        <p class="rounded bg-green-50 p-3 text-green-800" data-testid="sms-stopped">{{ __('shop.sms_stopped', ['phone' => $optin['phone']]) }}</p>
    @else
        <p class="mb-4 text-gray-700">{{ __('shop.sms_stop_body', ['phone' => $optin['phone']]) }}</p>
        <form method="POST" action="{{ route('public.shop.sms.stop.confirm', $token) }}">
            @csrf
            <button type="submit" class="btn-primary" data-testid="sms-stop-confirm">{{ __('shop.sms_stop_button') }}</button>
        </form>
    @endif
    <p class="mt-6 text-sm"><a href="{{ route('public.shop.index') }}" class="text-brandMaroon-700 underline">{{ __('shop.back_to_shop') }}</a></p>
</div>
@endsection
