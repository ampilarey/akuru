@extends('portal.layout')
@section('title', 'My Enrollments')

@section('portal-content')
<h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">My Enrollments</h1>

@if($enrollments->isEmpty())
    <div class="card p-8 text-center text-gray-500">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="font-medium mb-1">No enrollments yet</p>
        <a href="{{ route('public.courses.index') }}" class="mt-3 inline-block btn-primary text-sm">Browse courses</a>
    </div>
@else
    @foreach($enrollments as $status => $group)
    @php
        $statusLabels = ['active'=>'Active','pending'=>'Pending Review','pending_payment'=>'Pending Payment','cancelled'=>'Cancelled','rejected'=>'Rejected','draft'=>'Draft'];
        $statusColors = ['active'=>'text-green-700','pending'=>'text-yellow-700','pending_payment'=>'text-blue-700','cancelled'=>'text-red-700','rejected'=>'text-red-700','draft'=>'text-gray-500'];
    @endphp
    <div class="mb-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider mb-3 {{ $statusColors[$status] ?? 'text-gray-600' }}">
            {{ $statusLabels[$status] ?? ucfirst($status) }} ({{ $group->count() }})
        </h2>
        <div class="space-y-3">
            @foreach($group as $e)
            <div class="card p-5 flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900">{{ $e->course?->title ?? '—' }}</p>
                    <p class="text-sm text-gray-500">Student: {{ $e->student?->full_name ?? '—' }}</p>
                    @if($e->enrolled_at)
                        <p class="text-xs text-gray-400 mt-0.5">Enrolled {{ $e->enrolled_at->format('d M Y') }}</p>
                    @endif
                </div>
                <div class="shrink-0 flex items-center gap-3">
                    @if($e->course?->slug)
                        <a href="{{ LaravelLocalization::localizeURL(route('public.courses.show', $e->course->slug)) }}"
                           class="text-xs text-brandMaroon-600 hover:underline">View course</a>
                    @endif
                    @if($e->payment && in_array($e->payment->status, ['paid','completed']) && Route::has('payment.receipt'))
                        <a href="{{ route('payment.receipt', $e->payment) }}"
                           class="text-xs text-gray-600 hover:text-brandMaroon-600 border border-gray-200 px-2 py-1 rounded">
                            Receipt
                        </a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
@endif
@endsection
