<x-guest-layout>
    {{-- COMMERCE_PARITY_PLAN P1: sign in on the phone number — a password if the account has one, otherwise a code by SMS. --}}
    @if(session('status'))
        <div style="margin-bottom:1rem;padding:.75rem 1rem;background:#ECFDF5;border:1px solid #6EE7B7;border-radius:.5rem;font-size:.85rem;color:#065F46">{{ session('status') }}</div>
    @endif

    <div style="margin-bottom:1.5rem">
        <h2 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0 0 .25rem" data-testid="phone-sign-in-heading">{{ __('account.phone_title') }}</h2>
        <p style="font-size:.85rem;color:#6B7280;margin:0">{{ __('account.phone_intro') }}</p>
    </div>

    @if($mode === null)
        <form method="POST" action="{{ route('phone.sign-in.check') }}" style="display:flex;flex-direction:column;gap:1rem" data-testid="phone-step">
            @csrf
            <div>
                <label class="auth-label" for="phone">{{ __('account.guest_phone') }}</label>
                <input id="phone" class="auth-input" type="text" name="phone" value="{{ old('phone') }}" required autofocus
                       inputmode="tel" autocomplete="tel" dir="ltr" placeholder="7XXXXXX" data-testid="phone-number">
                @error('phone')<p class="auth-error" role="alert" data-testid="phone-error">{{ $message }}</p>@enderror
                @error('contact_value')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                @error('contact')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="auth-btn" data-testid="phone-continue">{{ __('account.guest_continue') }}</button>
        </form>
        @unless($codesAvailable)
            <p style="font-size:.8rem;color:#6B7280;margin-top:1rem" data-testid="phone-codes-off">{{ __('account.phone_codes_off') }}</p>
        @endunless
    @elseif($mode === 'password')
        <form method="POST" action="{{ route('login') }}" style="display:flex;flex-direction:column;gap:1rem" data-testid="password-step">
            @csrf
            <input type="hidden" name="identifier" value="{{ $phone }}">
            <input type="hidden" name="remember" value="1">
            <p style="font-size:.85rem;color:#374151;margin:0" dir="ltr">{{ $phone }}</p>
            <div>
                <label class="auth-label" for="password">{{ __('account.phone_password') }}</label>
                <input id="password" class="auth-input" type="password" name="password" required autofocus autocomplete="current-password" data-testid="phone-password">
                @error('identifier')<p class="auth-error" role="alert" data-testid="phone-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="auth-btn" data-testid="phone-sign-in">{{ __('account.phone_sign_in') }}</button>
            <a href="{{ route('password.otp.request') }}" style="font-size:.8rem;color:#7C2D37" data-testid="phone-forgot">{{ __('account.phone_forgot') }}</a>
        </form>
    @else
        <form method="POST" action="{{ route('phone.sign-in.verify') }}" style="display:flex;flex-direction:column;gap:1rem" data-testid="code-step">
            @csrf
            <p style="font-size:.85rem;color:#374151;margin:0">{{ __('account.phone_code_sent', ['phone' => $phone]) }}</p>
            <div>
                <label class="auth-label" for="code">{{ __('account.phone_code') }}</label>
                <input id="code" class="auth-input" type="text" name="code" required autofocus inputmode="numeric" autocomplete="one-time-code"
                       maxlength="6" dir="ltr" data-testid="phone-code">
                @error('code')<p class="auth-error" role="alert" data-testid="phone-error">{{ $message }}</p>@enderror
            </div>
            @unless($known)
                <div>
                    <label class="auth-label" for="name">{{ __('account.guest_name') }}</label>
                    <input id="name" class="auth-input" type="text" name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" dir="auto" data-testid="phone-name">
                </div>
            @endunless
            <button type="submit" class="auth-btn" data-testid="phone-verify">{{ __('account.phone_sign_in') }}</button>
        </form>
    @endif

    @if($mode !== null)
        <p style="text-align:center;margin-top:1.25rem"><a href="{{ route('phone.sign-in', ['again' => 1]) }}" style="font-size:.8rem;color:#6B7280" data-testid="phone-other">{{ __('account.phone_other_number') }}</a></p>
    @endif
    <p style="text-align:center;margin-top:.75rem"><a href="{{ route('login') }}" style="font-size:.8rem;color:#6B7280">{{ __('account.phone_other_ways') }}</a></p>
</x-guest-layout>
