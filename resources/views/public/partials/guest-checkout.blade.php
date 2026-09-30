{{-- STATUS §5ly: buy without signing in — a name and a mobile number (Bake & Grill's guest checkout).
     $for: shop | library | gift_card; $slug: the Library item, when $for is library. --}}
<form method="POST" action="{{ route('guest-checkout') }}" class="mt-4 rounded-lg border border-gray-200 bg-white p-4 text-start" data-testid="guest-checkout">
    @csrf
    <input type="hidden" name="for" value="{{ $for }}">
    @if(! empty($slug))
        <input type="hidden" name="slug" value="{{ $slug }}">
    @endif
    <p class="font-semibold text-brandMaroon-900">{{ __('account.guest_title') }}</p>
    <p class="mb-3 text-sm text-gray-600">{{ __('account.guest_intro') }}</p>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-sm">{{ __('account.guest_name') }}
            <input name="guest_name" value="{{ old('guest_name') }}" required maxlength="120" autocomplete="name" class="form-input mt-1 w-full" dir="auto" data-testid="guest-name">
        </label>
        <label class="block text-sm">{{ __('account.guest_phone') }}
            <input name="guest_phone" value="{{ old('guest_phone') }}" required maxlength="30" inputmode="tel" autocomplete="tel" class="form-input mt-1 w-full" dir="ltr" placeholder="7XXXXXX" data-testid="guest-phone">
        </label>
    </div>
    @error('guest_phone')
        <p class="mt-2 text-sm text-red-700" role="alert" data-testid="guest-error">{{ $message }}</p>
    @enderror
    @error('guest_name')
        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror
    <button type="submit" class="btn-primary mt-3" data-testid="guest-continue">{{ __('account.guest_continue') }}</button>
</form>
