@extends('public.layouts.public')

@section('title', __('public.My Wallet') . ' - ' . config('app.name'))

@section('content')
<div class="container mx-auto px-4 py-10 max-w-3xl">
    <h1 class="text-3xl font-bold text-brandMaroon-900 mb-6">{{ __('public.My Wallet') }}</h1>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-white p-6">
        <div>
            <div class="text-sm text-gray-500">{{ __('public.Balance') }}</div>
            <div class="text-3xl font-bold text-brandMaroon-900">{{ $wallet['currency'] }} {{ $wallet['balance'] }}</div>
        </div>
        <form method="POST" action="{{ route('public.wallet.redeem') }}" class="flex gap-2">
            @csrf
            <input type="text" name="code" class="form-input" placeholder="AKG-XXXX-XXXX-XXXX" required>
            <button type="submit" class="btn-primary">{{ __('public.Redeem gift card') }}</button>
        </form>
    </div>
    <p class="mb-6 text-sm text-gray-600">
        <a href="{{ route('public.gift-cards.index') }}" class="text-brandMaroon-700 hover:underline" data-testid="buy-gift-card">{{ __('public.Buy a gift card') }}</a>
        · <a href="{{ route('public.library.my') }}" class="hover:underline">{{ __('public.My Library') }}</a>
        · <a href="{{ route('public.library.index') }}" class="hover:underline">{{ __('public.Library') }}</a>
        · <a href="{{ route('public.page.show', 'wallet-terms') }}" class="hover:underline">{{ __('public.Wallet Terms') }}</a>
    </p>
    @if(session('success'))
        <p class="mb-4 rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>
    @endif
    @error('code')
        <p class="mb-4 rounded bg-red-50 p-3 text-red-700">{{ $message }}</p>
    @enderror

    @if(count($wallet['gift_card_orders']) > 0)
        <h2 class="text-xl font-semibold mb-3">{{ __('public.Gift cards you bought') }}</h2>
        <div class="mb-8 grid gap-2" data-testid="gift-card-orders">
            @foreach($wallet['gift_card_orders'] as $order)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-white p-3">
                    <div>
                        <span class="font-medium">{{ $order['currency'] }} {{ $order['amount'] }}</span>
                        <span class="text-sm text-gray-500"> — {{ $order['recipient_name'] }}</span>
                    </div>
                    <span class="text-sm text-gray-600">
                        {{ __('public.'.$order['status']) }}{{ $order['delivered_to'] ? ' · '.__('public.sent to :to', ['to' => $order['delivered_to']]) : '' }}{{ $order['created_at'] ? ' · '.$order['created_at'] : '' }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="text-xl font-semibold mb-3">{{ __('public.Transactions') }}</h2>
    <div class="overflow-x-auto rounded-lg border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-brandBeige-100">
                <tr>
                    <th class="px-3 py-2 text-start">{{ __('public.Date') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('public.Type') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('public.Source') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('public.Amount') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('public.Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @if(count($wallet['transactions']) === 0)
                    <tr><td colspan="5" class="px-3 py-4 text-gray-500">{{ __('public.No wallet activity yet.') }}</td></tr>
                @endif
                @foreach($wallet['transactions'] as $row)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $row['created_at'] }}</td>
                        <td class="px-3 py-2 {{ $row['type'] === 'credit' ? 'text-green-700' : 'text-red-700' }}">{{ $row['type'] }}</td>
                        <td class="px-3 py-2">{{ str_replace('_', ' ', $row['source_type']) }}</td>
                        <td class="px-3 py-2">{{ $row['type'] === 'credit' ? '+' : '−' }}{{ $row['amount'] }}</td>
                        <td class="px-3 py-2">{{ $row['balance_after'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
