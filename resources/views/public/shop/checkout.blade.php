@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 "Checkout" (slice B2): one address, a delivery method per
     shop priced for this basket, a discount code, and the payment method. --}}
@section('title', __('shop.checkout_title') . ' - ' . __('shop.bookshop_title'))

@php($basket = $checkout['basket'])
@php($currency = $checkout['currency'])
@php($oldAddress = $old['address_id'] ?? ($checkout['addresses'][0]['id'] ?? 'new'))

@section('content')
<div class="container mx-auto max-w-5xl px-4 py-8">
    <nav class="mb-4 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
        <span>›</span>
        <a href="{{ route('public.shop.cart') }}" class="hover:text-brandMaroon-600">{{ __('shop.cart_title') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ __('shop.checkout_title') }}</span>
    </nav>
    <h1 class="mb-6 text-3xl font-bold text-brandMaroon-900" data-testid="checkout-heading">{{ __('shop.checkout_title') }}</h1>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" data-testid="checkout-errors">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif
    @if(count($basket['problems']) > 0)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            <p class="font-semibold">{{ __('shop.fix_problems') }}</p>
            <ul class="list-disc ps-5">@foreach($basket['problems'] as $problem)<li>{{ $problem }}</li>@endforeach</ul>
            <a href="{{ route('public.shop.cart') }}" class="underline">{{ __('shop.view_cart') }}</a>
        </div>
    @endif

    <form method="POST" action="{{ route('public.shop.checkout.store') }}" class="grid gap-6 md:grid-cols-5" data-testid="checkout-form">
        @csrf
        <div class="space-y-6 md:col-span-3">
            {{-- 1. Address --}}
            <section class="rounded-lg border bg-white p-4" data-testid="checkout-address">
                <h2 class="mb-3 text-lg font-semibold">{{ __('shop.deliver_to') }}</h2>
                @foreach($checkout['addresses'] as $address)
                    <label class="mb-2 flex items-start gap-2 text-sm">
                        <input type="radio" name="address_id" value="{{ $address['id'] }}" @checked((string) $oldAddress === (string) $address['id']) data-testid="saved-address">
                        <span>
                            <span class="font-medium">{{ $address['label'] ?: $address['recipient_name'] }}</span>
                            <span class="block text-gray-600">{{ $address['recipient_name'] }} · {{ $address['phone'] }} · {{ $address['street'] }}, {{ $address['island'] }}, {{ $address['atoll'] }}</span>
                        </span>
                    </label>
                @endforeach
                @if(count($checkout['addresses']) > 0)
                    <label class="mb-2 flex items-center gap-2 text-sm">
                        <input type="radio" name="address_id" value="" @checked($oldAddress === 'new' || $oldAddress === '') data-testid="new-address"> {{ __('shop.new_address') }}
                    </label>
                @endif
                <div class="grid gap-3 md:grid-cols-2" data-testid="address-fields">
                    <label class="text-sm md:col-span-2">{{ __('shop.recipient_name') }}<input name="recipient_name" value="{{ $old['recipient_name'] ?? '' }}" class="form-input w-full" data-testid="recipient-name"></label>
                    <label class="text-sm">{{ __('shop.phone') }}<input name="phone" value="{{ $old['phone'] ?? '' }}" class="form-input w-full" inputmode="tel" data-testid="phone"></label>
                    <label class="text-sm">{{ __('shop.atoll') }}<input name="atoll" value="{{ $old['atoll'] ?? '' }}" class="form-input w-full" data-testid="atoll"></label>
                    <label class="text-sm">{{ __('shop.island') }}<input name="island" value="{{ $old['island'] ?? '' }}" class="form-input w-full" data-testid="island"></label>
                    <label class="text-sm">{{ __('shop.street') }}<input name="street" value="{{ $old['street'] ?? '' }}" class="form-input w-full" data-testid="street"></label>
                    <label class="text-sm md:col-span-2">{{ __('shop.address_notes') }}<input name="address_notes" value="{{ $old['address_notes'] ?? '' }}" class="form-input w-full"></label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="save_address" value="1" @checked(! empty($old['save_address'])) data-testid="save-address"> {{ __('shop.save_address') }}</label>
                    <input name="address_label" value="{{ $old['address_label'] ?? '' }}" class="form-input w-full" placeholder="{{ __('shop.address_label_hint') }}" aria-label="{{ __('shop.address_label_hint') }}">
                </div>
            </section>

            {{-- 2. Delivery, per shop --}}
            <section class="rounded-lg border bg-white p-4" data-testid="checkout-delivery">
                <h2 class="mb-3 text-lg font-semibold">{{ __('shop.delivery_heading') }}</h2>
                @foreach($basket['groups'] as $group)
                    <fieldset class="mb-4" data-testid="delivery-{{ $group['vendor']['slug'] }}">
                        <legend class="mb-1 text-sm font-semibold text-gray-700">{{ __('shop.delivery_for', ['vendor' => $group['vendor']['name']]) }}</legend>
                        @foreach($group['delivery_options'] as $option)
                            <label class="mb-1 flex items-start gap-2 text-sm {{ $option['offered'] ? '' : 'text-gray-400' }}">
                                <input type="radio" name="delivery[{{ $group['vendor']['slug'] }}]" value="{{ $option['key'] }}" @disabled(! $option['offered']) @checked(($old['delivery'][$group['vendor']['slug']] ?? '') === $option['key']) data-delivery-kind="{{ $option['kind'] }}">
                                <span>
                                    <span class="font-medium">{{ $option['name'] }}</span>
                                    <span class="ms-1">— @if($option['carrier_paid']){{ __('shop.carrier_paid_note') }}@elseif((float) $option['fee'] === 0.0){{ __('shop.free') }}@else{{ $currency }} {{ $option['fee'] }}@endif</span>
                                    <span class="block text-xs text-gray-500">
                                        {{ __('shop.handling_note', ['days' => $option['handling_days']]) }}
                                        @if($option['minimum_order'] && ! $option['offered']) · {{ __('shop.min_order', ['amount' => $currency.' '.$option['minimum_order']]) }} · {{ __('shop.not_offered') }}@endif
                                        @if($option['note']) · {{ $option['note'] }}@endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                @endforeach
            </section>

            {{-- 3. Payment --}}
            <section class="rounded-lg border bg-white p-4" data-testid="checkout-payment">
                <h2 class="mb-3 text-lg font-semibold">{{ __('shop.payment_heading') }}</h2>
                @foreach($checkout['payment_methods'] as $method)
                    <label class="mb-2 flex items-start gap-2 text-sm">
                        <input type="radio" name="payment_method" value="{{ $method }}" @checked(($old['payment_method'] ?? $checkout['payment_methods'][0]) === $method) data-testid="pay-{{ $method }}">
                        <span>
                            <span class="font-medium">{{ __('shop.pay_'.$method) }}</span>
                            @if($method === 'wallet')<span class="block text-xs text-gray-500">{{ __('shop.wallet_balance', ['balance' => $currency.' '.$checkout['wallet_balance']]) }}</span>@endif
                            @if($method === 'bank_transfer')<span class="block text-xs text-gray-500">{{ __('shop.pay_bank_transfer_hint') }}</span>@endif
                        </span>
                    </label>
                @endforeach
                <label class="mt-3 block text-sm">{{ __('shop.discount_code') }}<input name="discount_code" value="{{ $old['discount_code'] ?? '' }}" class="form-input w-full md:w-64" data-testid="discount-code"></label>
                <label class="mt-3 block text-sm">{{ __('shop.order_notes') }}<textarea name="notes" rows="2" class="form-input w-full">{{ $old['notes'] ?? '' }}</textarea></label>
            </section>
        </div>

        {{-- Summary --}}
        <aside class="md:col-span-2">
            <div class="sticky top-4 rounded-lg border bg-white p-4" data-testid="checkout-summary">
                <h2 class="mb-3 text-lg font-semibold">{{ __('shop.cart_title') }}</h2>
                <ul class="mb-3 divide-y text-sm">
                    @foreach($basket['groups'] as $group)
                        @foreach($group['lines'] as $line)
                            <li class="flex justify-between py-1"><span dir="auto">{{ $line['quantity'] }} × {{ $line['title'] }}@if($line['variant']) ({{ $line['variant'] }})@endif</span><span>{{ $line['line_total'] }}</span></li>
                        @endforeach
                    @endforeach
                </ul>
                <p class="flex justify-between text-sm"><span>{{ __('shop.goods') }}</span><span data-testid="summary-goods">{{ $currency }} {{ $basket['subtotal'] }}</span></p>
                <p class="mb-3 text-xs text-gray-500">{{ __('shop.delivery_at_checkout') }} {{ __('shop.prices_include_tax') }}</p>
                <p class="mb-3 text-xs text-gray-500">{{ __('shop.reservation_note', ['minutes' => $checkout['reservation_minutes']]) }}</p>
                <button type="submit" class="btn-primary w-full" data-testid="place-order" @disabled(count($basket['problems']) > 0)>{{ __('shop.place_order') }}</button>
                <p class="mt-3 text-xs text-gray-500">{!! __('shop.checkout_terms', [
                    'terms' => '<a href="'.route('public.page.show', 'shop-terms').'" class="underline" target="_blank" rel="noopener">'.e(__('shop.shop_terms')).'</a>',
                    'delivery' => '<a href="'.route('public.page.show', 'delivery-and-returns').'" class="underline" target="_blank" rel="noopener">'.e(__('shop.delivery_and_returns')).'</a>',
                ]) !!}</p>
            </div>
        </aside>
    </form>
</div>
@endsection
