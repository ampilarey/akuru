@extends('public.layouts.public')

@section('title', $item['title'] . ' - ' . config('app.name'))

@section('content')
<div class="container mx-auto px-4 py-16 max-w-xl text-center">
    @if($confirmed)
        <h1 class="text-3xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Payment confirmed') }}</h1>
        <p class="text-gray-600 mb-6">{{ __('public.Your access is ready.') }}</p>
        <a class="btn-primary" href="{{ route('public.library.read', ['slug' => $item['slug']]) }}">{{ __('public.Read online') }}</a>
    @else
        <h1 class="text-3xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Confirming your payment…') }}</h1>
        <p class="text-gray-600 mb-6">{{ __('public.Bank confirmation can take a moment. Refresh this page shortly — access is granted as soon as the bank confirms.') }}</p>
        <a class="btn-secondary" href="{{ route('public.library.payment-return', $item['slug']) }}">{{ __('public.Refresh') }}</a>
    @endif
</div>
@endsection
