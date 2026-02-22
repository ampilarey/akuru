@extends('public.layouts.public')

@section('title', 'Registration complete')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-2xl">
        <div class="card p-6">

            {{-- Hero status banner --}}
            <div style="background:linear-gradient(135deg,#3D1219,#7C2D37);border-radius:.75rem;padding:1.5rem;margin-bottom:1.5rem;text-align:center;color:white">
                <div style="font-size:2rem;margin-bottom:.5rem">✅</div>
                <h1 style="font-size:1.3rem;font-weight:800;margin:0 0 .4rem">Enrollment Request Received!</h1>
                <p style="font-size:.85rem;color:rgba(255,255,255,.8);margin:0">Your application has been submitted and is awaiting admin approval.</p>
            </div>

            {{-- What happens next --}}
            <div style="background:#FFFBF0;border:1px solid #FDE68A;border-radius:.625rem;padding:1rem 1.25rem;margin-bottom:1.25rem">
                <p style="font-size:.8rem;font-weight:700;color:#92400E;margin:0 0 .5rem;text-transform:uppercase;letter-spacing:.05em">What happens next?</p>
                <ol style="margin:0;padding-left:1.25rem;font-size:.85rem;color:#374151;line-height:1.9">
                    <li>Admin reviews your enrollment and payment</li>
                    <li>Once approved, you will receive a <strong>confirmation SMS</strong></li>
                    <li>Your enrollment status will change to <strong>Active</strong></li>
                </ol>
                <p style="font-size:.78rem;color:#92400E;margin:.75rem 0 0">⏱ Approval usually takes 1–2 business days.</p>
            </div>

            {{-- Payment status poller (BML) --}}
            @if($paymentRef && $paymentIdForStatus)
            <div id="payment-status" class="mb-5" x-data="{
                loading: true,
                confirmed: false,
                timedOut: false,
                async init() {
                    const url = '{{ route("payments.status.by_id", ["payment" => $paymentIdForStatus]) }}';
                    for (let i = 0; i < 60; i++) {
                        try {
                            const res = await fetch(url);
                            const data = await res.json();
                            if (data.confirmed) { this.confirmed = true; this.loading = false; return; }
                        } catch (e) {}
                        await new Promise(r => setTimeout(r, 3000));
                    }
                    this.loading = false;
                    this.timedOut = true;
                }
            }">
                <div x-show="loading" class="p-4 bg-amber-50 rounded flex items-center gap-3">
                    <svg class="animate-spin h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="text-sm text-amber-800">Verifying payment with bank...</span>
                </div>
                <div x-show="!loading && confirmed" x-cloak class="p-4 bg-green-50 border border-green-200 rounded">
                    <p class="text-green-800 font-semibold text-sm">✓ Payment received by bank. Your enrollment is now pending admin approval.</p>
                </div>
                <div x-show="timedOut && !confirmed" x-cloak class="p-4 bg-amber-50 border border-amber-200 rounded">
                    <p class="text-amber-800 font-medium text-sm mb-2">Payment verification is taking longer than expected.</p>
                    <p class="text-amber-700 text-xs mb-3">If you completed the payment at the bank, it will be confirmed automatically. You may also retry:</p>
                    <a href="{{ route('courses.register.payment.retry', ['ref' => $paymentRef]) }}"
                       class="inline-block bg-brandMaroon-600 text-white text-xs py-2 px-4 rounded hover:bg-brandMaroon-700 mr-2">
                        Retry payment
                    </a>
                    <a href="{{ route('public.courses.index') }}" class="inline-block text-xs text-gray-600 hover:underline py-2">
                        Return to courses
                    </a>
                </div>
            </div>
            @endif

            {{-- Enrollment summary table --}}
            <div style="overflow-x:auto;margin-bottom:1.25rem">
                <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:2px solid #E5E7EB">
                            <th style="text-align:left;padding:.5rem .25rem;color:#374151">Course</th>
                            <th style="text-align:left;padding:.5rem .25rem;color:#374151">Enrollment Status</th>
                            <th style="text-align:left;padding:.5rem .25rem;color:#374151">Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $e)
                        <tr style="border-bottom:1px solid #F3F4F6">
                            <td style="padding:.6rem .25rem;color:#111827;font-weight:500">{{ $e->course->title ?? '—' }}</td>
                            <td style="padding:.6rem .25rem">
                                @if($e->status === 'active')
                                    <span style="background:#D1FAE5;color:#065F46;font-size:.75rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px">✓ Active</span>
                                @else
                                    <span style="background:#FEF3C7;color:#92400E;font-size:.75rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px">⏳ Pending Approval</span>
                                @endif
                            </td>
                            <td style="padding:.6rem .25rem">
                                @if(in_array($e->payment_status, ['confirmed','paid']))
                                    <span style="color:#059669;font-weight:600">✓ Paid</span>
                                @elseif($e->payment_status === 'not_required')
                                    <span style="color:#6B7280">Free</span>
                                @else
                                    <span style="color:#D97706">Pending</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
                <a href="{{ route('my.enrollments') }}" class="btn-primary" style="font-size:.875rem">View My Enrollments</a>
                <a href="{{ route('public.courses.index') }}" class="btn-secondary" style="font-size:.875rem">Browse Courses</a>
            </div>
        </div>
    </div>
</section>

@endsection