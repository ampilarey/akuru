@extends('portal.layout')
@section('title', 'My Payments')

@section('portal-content')
<h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">My Payments</h1>

@if($payments->isEmpty())
    <div class="card p-8 text-center text-gray-500">
        <p>No payment records found.</p>
    </div>
@else
    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Reference</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 hidden sm:table-cell">Course</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Amount</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 hidden sm:table-cell">Date</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($payments as $payment)
                @php
                    $statusCls = ['paid'=>'bg-green-100 text-green-800','completed'=>'bg-green-100 text-green-800','pending'=>'bg-yellow-100 text-yellow-800','failed'=>'bg-red-100 text-red-800','initiated'=>'bg-blue-100 text-blue-800'][$payment->status] ?? 'bg-gray-100 text-gray-600';
                    $courseName = $payment->items->first()?->course?->title ?? '—';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $payment->local_id ?? $payment->merchant_reference ?? '#'.$payment->id }}</td>
                    <td class="px-4 py-3 text-gray-700 hidden sm:table-cell max-w-40 truncate">{{ $courseName }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                    <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">{{ $payment->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium {{ $statusCls }}">
                            {{ ucfirst($payment->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if(in_array($payment->status, ['paid','completed']) && Route::has('payment.receipt'))
                            <a href="{{ route('payment.receipt', $payment) }}" class="text-xs text-brandMaroon-600 hover:underline">Receipt</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3 border-t">{{ $payments->links() }}</div>
    </div>
@endif
@endsection
