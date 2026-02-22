<x-guest-layout>

    @if(session('status'))
    <div style="margin-bottom:1rem;padding:.75rem 1rem;background:#ECFDF5;border:1px solid #6EE7B7;border-radius:.5rem;font-size:.85rem;color:#065F46">
        {{ session('status') }}
    </div>
    @endif

    <div style="margin-bottom:1.75rem">
        <h2 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0 0 .25rem">Sign In</h2>
        <p style="font-size:.85rem;color:#6B7280;margin:0">Use your email, phone number, or ID card number.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" style="display:flex;flex-direction:column;gap:1.125rem">
        @csrf

        {{-- Identifier --}}
        <div>
            <label class="auth-label" for="identifier">Email / Phone / ID Card</label>
            <input id="identifier" class="auth-input" type="text" name="identifier"
                   value="{{ old('identifier') }}" required autofocus autocomplete="username"
                   placeholder="email@example.com  ·  7972434  ·  A123456">
            @error('identifier')
            <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.375rem">
                <label class="auth-label" for="password" style="margin:0">Password</label>
                <a href="{{ route('password.otp.request') }}"
                   style="font-size:.78rem;color:#7C2D37;text-decoration:none;font-weight:500"
                   onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                    Forgot password?
                </a>
            </div>
            <div style="position:relative">
                <input id="password" class="auth-input" type="password" name="password"
                       required autocomplete="current-password" placeholder="••••••••"
                       style="padding-right:2.75rem">
                <button type="button" onclick="togglePw()" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0">
                    <svg id="pw-eye" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            @error('password')
            <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember me --}}
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
            <input type="checkbox" name="remember" style="width:1rem;height:1rem;accent-color:#7C2D37;border-radius:.25rem">
            <span style="font-size:.85rem;color:#6B7280">Remember me</span>
        </label>

        {{-- Submit --}}
        <button type="submit" class="auth-btn" style="margin-top:.25rem">
            Sign In
        </button>
    </form>

    {{-- Divider --}}
    <div style="display:flex;align-items:center;gap:.75rem;margin:1.5rem 0">
        <div style="flex:1;height:1px;background:#E5E7EB"></div>
        <span style="font-size:.75rem;color:#9CA3AF;white-space:nowrap">Admin access</span>
        <div style="flex:1;height:1px;background:#E5E7EB"></div>
    </div>

    {{-- Admin OTP --}}
    <a href="{{ route('otp.login.form') }}"
       style="display:flex;align-items:center;justify-content:center;gap:.625rem;padding:.75rem;border-radius:.625rem;border:1.5px solid rgba(124,45,55,.25);background:rgba(124,45,55,.04);color:#7C2D37;font-size:.875rem;font-weight:600;text-decoration:none;transition:background .2s"
       onmouseover="this.style.background='rgba(124,45,55,.1)'" onmouseout="this.style.background='rgba(124,45,55,.04)'">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        Admin Login with OTP
    </a>

    <script>
    function togglePw() {
        const f = document.getElementById('password');
        f.type = f.type === 'password' ? 'text' : 'password';
    }
    </script>

</x-guest-layout>
