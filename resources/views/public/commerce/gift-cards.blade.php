@extends('public.layouts.public')

@section('title', __('public.Gift cards') . ' - ' . config('app.name'))
@section('description', __('public.Give the gift of reading — a gift card for books, articles and research in the Akuru Digital Library.'))

@section('content')
<div class="container mx-auto px-4 py-10 max-w-3xl">
    <nav class="text-sm text-gray-500 mb-6 flex gap-2 items-center flex-wrap">
        <a href="{{ route('public.library.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Digital Library') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ __('public.Gift cards') }}</span>
    </nav>

    <h1 class="text-3xl font-bold text-brandMaroon-900 mb-2">{{ __('public.Give the gift of reading') }}</h1>
    <p class="text-gray-600 mb-8">{{ __('public.A gift card becomes wallet money the moment it is redeemed, to spend on any book, article or research paper in the library. The code is sent to the person you name as soon as the bank confirms your payment.') }}</p>

    @if(session('error'))
        <p class="mb-4 rounded bg-red-50 p-3 text-red-700">{{ session('error') }}</p>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded bg-red-50 p-3 text-red-700">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if(! $signedIn)
        <div class="mb-6 rounded-lg border bg-brandBeige-50 p-6 text-center">
            <p class="mb-3 text-gray-700">{{ __('public.Sign in to buy a gift card.') }}</p>
            <a href="{{ route('login') }}" class="btn-primary">{{ __('public.Sign in') }}</a>
        </div>
    @endif

    <form method="POST" action="{{ route('public.gift-cards.purchase') }}" class="rounded-lg border bg-white p-6 grid gap-5" data-testid="gift-card-form">
        @csrf
        <fieldset>
            <legend class="mb-2 text-sm font-medium text-gray-700">{{ __('public.Amount (MVR)') }}</legend>
            <div class="flex flex-wrap gap-2 mb-3" role="group">
                @foreach($presets as $preset)
                    <button type="button" class="btn-secondary gift-preset" data-amount="{{ $preset }}">MVR {{ number_format($preset) }}</button>
                @endforeach
            </div>
            <input type="number" name="amount" id="gift-amount" class="form-input w-40" min="{{ $min }}" max="{{ $max }}" step="1" value="{{ old('amount', $presets[0] ?? $min) }}" required>
            <p class="mt-1 text-xs text-gray-500">{{ __('public.Any whole amount from MVR :min to MVR :max.', ['min' => number_format($min), 'max' => number_format($max)]) }}</p>
        </fieldset>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm text-gray-700">
                {{ __('public.Who is it for?') }}
                <input type="text" name="recipient_name" class="form-input mt-1 w-full" value="{{ old('recipient_name') }}" maxlength="120" required>
            </label>
            <label class="text-sm text-gray-700">
                {{ __('public.Their email') }}
                <input type="email" name="recipient_email" class="form-input mt-1 w-full" value="{{ old('recipient_email') }}" maxlength="190">
            </label>
            <label class="text-sm text-gray-700">
                {{ __('public.Their mobile (optional)') }}
                <input type="text" name="recipient_mobile" class="form-input mt-1 w-full" value="{{ old('recipient_mobile') }}" maxlength="20" placeholder="+960">
            </label>
            <label class="text-sm text-gray-700 md:col-span-2">
                {{ __('public.A message (optional)') }}
                <textarea name="message" class="form-input mt-1 w-full" rows="3" maxlength="500">{{ old('message') }}</textarea>
            </label>
        </div>

        <p class="text-xs text-gray-500">{{ __('public.Gift cards are paid by card only. Discount codes and wallet money cannot buy a gift card.') }} <a href="{{ route('public.page.show', 'gift-card-terms') }}" class="underline">{{ __('public.Gift Card Terms') }}</a></p>

        <div>
            <button type="submit" class="btn-primary" @disabled(! $signedIn)>{{ __('public.Pay with card') }}</button>
        </div>
    </form>
</div>
<script>
    document.querySelectorAll('.gift-preset').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('gift-amount').value = button.dataset.amount;
        });
    });
</script>
@endsection
