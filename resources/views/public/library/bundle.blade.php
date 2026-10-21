@extends('public.layouts.public')

@section('title', $bundle['title'] . ' - ' . __('public.Digital Library') . ' - ' . config('app.name'))
@section('description', __('public.bundle_meta', ['count' => count($bundle['items'])]))

{{-- LIBRARY_PLAN §19 (B4d, STATUS §5un): one bundle — its works, what it saves, the way to buy it. --}}
@section('content')
<div class="container mx-auto max-w-3xl px-4 py-8 sm:py-10" data-bundle="{{ $bundle['slug'] }}">
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('public.library.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Digital Library') }}</a>
        <span>›</span>
        <a href="{{ route('public.library.promotions') }}" class="hover:text-brandMaroon-600">{{ __('public.Offers') }}</a>
    </nav>
    <h1 class="mb-2 text-2xl font-bold text-brandMaroon-900 sm:text-3xl" data-office-words>{{ $bundle['title'] }}</h1>
    @if($bundle['description'])
        <p class="mb-4 whitespace-pre-line text-gray-700" data-office-words>{{ $bundle['description'] }}</p>
    @endif

    <div class="mb-6 rounded-lg border bg-brandBeige-50 p-4 sm:p-6" data-phone-main data-testid="bundle-buy">
        <p class="text-lg" data-testid="bundle-price">
            <s class="text-gray-500">{{ $bundle['currency'] }} {{ number_format($bundle['list_total'], 2) }}</s>
            <span class="font-semibold text-brandMaroon-900">{{ $bundle['currency'] }} {{ number_format($bundle['price'], 2) }}</span>
        </p>
        <p class="text-sm text-red-800" data-testid="bundle-saving">{{ __('public.bundle_saving', ['amount' => $bundle['currency'].' '.number_format($bundle['saving'], 2), 'count' => count($bundle['items'])]) }}</p>
        @if($bundle['ends_on'])
            <p class="text-sm text-gray-600">{{ __('public.Offer ends :date', ['date' => $bundle['ends_on']]) }}</p>
        @endif

        @if($paid && $bundle['owned_count'] < count($bundle['items']))
            <p class="mt-3 rounded bg-white p-3 text-sm text-gray-700" role="status" data-testid="bundle-confirming">{{ __('public.Bank confirmation can take a moment. Refresh this page shortly — access is granted as soon as the bank confirms.') }}</p>
        @endif

        @if($bundle['owned_count'] === count($bundle['items']))
            <p class="mt-3 font-semibold text-green-800" data-testid="bundle-owned">{{ __('public.bundle_have_all') }}</p>
        @elseif(! $bundle['on_sale'])
            <p class="mt-3 text-gray-700">{{ __('public.bundle_error_not_on_sale') }}</p>
        @elseif(auth()->check())
            @if($bundle['owned_count'] > 0)
                <p class="mt-3 text-sm text-amber-800" data-testid="bundle-some-owned">{{ __('public.bundle_some_owned', ['count' => $bundle['owned_count']]) }}</p>
            @endif
            <form method="POST" action="{{ route('public.library.bundle.checkout', $bundle['slug']) }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                @csrf
                <button type="submit" class="btn-primary justify-center" data-testid="bundle-buy-card">{{ __('public.Buy for :price', ['price' => $bundle['currency'].' '.number_format($bundle['price'], 2)]) }}</button>
                <button type="submit" name="pay_with_wallet" value="1" class="btn-secondary justify-center" data-testid="bundle-buy-wallet">{{ __('public.Pay with wallet') }}</button>
            </form>
            <p class="mt-2 text-sm text-gray-500">{{ __('public.Access opens as soon as the bank confirms your payment.') }}</p>
            @error('bundle')
                <p class="mt-2 text-sm text-red-700" role="alert" data-testid="bundle-error">{{ $message }}</p>
            @enderror
            @error('amount')
                <p class="mt-2 text-sm text-red-700" role="alert" data-testid="bundle-error">{{ $message }}</p>
            @enderror
        @else
            <a href="{{ route('phone.sign-in', ['next' => request()->getRequestUri()]) }}" class="btn-primary mt-3 inline-flex" data-testid="bundle-sign-in">{{ __('public.Sign in') }}</a>
        @endif
    </div>

    <h2 class="mb-3 text-lg font-semibold text-brandMaroon-900">{{ __('public.bundle_in_it', ['count' => count($bundle['items'])]) }}</h2>
    <ul class="grid gap-3 sm:grid-cols-2" data-testid="bundle-items">
        @foreach($bundle['items'] as $item)
            <li>
                <a href="{{ route($item['owned'] ? 'public.library.read' : 'public.library.show', $item['slug']) }}" class="flex min-h-[3rem] gap-3 rounded border bg-white p-3 hover:shadow-md" data-item="{{ $item['slug'] }}" data-owned="{{ $item['owned'] ? 'yes' : 'no' }}">
                    @if($item['cover_url'])
                        <img src="{{ $item['cover_url'] }}" alt="" class="h-20 w-14 shrink-0 rounded object-cover" loading="lazy">
                    @endif
                    <span class="min-w-0 text-sm">
                        <span class="block font-semibold leading-tight text-brandMaroon-900" dir="auto">{{ $item['title'] }}</span>
                        <span class="mt-1 block text-gray-600">{{ $item['owned'] ? __('public.Read online') : $bundle['currency'].' '.number_format($item['price'], 2) }}</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
@endsection
