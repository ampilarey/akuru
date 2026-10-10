@extends('public.layouts.public')
@section('title', __('register.confirm_title'))
@section('content')
<section style="background:#F8F5F2;min-height:80vh;padding:3rem 1rem">
  <div style="max-width:32rem;margin:0 auto">

    <div style="background:white;border-radius:1rem;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden">

      {{-- Header --}}
      <div style="padding:1.5rem 1.75rem;background:linear-gradient(135deg,#3D1219,#7C2D37);color:white">
        <h1 style="font-size:1.25rem;font-weight:800;margin:0 0 .25rem">{{ __('register.confirm_title') }}</h1>
        <p style="font-size:.85rem;color:rgba(255,255,255,.75);margin:0">{{ __($otpSent ? 'register.confirm_enter_code' : 'register.confirm_read_terms') }}</p>
      </div>

      {{-- Course summary --}}
      <div style="padding:1.25rem 1.75rem;background:#FFFBF0;border-bottom:1px solid #F3F4F6">
        <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#92400E;margin:0 0 .625rem">{{ __('register.summary') }}</p>
        @foreach($courses as $course)
        <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;padding:.5rem 0;{{ !$loop->last ? 'border-bottom:1px solid #F3F4F6' : '' }}">
          <span style="font-weight:600;color:#111827;font-size:.9rem">{{ $course->title }}</span>
          <span style="font-weight:700;color:#7C2D37;font-size:.9rem">
            {{ $course->fee && $course->fee > 0 ? __('register.money', ['amount' => number_format($course->fee, 2)]) : __('register.price_free') }}
          </span>
        </div>
        @endforeach
        @if($totalFee > 0)
        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:.625rem;margin-top:.25rem;border-top:2px solid #E5E7EB">
          <span style="font-weight:700;color:#111827;font-size:.9rem">{{ __('register.total') }}</span>
          <span style="font-weight:800;color:#7C2D37;font-size:1rem">{{ __('register.money', ['amount' => number_format($totalFee, 2)]) }}</span>
        </div>
        @endif
      </div>

      <div style="padding:1.75rem">

        {{-- Alerts. A code sent is flashed as success, which the layout shows (printed here too, it showed twice); info, which it does not show, is said here (slice RG1c). --}}
        @if($errors->any())
        <div role="alert" style="margin-bottom:1.25rem;padding:.875rem;background:#FEF2F2;border:1px solid #FECACA;border-radius:.5rem;font-size:.85rem;color:#991B1B">
          {{ $errors->first() }}
        </div>
        @endif
        @if(session('info'))
        <div role="status" data-testid="confirm-info" style="margin-bottom:1.25rem;padding:.875rem;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:.5rem;font-size:.85rem;color:#1D4ED8">
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
            <span aria-hidden="true">📋</span> {{ __('register.read_first') }}
          </p>

          <div style="font-size:.83rem;color:#374151;line-height:1.7;margin-bottom:1rem;max-height:10rem;overflow-y:auto;padding-inline-end:.25rem">
            <p style="margin:0 0 .5rem"><strong>{{ __('register.enrollment_terms') }}</strong></p>
            <ul style="margin:0 0 .75rem;padding-inline-start:1.25rem">
              <li>{{ __('register.term_seats') }}</li>
              <li>{{ __('register.term_fees') }}</li>
              <li>{{ __('register.term_attendance') }}</li>
              <li>{{ __('register.term_conduct') }}</li>
            </ul>
            <p style="margin:0">
              {{ __('register.full_details') }}
              <a href="{{ route('public.page.show','terms') }}" target="_blank" style="color:#7C2D37;font-weight:600">{{ __('register.terms_link') }}</a>
              <span class="mx-1" aria-hidden="true">·</span>
              <a href="{{ route('public.page.show','refund-policy') }}" target="_blank" style="color:#7C2D37;font-weight:600">{{ __('register.refunds_link') }}</a>
            </p>
          </div>

          <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer">
            <input id="terms-check" type="checkbox" value="1" @checked($termsAccepted ?? false)
                   style="width:1.125rem;height:1.125rem;margin-top:.125rem;flex-shrink:0;accent-color:#7C2D37">
            {{-- The book's sentence, the two documents named in bold where its :terms and :refunds stand (slice RG1c). --}}
            <span style="font-size:.83rem;color:#374151;line-height:1.5">@foreach(preg_split('/(:terms|:refunds)/', __('register.terms_agree'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if($part === ':terms')<strong>{{ __('register.terms_link') }}</strong>@elseif($part === ':refunds')<strong>{{ __('register.refunds_link') }}</strong>@else{{ $part }}@endif{{ '' }}@endforeach</span>
          </label>
        </div>

        {{-- Contact selector (shown when user has more than one verified contact) --}}
        @if($availableContacts->count() > 1)
        <fieldset style="margin:0 0 1.25rem;padding:0;border:0">
          <legend style="font-size:.75rem;font-weight:600;color:#374151;margin:0 0 .5rem;padding:0">{{ __('register.send_code_to') }}</legend>
          <div style="display:flex;flex-direction:column;gap:.5rem">
            @foreach($contactsForDisplay as $c)
            <label style="display:flex;align-items:center;gap:.625rem;padding:.625rem .875rem;border:1.5px solid {{ $c->id == $currentContactId ? '#7C2D37' : '#E5E7EB' }};border-radius:.5rem;cursor:pointer;background:{{ $c->id == $currentContactId ? '#FDF2F3' : 'white' }}">
              <input type="radio" name="contact_choice" value="{{ $c->id }}"
                     {{ $c->id == $currentContactId ? 'checked' : '' }}
                     onchange="selectContact({{ $c->id }})"
                     style="accent-color:#7C2D37;width:1rem;height:1rem">
              <div>
                <span style="font-size:.8rem;font-weight:600;color:#111827">
                  <span aria-hidden="true">{{ $c->type === 'mobile' ? '📱' : '✉️' }}</span> {{ __($c->type === 'mobile' ? 'register.mobile' : 'register.contact_email') }}
                </span>
                <span style="font-size:.8rem;color:#6B7280;margin-inline-start:.375rem" dir="ltr">{{ $c->masked }}</span>
              </div>
            </label>
            @endforeach
          </div>
        </fieldset>
        @else
        {{-- Single contact — just show it --}}
        <div style="display:flex;align-items:center;gap:.75rem;padding:.875rem;background:#F9FAFB;border-radius:.625rem;margin-bottom:1.5rem">
          <div style="width:2.25rem;height:2.25rem;background:#FAECED;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="16" height="16" fill="none" stroke="#7C2D37" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <p style="font-size:.75rem;color:#6B7280;margin:0">{{ __('register.code_goes_to') }}</p>
            <p style="font-weight:600;color:#111827;font-size:.875rem;margin:0" dir="ltr">{{ $maskedContact }}</p>
          </div>
        </div>
        @endif

        {{-- Send OTP button (enabled only after checkbox is ticked) --}}
        <form method="POST" action="{{ route('courses.register.enroll.resend') }}" id="send-otp-form">
          @csrf
          <input type="hidden" name="contact_id" id="selected-contact-id" value="{{ $currentContactId }}">
          <button id="send-otp-btn" type="submit" disabled data-testid="send-enroll-code"
                  style="width:100%;padding:.875rem;border-radius:.625rem;font-weight:700;font-size:1rem;cursor:not-allowed;border:none;background:#D1D5DB;color:#6B7280;transition:all .2s">
            <span aria-hidden="true">✔</span> {{ __('register.accept_send_code') }}
          </button>
        </form>
        <p style="font-size:.75rem;color:#9CA3AF;text-align:center;margin:.5rem 0 0">{{ __('register.accept_first') }}</p>

        {{-- ══════════════════════════════════════
             STEP 2 — Enter OTP
             Shown after OTP has been sent
        ══════════════════════════════════════ --}}
        @else

        {{-- OTP sent to --}}
        <div style="display:flex;align-items:center;gap:.75rem;padding:.875rem;background:#F9FAFB;border-radius:.625rem;margin-bottom:1.25rem">
          <div style="width:2.25rem;height:2.25rem;background:#FAECED;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="16" height="16" fill="none" stroke="#7C2D37" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <p style="font-size:.75rem;color:#6B7280;margin:0">{{ __('register.code_sent_to') }}</p>
            <p style="font-weight:600;color:#111827;font-size:.875rem;margin:0" dir="ltr">{{ $maskedContact }}</p>
          </div>
        </div>

        <form method="POST" action="{{ route('courses.register.enroll.confirm') }}">
          @csrf

          {{-- Hidden T&C — already accepted in step 1 --}}
          <input type="hidden" name="terms_accepted" value="1">

          {{-- OTP input --}}
          <div style="margin-bottom:1.25rem">
            <label for="otp-code" style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.5rem">{{ __('register.code') }}</label>
            <input id="otp-code" type="text" name="otp_code" required maxlength="6" pattern="[0-9]{6}"
                   inputmode="numeric" autocomplete="one-time-code" dir="ltr"
                   placeholder="0  0  0  0  0  0"
                   autofocus
                   style="width:100%;padding:.875rem;border:1.5px solid #E5E7EB;border-radius:.625rem;font-size:1.75rem;font-weight:700;text-align:center;letter-spacing:.5em;outline:none;box-sizing:border-box"
                   onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
            @error('otp_code')<p style="font-size:.75rem;color:#DC2626;margin:.25rem 0 0">{{ $message }}</p>@enderror
            <p style="font-size:.75rem;color:#9CA3AF;margin:.375rem 0 0;text-align:center">{{ __('register.code_lasts') }}</p>
          </div>

          <button type="submit" id="confirm-btn" data-testid="confirm-enrollment"
                  style="width:100%;padding:.875rem;border-radius:.625rem;font-weight:700;font-size:1rem;cursor:pointer;border:none;background:linear-gradient(135deg,#7C2D37,#5A1F28);color:white;transition:opacity .2s"
                  onmouseover="if(!this.disabled)this.style.opacity='.9'" onmouseout="this.style.opacity='1'"
                  onclick="disableConfirmForm()">
            {{ __($totalFee > 0 ? 'register.confirm_and_pay' : 'register.confirm_enrollment') }}
          </button>

          {{-- Consent notice: the book's sentence, with its two links where its :terms and :refunds stand (slice RG1c). --}}
          <p style="font-size:.75rem;color:#6B7280;text-align:center;margin:.875rem 0 0;line-height:1.6">@foreach(preg_split('/(:terms|:refunds)/', __('register.consent'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if($part === ':terms')<a href="{{ route('public.page.show','terms') }}" target="_blank" style="color:#7C2D37;font-weight:600;text-decoration:underline">{{ __('register.terms_link') }}</a>@elseif($part === ':refunds')<a href="{{ route('public.page.show','refund-policy') }}" target="_blank" style="color:#7C2D37;font-weight:600;text-decoration:underline">{{ __('register.refunds_link') }}</a>@else{{ $part }}@endif{{ '' }}@endforeach</p>

        </form>

        {{-- Resend --}}
        <div style="text-align:center;margin-top:1rem">
          <form method="POST" action="{{ route('courses.register.enroll.resend') }}" style="display:inline">
            @csrf
            <button type="submit" style="background:none;border:none;cursor:pointer;font-size:.82rem;color:#7C2D37;font-weight:600;text-decoration:underline;padding:0">
              {{ __('register.resend') }}
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
function selectContact(contactId) {
  document.getElementById('selected-contact-id').value = contactId;
  // Re-style all radio labels
  document.querySelectorAll('[name="contact_choice"]').forEach(function(radio) {
    var label = radio.closest('label');
    if (parseInt(radio.value) === contactId) {
      label.style.borderColor = '#7C2D37';
      label.style.background  = '#FDF2F3';
    } else {
      label.style.borderColor = '#E5E7EB';
      label.style.background  = 'white';
    }
  });
}

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
// Accepted on the form already (STATUS §5oc): the button is live at once.
if (termsCheck.checked) { termsCheck.dispatchEvent(new Event('change')); }
@else
const otpInput = document.getElementById('otp-code');
otpInput.addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '');
  if (this.value.length === 6) {
    disableConfirmForm();
    this.form.submit();
  }
});
@endif

function disableConfirmForm() {
  const btn = document.getElementById('confirm-btn');
  if (btn) {
    btn.disabled = true;
    btn.style.opacity = '0.6';
    btn.style.cursor  = 'not-allowed';
    btn.textContent   = @js(__('register.processing'));
  }
  // Do NOT disable the input — disabled fields are excluded from form submission
  const input = document.getElementById('otp-code');
  if (input) input.readOnly = true;
}
</script>
@endsection
