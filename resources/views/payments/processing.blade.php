@extends('public.layouts.public')

@section('title', __('payments.processing_title'))

@section('content')
{{--
    Back from the bank (slice PY1). Besides being English throughout, the page
    said "Your registration has been confirmed" for every payment, a family's
    fee and a course the office still approves among them, and sent everyone
    on to the registration's last page, which only a registration's own
    payment has the session for: the controller now says where each one goes.
    It offered a password to nobody, because it asked whether the account had
    none and every account has one (an account made by a code has a random
    one, and `force_password_change`). And a payment the bank turned down
    waited a minute to say "If your payment was completed…".
--}}
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md text-center">
        <div class="card p-8">
            <div id="payment-success" @class(['hidden' => ! $payment->isConfirmed()]) data-testid="payment-confirmed">
                <div style="width:3.5rem;height:3.5rem;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem" aria-hidden="true">
                    <svg style="width:1.75rem;height:1.75rem;color:#16a34a;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-green-600 font-semibold text-lg mb-1">{{ __('payments.confirmed') }}</p>
                <p class="text-gray-600 mb-3">{{ __('payments.confirmed_hint') }}</p>
                <p class="text-xs text-gray-500 mb-4">{{ __('payments.keep_receipt') }}</p>

                @if(auth()->user()?->force_password_change)
                    <div class="mb-5 p-4 bg-brandMaroon-50 border border-brandMaroon-200 rounded-lg text-start" data-testid="payment-set-password">
                        <p class="text-sm font-semibold text-brandMaroon-800 mb-1">{{ __('payments.set_password_title') }}</p>
                        <p class="text-sm text-brandMaroon-700 mb-3">{{ __('payments.set_password_hint') }}</p>
                        <a href="{{ route('account.set-password') }}"
                           class="inline-block bg-brandMaroon-600 text-white text-sm py-2 px-4 rounded hover:bg-brandMaroon-700">
                            {{ __('payments.set_password') }}
                        </a>
                        <a href="{{ $nextHref }}" class="inline-block ms-3 text-sm text-gray-500 hover:underline">
                            {{ __('payments.skip') }}
                        </a>
                    </div>
                @else
                    <a href="{{ $nextHref }}" class="inline-block bg-brandMaroon-600 text-white py-2 px-6 rounded-lg hover:bg-brandMaroon-700" data-testid="payment-continue">{{ __('payments.continue') }}</a>
                @endif
            </div>
            <div id="payment-failed" @class(['hidden' => ! $notPaid]) role="alert" data-testid="payment-not-paid">
                <p class="text-red-700 font-semibold mb-2">{{ __('payments.not_paid') }}</p>
                <p class="text-gray-600 text-sm mb-4">{{ __('payments.not_paid_hint') }}</p>
                <a href="{{ $nextHref }}" class="inline-block bg-brandMaroon-600 text-white py-2 px-6 rounded-lg hover:bg-brandMaroon-700">{{ __('payments.continue') }}</a>
            </div>
            <div id="payment-pending" @class(['hidden' => $payment->isConfirmed() || $notPaid]) role="status" data-testid="payment-checking">
                <p class="text-brandMaroon-700 font-semibold mb-2">{{ __('payments.checking') }}</p>
                <p class="text-gray-600 text-sm mb-4">{{ __('payments.checking_hint') }}</p>
                <div class="animate-pulse h-2 bg-gray-200 rounded w-3/4 mx-auto" aria-hidden="true"></div>
            </div>
            <div id="payment-timeout" class="hidden" role="status" data-testid="payment-slow">
                <p class="text-gray-800 font-semibold mb-2">{{ __('payments.slow') }}</p>
                <p class="text-gray-600">{{ __('payments.slow_hint') }}</p>
                <a href="{{ route('public.home') }}" class="inline-block mt-4 text-brandMaroon-600 hover:underline">{{ __('payments.back_home') }}</a>
            </div>

            <x-payment-trust-bar />
        </div>
    </div>
</section>

@push('scripts')
<script>
(function() {
    var statusUrl = @js(route('payments.status.by_id', $payment));
    var successEl = document.getElementById('payment-success');
    var failedEl = document.getElementById('payment-failed');
    var pendingEl = document.getElementById('payment-pending');
    var timeoutEl = document.getElementById('payment-timeout');
    // Decided on the server already: nothing to wait for.
    if (pendingEl.classList.contains('hidden')) return;

    var attempts = 0;
    var maxAttempts = 20;
    var done = false;

    function show(el) {
        pendingEl.classList.add('hidden');
        el.classList.remove('hidden');
    }

    function check() {
        return fetch(statusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (done) return true;
                if (data.confirmed) {
                    done = true;
                    show(successEl);
                } else if (['failed', 'cancelled', 'expired'].indexOf(data.status) !== -1) {
                    done = true;
                    show(failedEl);
                }
                return done;
            })
            .catch(function() { return false; });
    }

    var interval = setInterval(function() {
        if (done) { clearInterval(interval); return; }
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(interval);
            done = true;
            show(timeoutEl);
            return;
        }
        check();
    }, 3000);
    check();
})();
</script>
@endpush
@endsection
