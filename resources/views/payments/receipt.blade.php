@extends('public.layouts.public')

{{-- `.` binds tighter than `??`, so the title read "Payment Receipt #" with no number whenever local_id was empty (slice PY1). --}}
@section('title', __('payments.receipt_title', ['number' => '#'.($payment->local_id ?? $payment->merchant_reference ?? $payment->id)]))

@section('content')
<section class="py-10">
    <div class="container mx-auto px-4 max-w-2xl">

        {{-- Print button --}}
        <div class="flex justify-between items-center mb-6 no-print">
            <a href="{{ route('my.enrollments') }}" class="text-sm text-brandMaroon-600 hover:underline"><span class="rtl-flip" aria-hidden="true">←</span> {{ __('nav.my_enrolments') }}</a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white text-sm px-4 py-2 rounded-lg shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                {{ __('payments.print') }}
            </button>
        </div>

        <div class="card p-8" id="receipt-card">
            {{-- Header --}}
            <div class="flex justify-between items-start mb-6 pb-6 border-b border-gray-200">
                <div>
                    <p class="text-2xl font-bold text-brandMaroon-900">{{ __('public.Akuru Institute') }}</p>
                    <p class="text-sm text-gray-500" dir="ltr">akuru.edu.mv</p>
                </div>
                <div class="text-end">
                    <p class="text-lg font-bold text-gray-900 uppercase">{{ __('payments.receipt') }}</p>
                    <p class="text-xs text-gray-500 font-mono mt-0.5" dir="ltr">
                        #{{ $payment->local_id ?? $payment->merchant_reference ?? $payment->id }}
                    </p>
                </div>
            </div>

            {{-- Paid badge --}}
            <div class="mb-6">
                <span class="inline-block bg-green-100 text-green-800 text-sm font-semibold px-3 py-1 rounded-full">
                    <span aria-hidden="true">✓</span> {{ __('payments.receipt_confirmed') }}
                </span>
            </div>

            {{-- Billing info --}}
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ __('payments.billed_to') }}</p>
                    <p class="font-medium text-gray-800">{{ $payment->user?->name ?? '—' }}</p>
                    @php
                        $u = $payment->user;
                        $mobile = $u?->mobile ?? $u?->contacts()->where('type','mobile')->value('value');
                        $email  = $u?->email  ?? $u?->contacts()->where('type','email')->value('value');
                    @endphp
                    @if($mobile)<p class="text-sm text-gray-600" dir="ltr">{{ $mobile }}</p>@endif
                    @if($email) <p class="text-sm text-gray-600" dir="ltr">{{ $email }}</p>@endif
                </div>
                <div class="text-end">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ __('payments.date') }}</p>
                    {{-- The month by its name in the page's language; the time said as the Maldives' (it printed "MVT"). --}}
                    <p class="font-medium text-gray-800">
                        {{ ($payment->paid_at ?? $payment->updated_at)?->translatedFormat('j F Y') }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ __('payments.maldives_time', ['time' => ($payment->paid_at ?? $payment->updated_at)?->format('H:i')]) }}
                    </p>
                </div>
            </div>

            {{-- Line items --}}
            <table class="w-full mb-6 text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-start pb-2 text-gray-500 font-medium">{{ __('payments.col_description') }}</th>
                        <th class="text-start pb-2 text-gray-500 font-medium">{{ __('payments.col_student') }}</th>
                        <th class="text-end pb-2 text-gray-500 font-medium">{{ __('payments.col_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- An engine payment has no `payment_items` at all, so
                         this used to render an empty table above a total.
                         BuildPaymentReceiptAction falls back to the payment's
                         own course (SPEC §38's `course_id`). --}}
                    @foreach($receipt['lines'] as $line)
                    <tr class="border-b border-gray-100">
                        <td class="py-3 text-gray-800">{{ $line['description'] }}</td>
                        <td class="py-3 text-gray-600">{{ $line['student'] ?? '—' }}</td>
                        <td class="py-3 text-end text-gray-800 font-mono">
                            {{ number_format($line['amount'], 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="pt-4 text-end font-semibold text-gray-700">{{ __('payments.total') }}</td>
                        <td class="pt-4 text-end font-bold text-brandMaroon-900 font-mono text-base">
                            {{ number_format($receipt['total'], 2) }} {{ $receipt['currency'] }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            {{-- Payment details --}}
            <div class="bg-gray-50 rounded-lg p-4 text-sm space-y-2">
                {{-- SPEC §38 keeps gateway and method apart. This line read
                     "BML {{ provider }}", which printed "BML bml" for a
                     gateway payment and "BML manual" for cash taken at the
                     office — the one case where it is certainly not BML. --}}
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('payments.paid_through') }}</span>
                    <span class="font-medium">{{ $receipt['gateway'] }}</span>
                </div>
                @if($receipt['method'])
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('payments.payment_method') }}</span>
                    <span class="font-medium">{{ $receipt['method'] }}</span>
                </div>
                @endif
                @if($payment->bml_transaction_id)
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('payments.transaction_id') }}</span>
                    <span class="font-mono text-xs" dir="ltr">{{ $payment->bml_transaction_id }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('payments.reference') }}</span>
                    <span class="font-mono text-xs" dir="ltr">{{ $payment->merchant_reference }}</span>
                </div>
            </div>

            {{-- Req #11: retain a copy --}}
            <div class="mt-6 p-3 bg-blue-50 border border-blue-100 rounded text-xs text-blue-700">
                <strong>{{ __('payments.keep_copy') }}</strong>
                {{ __('payments.keep_copy_hint') }}
            </div>

            @php($contactEmail = $siteSettings['email'] ?? 'info@akuru.edu.mv')
            <p class="text-xs text-gray-400 mt-4 text-center">@foreach(preg_split('/(:email)/', __('payments.thanks'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if($part === ':email')<a href="mailto:{{ $contactEmail }}" dir="ltr" class="underline">{{ $contactEmail }}</a>@else{{ $part }}@endif{{ '' }}@endforeach</p>
        </div>
    </div>
</section>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white; }
    #receipt-card { box-shadow: none; border: 1px solid #ddd; }
}
</style>
@endsection
