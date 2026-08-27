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
    @if(session('success'))
        <p class="mb-4 rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>
    @endif
    @error('code')
        <p class="mb-4 rounded bg-red-50 p-3 text-red-700">{{ $message }}</p>
    @enderror

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
