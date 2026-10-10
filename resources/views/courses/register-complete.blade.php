@extends('public.layouts.public')

@section('title', __('register.complete_title'))

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-2xl">
        <div class="card p-6">

            {{-- Hero status banner --}}
            <div style="background:linear-gradient(135deg,#3D1219,#7C2D37);border-radius:.75rem;padding:1.5rem;margin-bottom:1.5rem;text-align:center;color:white">
                <div style="font-size:2rem;margin-bottom:.5rem" aria-hidden="true">✅</div>
                <h1 style="font-size:1.3rem;font-weight:800;margin:0 0 .4rem">{{ __('register.complete_heading') }}</h1>
                <p style="font-size:.85rem;color:rgba(255,255,255,.8);margin:0">{{ __('register.complete_intro') }}</p>
            </div>

            {{-- Why the learner is here, when the controller says (a payment already waiting). The layout shows no info, so nothing did (slice RG1c). --}}
            @if(session('info'))
            <div class="mb-5 p-4 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800" role="status" data-testid="complete-info">{{ session('info') }}</div>
            @endif

            {{-- What happens next --}}
            <div style="background:#FFFBF0;border:1px solid #FDE68A;border-radius:.625rem;padding:1rem 1.25rem;margin-bottom:1.25rem">
                <p style="font-size:.8rem;font-weight:700;color:#92400E;margin:0 0 .5rem;text-transform:uppercase;letter-spacing:.05em">{{ __('register.next_title') }}</p>
                <ol style="margin:0;padding-inline-start:1.25rem;font-size:.85rem;color:#374151;line-height:1.9">
                    <li>{{ __('register.next_review') }}</li>
                    <li>{{ __('register.next_sms') }}</li>
                    <li>{{ __('register.next_active') }}</li>
                </ol>
                <p style="font-size:.78rem;color:#92400E;margin:.75rem 0 0"><span aria-hidden="true">⏱</span> {{ __('register.next_time') }}</p>
            </div>

            {{-- Message when payment gateway could not be reached after OTP. Taken out of the session by the controller, so the layout does not say it a second time (slice RG1c). --}}
            @if(!empty($paymentError))
            <div class="mb-5 p-4 bg-amber-50 border border-amber-200 rounded" role="alert">
                <p class="text-amber-800 font-medium text-sm">{{ $paymentError }}</p>
                @if($paymentRef)
                <a href="{{ route('courses.register.payment.retry', ['ref' => $paymentRef]) }}"
                   class="inline-block mt-3 bg-brandMaroon-600 text-white text-xs py-2 px-4 rounded hover:bg-brandMaroon-700">
                    {{ __('register.proceed_to_payment') }}
                </a>
                @endif
            </div>
            @endif

            {{-- Proceed to payment when payment still pending (so user can open BML even if error flash didn't show) --}}
            @if(!empty($showProceedToPayment) && $paymentRef && empty($paymentError))
            <div class="mb-5 p-4 bg-amber-50 border border-amber-200 rounded">
                <p class="text-amber-800 font-medium text-sm">{{ __('register.pay_to_finish') }}</p>
                <a href="{{ route('courses.register.payment.retry', ['ref' => $paymentRef]) }}"
                   class="inline-block mt-3 bg-brandMaroon-600 text-white text-xs py-2 px-4 rounded hover:bg-brandMaroon-700">
                    {{ __('register.proceed_to_payment') }}
                </a>
            </div>
            @endif

            {{-- Payment status poller (BML) — when we have a pending payment and no gateway error --}}
            @if($paymentRef && $paymentIdForStatus && empty($paymentError))
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
                    <svg class="animate-spin h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="text-sm text-amber-800">{{ __('register.checking_payment') }}</span>
                </div>
                <div x-show="!loading && confirmed" x-cloak class="p-4 bg-green-50 border border-green-200 rounded">
                    <p class="text-green-800 font-semibold text-sm"><span aria-hidden="true">✓</span> {{ __('register.payment_received') }}</p>
                </div>
                <div x-show="timedOut && !confirmed" x-cloak class="p-4 bg-amber-50 border border-amber-200 rounded">
                    <p class="text-amber-800 font-medium text-sm mb-2">{{ __('register.checking_slow') }}</p>
                    <p class="text-amber-700 text-xs mb-3">{{ __('register.checking_slow_hint') }}</p>
                    <a href="{{ route('courses.register.payment.retry', ['ref' => $paymentRef]) }}"
                       class="inline-block bg-brandMaroon-600 text-white text-xs py-2 px-4 rounded hover:bg-brandMaroon-700 me-2">
                        {{ __('register.retry_payment') }}
                    </a>
                    <a href="{{ route('public.courses.index') }}" class="inline-block text-xs text-gray-600 hover:underline py-2">
                        {{ __('register.back_to_courses') }}
                    </a>
                </div>
            </div>
            @endif

            {{-- Enrollment summary table --}}
            <div style="overflow-x:auto;margin-bottom:1.25rem">
                <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:2px solid #E5E7EB">
                            <th style="text-align:start;padding:.5rem .25rem;color:#374151">{{ __('register.col_course') }}</th>
                            <th style="text-align:start;padding:.5rem .25rem;color:#374151">{{ __('register.col_status') }}</th>
                            <th style="text-align:start;padding:.5rem .25rem;color:#374151">{{ __('register.col_payment') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $e)
                        <tr style="border-bottom:1px solid #F3F4F6">
                            <td style="padding:.6rem .25rem;color:#111827;font-weight:500">{{ $e->course->title ?? '—' }}</td>
                            <td style="padding:.6rem .25rem">
                                @if($e->status === 'active')
                                    <span style="background:#D1FAE5;color:#065F46;font-size:.75rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px"><span aria-hidden="true">✓</span> {{ __('register.badge_active') }}</span>
                                @else
                                    <span style="background:#FEF3C7;color:#92400E;font-size:.75rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px"><span aria-hidden="true">⏳</span> {{ __('register.badge_pending') }}</span>
                                @endif
                            </td>
                            <td style="padding:.6rem .25rem">
                                @if(in_array($e->payment_status, ['confirmed','paid']))
                                    <span style="color:#059669;font-weight:600"><span aria-hidden="true">✓</span> {{ __('register.paid') }}</span>
                                @elseif($e->payment_status === 'not_required')
                                    <span style="color:#6B7280">{{ __('register.price_free') }}</span>
                                @else
                                    <span style="color:#D97706">{{ __('register.payment_due') }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
                <a href="{{ route('my.enrollments') }}" class="btn-primary" style="font-size:.875rem">{{ __('register.my_enrollments') }}</a>
                <a href="{{ route('public.courses.index') }}" class="btn-secondary" style="font-size:.875rem">{{ __('register.browse_courses') }}</a>
            </div>
        </div>
    </div>
</section>

@endsection
