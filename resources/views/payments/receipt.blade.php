@extends('public.layouts.public')

@section('title', 'Payment Receipt #' . $payment->local_id ?? $payment->id)

@section('content')
<section class="py-10">
    <div class="container mx-auto px-4 max-w-2xl">

        {{-- Print button --}}
        <div class="flex justify-between items-center mb-6 no-print">
            <a href="{{ route('my.enrollments') }}" class="text-sm text-brandMaroon-600 hover:underline">← My Enrollments</a>
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white text-sm px-4 py-2 rounded-lg shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print / Save PDF
            </button>
        </div>

        <div class="card p-8" id="receipt-card">
            {{-- Header --}}
            <div class="flex justify-between items-start mb-6 pb-6 border-b border-gray-200">
                <div>
                    <p class="text-2xl font-bold text-brandMaroon-900">Akuru Institute</p>
                    <p class="text-sm text-gray-500">akuru.edu.mv</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-gray-900">RECEIPT</p>
                    <p class="text-xs text-gray-500 font-mono mt-0.5">
                        #{{ $payment->local_id ?? $payment->merchant_reference ?? $payment->id }}
                    </p>
                </div>
            </div>

            {{-- Paid badge --}}
            <div class="mb-6">
                <span class="inline-block bg-green-100 text-green-800 text-sm font-semibold px-3 py-1 rounded-full">
                    ✓ Payment confirmed
                </span>
            </div>

            {{-- Billing info --}}
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Billed to</p>
                    <p class="font-medium text-gray-800">{{ $payment->user?->name ?? '—' }}</p>
                    @php
                        $u = $payment->user;
                        $mobile = $u?->mobile ?? $u?->contacts()->where('type','mobile')->value('value');
                        $email  = $u?->email  ?? $u?->contacts()->where('type','email')->value('value');
                    @endphp
                    @if($mobile)<p class="text-sm text-gray-600">{{ $mobile }}</p>@endif
                    @if($email) <p class="text-sm text-gray-600">{{ $email }}</p>@endif
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Date</p>
                    <p class="font-medium text-gray-800">
                        {{ ($payment->paid_at ?? $payment->updated_at)?->format('d M Y') }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ ($payment->paid_at ?? $payment->updated_at)?->format('H:i') }} (MVT)
                    </p>
                </div>
            </div>

            {{-- Line items --}}
            <table class="w-full mb-6 text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left pb-2 text-gray-500 font-medium">Description</th>
                        <th class="text-left pb-2 text-gray-500 font-medium">Student</th>
                        <th class="text-right pb-2 text-gray-500 font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payment->items as $item)
                    <tr class="border-b border-gray-100">
                        <td class="py-3 text-gray-800">{{ $item->course?->title ?? 'Course enrollment' }}</td>
                        <td class="py-3 text-gray-600">{{ $item->enrollment?->student?->full_name ?? $payment->student?->full_name ?? '—' }}</td>
                        <td class="py-3 text-right text-gray-800 font-mono">
                            {{ number_format($item->amount ?? $payment->amount, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="pt-4 text-right font-semibold text-gray-700">Total</td>
                        <td class="pt-4 text-right font-bold text-brandMaroon-900 font-mono text-base">
                            {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            {{-- Payment details --}}
            <div class="bg-gray-50 rounded-lg p-4 text-sm space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-500">Payment method</span>
                    <span class="font-medium">BML {{ $payment->provider ?? 'Card' }}</span>
                </div>
                @if($payment->bml_transaction_id)
                <div class="flex justify-between">
                    <span class="text-gray-500">Transaction ID</span>
                    <span class="font-mono text-xs">{{ $payment->bml_transaction_id }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-gray-500">Reference</span>
                    <span class="font-mono text-xs">{{ $payment->merchant_reference }}</span>
                </div>
            </div>

            {{-- Req #11: retain a copy --}}
            <div class="mt-6 p-3 bg-blue-50 border border-blue-100 rounded text-xs text-blue-700">
                <strong>Please retain a copy of this receipt</strong> for your records.
                You can print or save this page as a PDF using the button above.
            </div>

            <p class="text-xs text-gray-400 mt-4 text-center">
                Thank you for enrolling with Akuru Institute. For questions: info@akuru.edu.mv
            </p>
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
