@extends('public.layouts.public')
@section('title', 'Confirm Your Enrollment')
@section('content')
<section style="background:#F8F5F2;min-height:80vh;padding:3rem 1rem">
  <div style="max-width:32rem;margin:0 auto">

    <div style="background:white;border-radius:1rem;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden">

      {{-- Header --}}
      <div style="padding:1.5rem 1.75rem;background:linear-gradient(135deg,#3D1219,#7C2D37);color:white">
        <h1 style="font-size:1.25rem;font-weight:800;margin:0 0 .25rem">Confirm Your Enrollment</h1>
        <p style="font-size:.85rem;color:rgba(255,255,255,.75);margin:0">
          @if($otpSent) Enter the OTP sent to your mobile to confirm.
          @else Read and accept the terms below, then request your OTP.
          @endif
        </p>
      </div>

      {{-- Course summary --}}
      <div style="padding:1.25rem 1.75rem;background:#FFFBF0;border-bottom:1px solid #F3F4F6">
        <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#92400E;margin:0 0 .625rem">Enrollment Summary</p>
        @foreach($courses as $course)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ !$loop->last ? 'border-bottom:1px solid #F3F4F6' : '' }}">
          <span style="font-weight:600;color:#111827;font-size:.9rem">{{ $course->title }}</span>
          <span style="font-weight:700;color:#7C2D37;font-size:.9rem">
            {{ $course->fee && $course->fee > 0 ? 'MVR '.number_format($course->fee,2) : 'Free' }}
          </span>
        </div>
        @endforeach
        @if($totalFee > 0)
        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:.625rem;margin-top:.25rem;border-top:2px solid #E5E7EB">
          <span style="font-weight:700;color:#111827;font-size:.9rem">Total</span>
          <span style="font-weight:800;color:#7C2D37;font-size:1rem">MVR {{ number_format($totalFee,2) }}</span>
        </div>
        @endif
      </div>

      <div style="padding:1.75rem">

        {{-- Alerts --}}
        @if($errors->any())
        <div style="margin-bottom:1.25rem;padding:.875rem;background:#FEF2F2;border:1px solid #FECACA;border-radius:.5rem;font-size:.85rem;color:#991B1B">
          {{ $errors->first() }}
        </div>
        @endif
        @if(session('success'))
        <div style="margin-bottom:1.25rem;padding:.875rem;background:#ECFDF5;border:1px solid #6EE7B7;border-radius:.5rem;font-size:.85rem;color:#065F46">
          {{ session('success') }}
        </div>
        @endif
        @if(session('info'))
        <div style="margin-bottom:1.25rem;padding:.875rem;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:.5rem;font-size:.85rem;color:#1D4ED8">
          {{ session('info') }}
        </div>
        @endif

        {{-- ══════════════════════════════════════
             STEP 1 — Terms & Conditions + Send OTP
             Shown when OTP has NOT been sent yet
        ══════════════════════════════════════ --}}
        @if(! $otpSent)

        {{-- T&C block --}}
        <div style="padding:1.25rem;background:#FFFBF0;border:1.5px solid #FDE68A;border-radius:.75rem;margin-bottom:1.5rem">
          <p style="font-size:.8rem;font-weight:700;color:#92400E;margin:0 0 .75rem;text-transform:uppercase;letter-spacing:.06em">
            📋 Please read before proceeding
          </p>

          <div style="font-size:.83rem;color:#374151;line-height:1.7;margin-bottom:1rem;max-height:10rem;overflow-y:auto;padding-right:.25rem">
            <p style="margin:0 0 .5rem"><strong>Enrollment Terms:</strong></p>
            <ul style="margin:0 0 .75rem;padding-left:1.25rem">
              <li>Enrollment is subject to seat availability and admin approval.</li>
              <li>Course fees are non-refundable unless the course is cancelled by Akuru Institute.</li>
              <li>You must attend the required number of sessions to receive a certificate.</li>
              <li>By enrolling, you agree to abide by the rules and conduct policies of Akuru Institute.</li>
            </ul>
            <p style="margin:0">
              Full details:
              <a href="{{ route('public.page.show','terms') }}" target="_blank" style="color:#7C2D37;font-weight:600">Terms &amp; Conditions</a>
              &nbsp;·&nbsp;
              <a href="{{ route('public.page.show','refund-policy') }}" target="_blank" style="color:#7C2D37;font-weight:600">Refund Policy</a>
            </p>
          </div>

          <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer">
            <input id="terms-check" type="checkbox" value="1"
                   style="width:1.125rem;height:1.125rem;margin-top:.125rem;flex-shrink:0;accent-color:#7C2D37">
            <span style="font-size:.83rem;color:#374151;line-height:1.5">
              I have read and agree to the <strong>Terms &amp; Conditions</strong> and <strong>Refund Policy</strong>.
            </span>
          </label>
        </div>

        {{-- Mobile number info --}}
        <div style="display:flex;align-items:center;gap:.75rem;padding:.875rem;background:#F9FAFB;border-radius:.625rem;margin-bottom:1.5rem">
          <div style="width:2.25rem;height:2.25rem;background:#FAECED;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="16" height="16" fill="none" stroke="#7C2D37" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <p style="font-size:.75rem;color:#6B7280;margin:0">OTP will be sent to</p>
            <p style="font-weight:600;color:#111827;font-size:.875rem;margin:0">{{ $maskedContact }}</p>
          </div>
        </div>

        {{-- Send OTP button (enabled only after checkbox is ticked) --}}
        <form method="POST" action="{{ route('courses.register.enroll.resend') }}" id="send-otp-form">
          @csrf
          <button id="send-otp-btn" type="submit" disabled
                  style="width:100%;padding:.875rem;border-radius:.625rem;font-weight:700;font-size:1rem;cursor:not-allowed;border:none;background:#D1D5DB;color:#6B7280;transition:all .2s">
            ✔ Accept &amp; Send OTP
          </button>
        </form>
        <p style="font-size:.75rem;color:#9CA3AF;text-align:center;margin:.5rem 0 0">
          You must accept the terms above to receive the OTP.
        </p>

        {{-- ══════════════════════════════════════
             STEP 2 — Enter OTP
             Shown after OTP has been sent
        ══════════════════════════════════════ --}}
        @else

        {{-- OTP sent to --}}
        <div style="display:flex;align-items:center;gap:.75rem;padding:.875rem;background:#F9FAFB;border-radius:.625rem;margin-bottom:1.25rem">
          <div style="width:2.25rem;height:2.25rem;background:#FAECED;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="16" height="16" fill="none" stroke="#7C2D37" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <p style="font-size:.75rem;color:#6B7280;margin:0">OTP sent to</p>
            <p style="font-weight:600;color:#111827;font-size:.875rem;margin:0">{{ $maskedContact }}</p>
          </div>
        </div>

        <form method="POST" action="{{ route('courses.register.enroll.confirm') }}">
          @csrf

          {{-- Hidden T&C — already accepted in step 1 --}}
          <input type="hidden" name="terms_accepted" value="1">

          {{-- OTP input --}}
          <div style="margin-bottom:1.25rem">
            <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.5rem">Enter 6-Digit OTP</label>
            <input id="otp-code" type="text" name="otp_code" required maxlength="6" pattern="[0-9]{6}"
                   inputmode="numeric" autocomplete="off"
                   placeholder="0  0  0  0  0  0"
                   autofocus
                   style="width:100%;padding:.875rem;border:1.5px solid #E5E7EB;border-radius:.625rem;font-size:1.75rem;font-weight:700;text-align:center;letter-spacing:.5em;outline:none;box-sizing:border-box"
                   onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
            @error('otp_code')<p style="font-size:.75rem;color:#DC2626;margin:.25rem 0 0">{{ $message }}</p>@enderror
            <p style="font-size:.75rem;color:#9CA3AF;margin:.375rem 0 0;text-align:center">
              Code expires in 15 minutes &nbsp;·&nbsp; Auto-submits on 6 digits
            </p>
          </div>

          <button type="submit"
                  style="width:100%;padding:.875rem;border-radius:.625rem;font-weight:700;font-size:1rem;cursor:pointer;border:none;background:linear-gradient(135deg,#7C2D37,#5A1F28);color:white;transition:opacity .2s"
                  onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
            {{ $totalFee > 0 ? 'Confirm &amp; Proceed to Payment' : 'Confirm Enrollment' }}
          </button>
        </form>

        {{-- Resend --}}
        <div style="text-align:center;margin-top:1rem">
          <form method="POST" action="{{ route('courses.register.enroll.resend') }}" style="display:inline">
            @csrf
            <button type="submit" style="background:none;border:none;cursor:pointer;font-size:.82rem;color:#7C2D37;font-weight:600;text-decoration:underline;padding:0">
              Resend OTP
            </button>
          </form>
        </div>

        {{-- BML compliance: card logos, country, currency, security statement (Req #1,4,5,10,11) --}}
        @if($totalFee > 0)
            <x-payment-trust-bar />
        @endif

        @endif
      </div>
    </div>
  </div>
</section>

<script>
@if(! $otpSent)
const termsCheck = document.getElementById('terms-check');
const sendBtn    = document.getElementById('send-otp-btn');
termsCheck.addEventListener('change', function () {
  if (this.checked) {
    sendBtn.disabled = false;
    sendBtn.style.background = 'linear-gradient(135deg,#7C2D37,#5A1F28)';
    sendBtn.style.color = 'white';
    sendBtn.style.cursor = 'pointer';
  } else {
    sendBtn.disabled = true;
    sendBtn.style.background = '#D1D5DB';
    sendBtn.style.color = '#6B7280';
    sendBtn.style.cursor = 'not-allowed';
  }
});
@else
const otpInput = document.getElementById('otp-code');
otpInput.addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '');
  if (this.value.length === 6) this.form.submit();
});
@endif
</script>
@endsection
