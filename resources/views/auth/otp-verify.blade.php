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
        <a href="{{ route('login') }}" style="font-size:.82rem;color:#6B7280;text-decoration:none">← {{ __('security.challenge_back') }}</a>
    </div>
@else

    @if(session('success'))
    <div style="margin-bottom:1rem;padding:.75rem 1rem;background:#ECFDF5;border:1px solid #6EE7B7;border-radius:.5rem;font-size:.85rem;color:#065F46">
        {{ session('success') }}
    </div>
    @endif

    <div style="margin-bottom:1.75rem">
        <h2 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0 0 .375rem">Enter OTP Code</h2>
        <p style="font-size:.85rem;color:#6B7280;margin:0">
            We sent a 6-digit code to
            <strong style="color:#374151">{{ session('otp_login_identifier', 'your contact') }}</strong>
        </p>
    </div>

    <form method="POST" action="{{ route('otp.verify') }}" style="display:flex;flex-direction:column;gap:1.25rem">
        @csrf

        <div>
            <label class="auth-label" for="code">6-Digit Code</label>
            <input id="code" class="auth-input" type="text" name="code"
                   required autofocus maxlength="6" pattern="[0-9]{6}"
                   inputmode="numeric" autocomplete="one-time-code"
                   placeholder="0  0  0  0  0  0"
                   style="text-align:center;font-size:1.75rem;font-weight:700;letter-spacing:.5em;padding:.75rem">
            @error('code')
            <p class="auth-error">{{ $message }}</p>
            @enderror
            <p style="font-size:.75rem;color:#9CA3AF;margin-top:.375rem;text-align:center">Code expires in 15 minutes. Auto-submits when 6 digits are entered.</p>
        </div>

        <button type="submit" class="auth-btn">
            Verify &amp; Sign In
        </button>
    </form>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1.25rem">
        <a href="{{ route('otp.login.form') }}"
           style="font-size:.82rem;color:#6B7280;text-decoration:none"
           onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#6B7280'">
            ← Use different account
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
