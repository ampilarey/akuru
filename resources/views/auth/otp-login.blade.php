<x-guest-layout>

    @if(session('success'))
    <div style="margin-bottom:1rem;padding:.75rem 1rem;background:#ECFDF5;border:1px solid #6EE7B7;border-radius:.5rem;font-size:.85rem;color:#065F46">
        {{ session('success') }}
    </div>
    @endif

    <div style="margin-bottom:1.75rem">
        <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(124,45,55,.08);border:1px solid rgba(124,45,55,.2);color:#7C2D37;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:.25rem .75rem;border-radius:9999px;margin-bottom:.875rem">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944"/></svg>
            Admin Access
        </div>
        <h2 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0 0 .25rem">Admin Login</h2>
        <p style="font-size:.85rem;color:#6B7280;margin:0">Enter your email or phone — we'll send a one-time code.</p>
    </div>

    <form method="POST" action="{{ route('otp.request') }}" style="display:flex;flex-direction:column;gap:1.125rem">
        @csrf

        <div>
            <label class="auth-label" for="identifier">Email or Phone Number</label>
            <input id="identifier" class="auth-input" type="text" name="identifier"
                   value="{{ old('identifier') }}" required autofocus
                   placeholder="admin@akuru.edu.mv  ·  7972434">
            @error('identifier')
            <p class="auth-error">{{ $message }}</p>
            @enderror
            <p style="font-size:.75rem;color:#9CA3AF;margin-top:.375rem">For Maldives numbers, enter the 7-digit number without country code.</p>
        </div>

        <button type="submit" class="auth-btn">
            Send OTP Code
        </button>
    </form>

    <div style="text-align:center;margin-top:1.5rem">
        <a href="{{ route('login') }}"
           style="font-size:.85rem;color:#6B7280;text-decoration:none"
           onmouseover="this.style.color='#7C2D37'" onmouseout="this.style.color='#6B7280'">
            ← Back to regular login
        </a>
    </div>

</x-guest-layout>
