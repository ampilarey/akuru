@extends('public.layouts.public')

@section('title', __('public.Gift cards') . ' - ' . config('app.name'))

@section('content')
<div class="container mx-auto px-4 py-16 max-w-xl text-center">
    @if($order === null)
        <h1 class="text-3xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Gift cards') }}</h1>
        <a class="btn-primary" href="{{ route('public.gift-cards.index') }}">{{ __('public.Buy a gift card') }}</a>
    @elseif($order['status'] === 'paid')
        <h1 class="text-3xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Payment confirmed') }}</h1>
        <p class="text-gray-600 mb-2">{{ __('public.The :currency :amount gift card for :name is on its way.', ['currency' => $order['currency'], 'amount' => number_format((float) $order['amount'], 2), 'name' => $order['recipient_name']]) }}</p>
        @if($order['delivered_to'])
            <p class="text-gray-600 mb-6">{{ __('public.The code was sent to :to.', ['to' => $order['delivered_to']]) }}</p>
        @endif
        <a class="btn-secondary" href="{{ route('public.wallet') }}">{{ __('public.My Wallet') }}</a>
    @elseif($order['status'] === 'failed')
        <h1 class="text-3xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Payment could not be started') }}</h1>
        <a class="btn-primary" href="{{ route('public.gift-cards.index') }}">{{ __('public.Try again') }}</a>
    @else
        <h1 class="text-3xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Confirming your payment…') }}</h1>
        <p class="text-gray-600 mb-6">{{ __('public.Bank confirmation can take a moment. Refresh this page shortly — the code is sent as soon as the bank confirms.') }}</p>
        <a class="btn-secondary" href="{{ route('public.gift-cards.return') }}">{{ __('public.Refresh') }}</a>
    @endif
</div>
@endsection
