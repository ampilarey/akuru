@extends('public.layouts.public')

@section('title', 'Payment processing')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md text-center">
        <div class="card p-8">
            <div id="payment-success" class="hidden">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-green-600 font-semibold text-lg mb-1">Payment successful</p>
                <p class="text-gray-600 mb-5">Your registration has been confirmed.</p>

                @auth
                    @if(! auth()->user()->password)
                    <div class="mb-5 p-4 bg-brandMaroon-50 border border-brandMaroon-200 rounded-lg text-left">
                        <p class="text-sm font-semibold text-brandMaroon-800 mb-1">Save time next time</p>
                        <p class="text-sm text-brandMaroon-700 mb-3">Create a password so you can log in directly without needing an OTP code.</p>
                        <a href="{{ route('account.set-password') }}"
                           class="inline-block bg-brandMaroon-600 text-white text-sm py-2 px-4 rounded hover:bg-brandMaroon-700">
                            Set password
                        </a>
                        <a href="{{ route('courses.register.complete') }}"
                           class="inline-block ml-3 text-sm text-gray-500 hover:underline">
                            Skip
                        </a>
                    </div>
                    @else
                        <a href="{{ route('courses.register.complete') }}" class="inline-block bg-brandMaroon-600 text-white py-2 px-6 rounded-lg hover:bg-brandMaroon-700">Continue</a>
                    @endif
                @else
                    <a href="{{ route('courses.register.complete') }}" class="inline-block bg-brandMaroon-600 text-white py-2 px-6 rounded-lg hover:bg-brandMaroon-700">Continue</a>
                @endauth
            </div>
            <div id="payment-pending">
                <p class="text-brandMaroon-700 font-semibold mb-2">Payment processing</p>
                <p class="text-gray-600 text-sm mb-4">Please wait while we confirm your payment. This page will update automatically.</p>
                <div class="animate-pulse h-2 bg-gray-200 rounded w-3/4 mx-auto"></div>
            </div>
            <div id="payment-timeout" class="hidden">
                <p class="text-gray-600">If your payment was completed, your registration will be confirmed shortly. You can close this page and return later.</p>
                <a href="{{ route('public.home') }}" class="inline-block mt-4 text-brandMaroon-600 hover:underline">Return to home</a>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function() {
    var statusUrl = '{{ route("payments.status.by_id", $payment) }}';
    var successEl = document.getElementById('payment-success');
    var pendingEl = document.getElementById('payment-pending');
    var timeoutEl = document.getElementById('payment-timeout');
    var attempts = 0;
    var maxAttempts = 20;

    function check() {
        return fetch(statusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'paid' || data.status === 'confirmed') {
                    pendingEl.classList.add('hidden');
                    successEl.classList.remove('hidden');
                    return true;
                }
                return false;
            })
            .catch(function() { return false; });
    }

    var interval = setInterval(function() {
        attempts++;
        check().then(function(done) {
            if (done) clearInterval(interval);
        });
        if (attempts >= maxAttempts) {
            clearInterval(interval);
            pendingEl.classList.add('hidden');
            timeoutEl.classList.remove('hidden');
        }
    }, 3000);
    check();
})();
</script>
@endpush
@endsection
