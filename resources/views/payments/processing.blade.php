@extends('public.layouts.public')

@section('title', 'Payment processing')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-md text-center">
        <div class="card p-8">
            <div id="payment-success" class="hidden">
                <p class="text-green-600 font-semibold text-lg mb-2">Payment successful</p>
                <p class="text-gray-600 mb-4">Your registration has been confirmed.</p>
                <a href="{{ route('courses.register.complete') }}" class="inline-block bg-brandMaroon-600 text-white py-2 px-6 rounded-lg hover:bg-brandMaroon-700">Continue</a>
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
