{{-- BOOKSHOP_PLAN slice B9c: leave a shop's newsletter. Reached from the link in the shop's own mailings;
     the address is confirmed with a button, so a mail scanner opening the link unsubscribes nobody. --}}
@extends('public.layouts.public')

@section('title', __('shop.newsletter_leave_title'))

@section('content')
<div class="container mx-auto max-w-xl px-4 py-12" data-testid="newsletter-leave">
    <h1 class="mb-3 text-2xl font-bold text-brandMaroon-900">{{ __('shop.newsletter_leave_title') }}</h1>
    @if($done || ! $subscriber['subscribed'])
        <p class="rounded bg-green-50 p-3 text-green-800" data-testid="newsletter-left">{{ __('shop.newsletter_left', ['email' => $subscriber['email'], 'shop' => $subscriber['shop']]) }}</p>
    @else
        <p class="mb-4 text-gray-700">{{ __('shop.newsletter_leave_body', ['email' => $subscriber['email'], 'shop' => $subscriber['shop']]) }}</p>
        <form method="POST" action="{{ route('public.shop.newsletter.unsubscribe.confirm', $token) }}">
            @csrf
            <button type="submit" class="btn-primary" data-testid="newsletter-leave-confirm">{{ __('shop.newsletter_leave_button') }}</button>
        </form>
    @endif
    <p class="mt-6 text-sm"><a href="{{ route('public.shop.index') }}" class="text-brandMaroon-700 underline">{{ __('shop.back_to_shop') }}</a></p>
</div>
@endsection
