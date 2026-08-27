@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Payments</h1>
            <div class="flex gap-4 mt-1 text-sm">
                <a href="{{ route('admin.enrollments.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800">← Enrollments</a>
                <span class="font-semibold text-brandMaroon-700">Payments</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="card p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, mobile, email, ref…"
                   class="border rounded px-3 py-2 text-sm w-56">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select name="status" class="border rounded px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="confirmed" @selected(request('status') === 'confirmed')>Confirmed</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                <option value="refunded" @selected(request('status') === 'refunded')>Refunded</option>
            </select>
        </div>
        <button type="submit" class="btn-primary text-sm py-2 px-4">Filter</button>
        @if(request()->hasAny(['search','status']))
            <a href="{{ route('admin.enrollments.payments') }}" class="text-sm text-gray-500 hover:underline py-2">Clear</a>
        @endif
    </form>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payer</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Refund</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-xs font-mono text-gray-600 break-all max-w-xs">
                                {{ Str::limit($payment->merchant_reference, 30) }}
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-800">{{ $payment->user?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-800">{{ $payment->student?->full_name ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm font-semibold text-gray-900">
                                {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
                            </td>
                            <td class="px-5 py-3">
                                @php
                                    $sc = match($payment->status) {
                                        'confirmed' => 'bg-green-100 text-green-800',
                                        'failed','cancelled','expired' => 'bg-red-100 text-red-800',
                                        'refunded' => 'bg-gray-200 text-gray-700',
                                        default => 'bg-amber-100 text-amber-800',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $sc }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500">
                                {{ $payment->created_at->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3 text-sm">
                                @php
                                    $refunded = $payment->refunds->sum('amount');
                                    $refundable = round($payment->amount - $refunded, 2);
                                @endphp
                                @if($refunded > 0)
                                    <p class="text-xs text-gray-500 mb-1">Refunded: {{ number_format($refunded, 2) }} {{ $payment->currency }}</p>
                                @endif
                                @if(auth()->user()?->can('payments.refund') && in_array($payment->status, ['confirmed', 'paid'], true) && $refundable > 0)
                                    <details>
                                        <summary class="cursor-pointer text-xs text-brandMaroon-700 hover:underline">Refund…</summary>
                                        <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="mt-2 space-y-1">
                                            @csrf
                                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $refundable }}"
                                                   value="{{ $refundable }}" class="border rounded px-2 py-1 text-xs w-24">
                                            <select name="destination" class="border rounded px-2 py-1 text-xs">
                                                <option value="wallet">To wallet</option>
                                                <option value="manual">Manual (returned outside)</option>
                                            </select>
                                            <input type="text" name="reason" placeholder="Reason" maxlength="500"
                                                   class="border rounded px-2 py-1 text-xs w-32">
                                            <button type="submit" class="btn-primary text-xs py-1 px-2"
                                                    onclick="return confirm('Record this refund? A full refund cancels what the payment bought.')">
                                                Refund
                                            </button>
                                        </form>
                                    </details>
                                @elseif($payment->status === 'refunded')
                                    <span class="text-xs text-gray-400">Fully refunded</span>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400 text-sm">No payments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
            <div class="px-5 py-4 border-t">{{ $payments->links() }}</div>
        @endif
    </div>
</div>
@endsection
