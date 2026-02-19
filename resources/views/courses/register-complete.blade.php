@extends('public.layouts.public')

@section('title', 'Registration complete')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-2xl">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">Registration complete</h1>

            @if($paymentRef && $paymentIdForStatus)
                <div id="payment-status" class="mb-6" x-data="{
                    loading: true,
                    confirmed: false,
                    timedOut: false,
                    async init() {
                        const url = '{{ route("payments.status.by_id", ["payment" => $paymentIdForStatus]) }}';
                        for (let i = 0; i < 60; i++) {
                            try {
                                const res = await fetch(url);
                                const data = await res.json();
                                if (data.confirmed) {
                                    this.confirmed = true;
                                    this.loading = false;
                                    return;
                                }
                            } catch (e) {}
                            await new Promise(r => setTimeout(r, 3000));
                        }
                        this.loading = false;
                        this.timedOut = true;
                    }
                }">
                    <div x-show="loading" class="p-4 bg-amber-50 rounded flex items-center gap-3">
                        <svg class="animate-spin h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>Waiting for payment confirmation...</span>
                    </div>
                    <div x-show="!loading && confirmed" x-cloak class="p-4 bg-green-100 text-green-800 rounded font-medium">
                        Payment confirmed. A confirmation email has been sent if you have an email on file.
                    </div>
                    <div x-show="timedOut && !confirmed" x-cloak class="p-4 bg-amber-50 border border-amber-200 rounded">
                        <p class="text-amber-800 font-medium mb-2">Payment confirmation is taking longer than expected.</p>
                        <p class="text-amber-700 text-sm mb-3">If you completed the payment, it will be confirmed automatically once we receive notification from the bank. You can also:</p>
                        <a href="{{ route('courses.register.payment.retry', ['ref' => $paymentRef]) }}"
                           class="inline-block bg-brandMaroon-600 text-white text-sm py-2 px-4 rounded hover:bg-brandMaroon-700 mr-2">
                            Try payment again
                        </a>
                        <a href="{{ route('public.courses.index') }}" class="inline-block text-sm text-gray-600 hover:underline py-2">
                            Return to courses
                        </a>
                    </div>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Course</th>
                            <th class="text-left py-2">Status</th>
                            <th class="text-left py-2">Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $e)
                            <tr class="border-b">
                                <td class="py-2">{{ $e->course->title ?? '—' }}</td>
                                <td class="py-2">
                                    <span class="px-2 py-1 rounded text-sm
                                        {{ $e->status === 'active' ? 'bg-green-100' : ($e->status === 'pending' ? 'bg-amber-100' : 'bg-gray-100') }}">
                                        {{ ucfirst($e->status) }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    @if($e->payment_status === 'confirmed')
                                        <span class="text-green-600">Paid</span>
                                    @elseif($e->payment_status === 'pending')
                                        <span class="text-amber-600">Pending</span>
                                    @else
                                        <span class="text-gray-500">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-6 text-gray-600">
                @if($enrollments->contains(fn($e) => $e->payment_status === 'pending'))
                    Payment confirmation may take a few moments. This page will update automatically.
                @else
                    Your registration has been received. We will contact you with further details.
                @endif
            </p>

            <a href="{{ route('public.courses.index') }}" class="btn-secondary mt-6 inline-block">Back to courses</a>
        </div>
    </div>
</section>

@endsection