<x-guest-layout>

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
        <form method="POST" action="{{ route('otp.resend') }}" style="display:inline">
            @csrf
            <button type="submit"
                    style="background:none;border:none;cursor:pointer;font-size:.82rem;color:#7C2D37;font-weight:600;text-decoration:underline;padding:0">
                Resend code
            </button>
        </form>
    </div>

    <script>
    const codeInput = document.getElementById('code');
    codeInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '');
        if (this.value.length === 6) this.form.submit();
    });
    </script>

</x-guest-layout>
