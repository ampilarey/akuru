@extends('portal.layout')
@section('title', 'My Portal')

@section('portal-content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-brandMaroon-900">Welcome back, {{ $user->name }}</h1>
    <p class="text-gray-500 text-sm mt-1">Here's an overview of your activity.</p>
</div>

@if(session('success'))
    <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('success') }}</div>
@endif

{{-- Stats --}}
<div class="grid sm:grid-cols-3 gap-4 mb-8">
    <div class="card p-5 text-center">
        <p class="text-3xl font-bold text-green-600">{{ $activeEnrollments }}</p>
        <p class="text-sm text-gray-500 mt-1">Active enrollments</p>
    </div>
    <div class="card p-5 text-center">
        <p class="text-3xl font-bold text-yellow-600">{{ $pendingEnrollments }}</p>
        <p class="text-sm text-gray-500 mt-1">Pending</p>
    </div>
    <div class="card p-5 text-center">
        <p class="text-3xl font-bold text-brandMaroon-700">{{ $recentPayments->count() }}</p>
        <p class="text-sm text-gray-500 mt-1">Payments</p>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- Recent Enrollments --}}
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-900">Recent Enrollments</h2>
            <a href="{{ route('portal.enrollments') }}" class="text-xs text-brandMaroon-600 hover:underline">View all →</a>
        </div>
        @if($enrollments->isEmpty())
            <p class="p-5 text-sm text-gray-500">No enrollments yet. <a href="{{ route('public.courses.index') }}" class="text-brandMaroon-600 hover:underline">Browse courses</a></p>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($enrollments as $e)
                @php $statusCls = ['active'=>'bg-green-100 text-green-800','pending'=>'bg-yellow-100 text-yellow-800','pending_payment'=>'bg-blue-100 text-blue-800','cancelled'=>'bg-red-100 text-red-800'][$e->status] ?? 'bg-gray-100 text-gray-600'; @endphp
                <div class="px-5 py-3 flex justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $e->course?->title ?? '—' }}</p>
                        <p class="text-xs text-gray-500">{{ $e->student?->full_name ?? '—' }}</p>
                    </div>
                    <span class="shrink-0 text-xs px-2 py-0.5 rounded-full font-medium self-start {{ $statusCls }}">{{ ucfirst(str_replace('_',' ',$e->status)) }}</span>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Recent Payments --}}
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-900">Recent Payments</h2>
            <a href="{{ route('portal.payments') }}" class="text-xs text-brandMaroon-600 hover:underline">View all →</a>
        </div>
        @if($recentPayments->isEmpty())
            <p class="p-5 text-sm text-gray-500">No payments yet.</p>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($recentPayments as $p)
                <div class="px-5 py-3 flex justify-between gap-3 items-center">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 font-mono">{{ $p->local_id ?? $p->merchant_reference }}</p>
                        <p class="text-xs text-gray-500">{{ $p->created_at->format('d M Y') }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-bold text-gray-900">{{ number_format($p->amount, 2) }} {{ $p->currency }}</p>
                        @if(Route::has('payment.receipt'))
                            <a href="{{ route('payment.receipt', $p) }}" class="text-xs text-brandMaroon-600 hover:underline">Receipt</a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
