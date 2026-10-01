@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 "My orders" (slice B2). Own only. --}}
@section('title', __('shop.my_orders_title') . ' - ' . __('shop.bookshop_title'))

@section('content')
<div class="container mx-auto max-w-4xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold text-brandMaroon-900" data-testid="orders-heading">{{ __('shop.my_orders_title') }}</h1>
        <div class="flex gap-3">
            <a href="{{ route('public.shop.orders.export') }}" class="btn-secondary" data-testid="export-orders">{{ __('shop.export_csv') }}</a>
            <a href="{{ route('public.shop.index') }}" class="btn-secondary">{{ __('shop.browse_shop') }}</a>
        </div>
    </div>

    {{-- COMMERCE_PARITY_PLAN P8c: a school's credit account — what it may spend, what it owes, and its statement. --}}
    @if($credit ?? null)
        <section class="mb-6 rounded-lg border bg-white p-4 text-sm" data-testid="credit-account">
            <h2 class="mb-1 font-semibold">{{ __('shop.credit_account_heading') }}@if($credit['organisation']) · {{ $credit['organisation'] }}@endif</h2>
            <p>{{ __('shop.credit_account_line', ['limit' => 'MVR '.$credit['limit'], 'owed' => 'MVR '.$credit['owed'], 'available' => 'MVR '.$credit['available'], 'days' => $credit['terms_days']]) }}</p>
            @if((float) $credit['overdue'] > 0)<p class="mt-1 font-medium text-red-700" data-testid="credit-overdue">{{ __('shop.credit_overdue', ['amount' => 'MVR '.$credit['overdue']]) }}</p>@endif
            @if((float) $credit['in_credit'] > 0)<p class="mt-1 font-medium text-green-700" data-testid="credit-in-credit">{{ __('shop.credit_in_credit', ['amount' => 'MVR '.$credit['in_credit']]) }}</p>@endif
            <a href="{{ route('public.shop.credit.statement') }}" class="mt-2 inline-block text-brandMaroon-700 underline" data-testid="credit-statement">{{ __('shop.credit_statement_csv') }}</a>
        </section>
    @endif

    @if($invite ?? null)
        {{-- STATUS §5ln: a customer's share link, while referral credit is on. --}}
        <section class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm" data-testid="referral-invite">
            <h2 class="font-semibold text-green-900">{{ __('shop.referral_title') }}</h2>
            <p class="mb-2 text-green-900">{{ __('shop.referral_intro', ['you' => 'MVR '.$invite['referrer_amount'], 'friend' => 'MVR '.$invite['friend_amount'], 'min' => 'MVR '.$invite['min_order']]) }}</p>
            <input type="text" readonly value="{{ $invite['url'] }}" dir="ltr" class="form-input w-full bg-white font-mono text-xs" aria-label="{{ __('shop.referral_link') }}" data-testid="referral-link">
            <p class="mt-2 text-xs text-green-800" data-testid="referral-counts">{{ __('shop.referral_counts', ['paid' => $invite['paid'], 'pending' => $invite['pending']]) }}</p>
        </section>
    @endif

    @if(count($orders) === 0)
        <p class="rounded-lg border bg-white p-6 text-gray-600" data-testid="no-orders">{{ __('shop.no_orders') }}</p>
    @else
        <ul class="divide-y rounded-lg border bg-white" data-testid="orders-list">
            @foreach($orders as $order)
                <li data-order="{{ $order['number'] }}">
                    <a href="{{ route('public.shop.orders.show', $order['number']) }}" class="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-brandBeige-50">
                        <span>
                            <span class="font-mono font-semibold text-brandMaroon-900">{{ $order['number'] }}</span>
                            <span class="ms-2 rounded bg-gray-100 px-2 py-0.5 text-xs">{{ __('shop.status_'.$order['status']) }}</span>
                            <span class="block text-sm text-gray-600">{{ $order['vendor']['name'] }} · {{ __('shop.items_count', ['count' => $order['item_count']]) }} · {{ $order['delivery']['name'] }}</span>
                            <span class="block text-xs text-gray-500">{{ __('shop.order_placed') }} {{ $order['placed_at'] }}</span>
                        </span>
                        <span class="font-semibold">{{ $order['currency'] }} {{ $order['total'] }}</span>
                    </a>
                    {{-- §5ld: order the same again in one tap. --}}
                    <form method="POST" action="{{ route('public.shop.orders.buy-again', $order['number']) }}" class="px-4 pb-3">
                        @csrf
                        <button type="submit" class="text-sm font-semibold text-brandMaroon-700 underline" data-testid="buy-again-{{ $order['number'] }}">{{ __('shop.buy_again') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
