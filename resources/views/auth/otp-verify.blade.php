<x-guest-layout>
@if($twoFactor ?? false)
    {{-- STATUS §5lk: the second step — a code from the authenticator app, or a recovery code. --}}
    <div style="margin-bottom:1.75rem">
        <h2 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0 0 .375rem">{{ __('security.challenge_title') }}</h2>
        <p style="font-size:.85rem;color:#6B7280;margin:0">{{ __('security.challenge_intro') }}</p>
    </div>
    <form method="POST" action="{{ route('two-factor.challenge.store') }}" style="display:flex;flex-direction:column;gap:1.25rem" data-testid="two-factor-form">
        @csrf
        <div>
            <label class="auth-label" for="code">{{ __('security.code_label') }}</label>
            <input id="code" class="auth-input" type="text" name="code" required autofocus maxlength="32"
                   autocomplete="one-time-code" inputmode="text" dir="ltr"
                   style="text-align:center;font-size:1.5rem;font-weight:700;letter-spacing:.3em;padding:.75rem" data-testid="two-factor-code">
            @error('code')
            <p class="auth-error" data-testid="two-factor-error">{{ $message }}</p>
            @enderror
            <p style="font-size:.75rem;color:#9CA3AF;margin-top:.375rem;text-align:center">{{ __('security.challenge_recovery_hint') }}</p>
        </div>
        <button type="submit" class="auth-btn" data-testid="two-factor-submit">{{ __('security.challenge_button') }}</button>
    </form>
    <div style="margin-top:1.25rem">
        <a href="{{ route('login') }}" style="font-size:.82rem;color:#6B7280;text-decoration:none"><span class="rtl-flip" aria-hidden="true">←</span> {{ __('security.challenge_back') }}</a>
    </div>
@else

    @if(session('success'))
    <div role="status" style="margin-bottom:1rem;padding:.75rem 1rem;background:#ECFDF5;border:1px solid #6EE7B7;border-radius:.5rem;font-size:.85rem;color:#065F46" data-testid="otp-sent">
        {{ session('success') }}
    </div>
    @endif

    <div style="margin-bottom:1.75rem">
        <h2 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0 0 .375rem">{{ __('signin.code_title') }}</h2>
        {{-- The contact where the language puts it, set left to right (slice SI1). --}}
        <p style="font-size:.85rem;color:#6B7280;margin:0">@foreach(preg_split('/(:contact)/', __('signin.code_sent_to'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if($part === ':contact' && session('otp_login_identifier'))<strong style="color:#374151" dir="ltr">{{ session('otp_login_identifier') }}</strong>@elseif($part === ':contact')<strong style="color:#374151">{{ __('signin.your_contact') }}</strong>@else{{ $part }}@endif{{ '' }}@endforeach</p>
    </div>

    <form method="POST" action="{{ route('otp.verify') }}" style="display:flex;flex-direction:column;gap:1.25rem">
        @csrf

        <div>
            <label class="auth-label" for="code">{{ __('signin.code_label') }}</label>
            <input id="code" class="auth-input" type="text" name="code" dir="ltr"
                   required autofocus maxlength="6" pattern="[0-9]{6}"
                   inputmode="numeric" autocomplete="one-time-code"
                   placeholder="0  0  0  0  0  0"
                   style="text-align:center;font-size:1.75rem;font-weight:700;letter-spacing:.5em;padding:.75rem">
            @error('code')
            <p class="auth-error" role="alert">{{ $message }}</p>
            @enderror
            {{-- A new code the code service refused to send comes back on `contact`; this page said it nowhere (slice SI1). --}}
            @error('contact')
            <p class="auth-error" role="alert" data-testid="otp-resend-refused">{{ $message }}</p>
            @enderror
            <p style="font-size:.75rem;color:#9CA3AF;margin-top:.375rem;text-align:center">{{ __('signin.code_hint', ['minutes' => 15]) }}</p>
        </div>

        <button type="submit" class="auth-btn">
            {{ __('signin.verify_sign_in') }}
        </button>
    </form>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1.25rem">
        <a href="{{ route('otp.login.form') }}"
           style="font-size:.82rem;color:#6B7280;text-decoration:none"
           onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#6B7280'">
            <span class="rtl-flip" aria-hidden="true">←</span> {{ __('signin.other_account') }}
        </a>
        {{-- C16 slice N3: the resend wait counts down here and the button is held until it ends. --}}
        <form method="POST" action="{{ route('otp.resend') }}" style="display:inline" data-testid="otp-resend-form">
            @csrf
            <button type="submit" id="otp-resend" data-testid="otp-resend"
                    data-retry-after="{{ (int) ($retryAfter ?? 0) }}"
                    data-label="{{ __('security.otp_resend') }}"
                    data-waiting="{{ __('security.otp_resend_in', ['time' => ':time']) }}"
                    @if(($retryAfter ?? 0) > 0) disabled aria-disabled="true" @endif
                    style="background:none;border:none;cursor:pointer;font-size:.82rem;color:#7C2D37;font-weight:600;text-decoration:underline;padding:0">
                {{ ($retryAfter ?? 0) > 0 ? __('security.otp_resend_in', ['time' => gmdate('i:s', (int) $retryAfter)]) : __('security.otp_resend') }}
            </button>
        </form>
    </div>

    <script>
    const codeInput = document.getElementById('code');
    codeInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '');
        if (this.value.length === 6) this.form.submit();
    });
    (function () {
        const button = document.getElementById('otp-resend');
        let left = parseInt(button.dataset.retryAfter, 10) || 0;
        if (left <= 0) return;
        const tick = () => {
            if (left <= 0) {
                button.disabled = false;
                button.removeAttribute('aria-disabled');
                button.style.opacity = '';
                button.textContent = button.dataset.label;
                return;
            }
            const m = Math.floor(left / 60), s = left % 60;
            button.textContent = button.dataset.waiting.replace(':time', `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`);
            button.disabled = true;
            button.style.opacity = '.6';
            left -= 1;
            setTimeout(tick, 1000);
        };
        tick();
    })();
    </script>

@endif
</x-guest-layout>
