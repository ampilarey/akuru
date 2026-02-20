@extends('public.layouts.public')

@section('title', 'My Enrollments')

@section('content')
<section class="py-10">
    <div class="container mx-auto px-4 max-w-3xl">
        <h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">My Enrollments</h1>

        @if($enrollments->isEmpty())
            <div class="card p-8 text-center text-gray-500">
                <svg class="mx-auto mb-3 w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p>You have no enrollments yet.</p>
                <a href="{{ route('public.home') }}" class="mt-4 inline-block text-brandMaroon-600 hover:underline text-sm">Browse courses</a>
            </div>
        @else
            <div class="space-y-4">
                @foreach($enrollments as $enrollment)
                <div class="card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 truncate">
                                {{ $enrollment->course?->title ?? '—' }}
                            </p>
                            <p class="text-sm text-gray-500 mt-0.5">
                                Student: {{ $enrollment->student?->full_name ?? '—' }}
                            </p>
                            @if($enrollment->enrolled_at)
                            <p class="text-xs text-gray-400 mt-0.5">
                                Enrolled {{ $enrollment->enrolled_at->format('d M Y') }}
                            </p>
                            @endif
                        </div>

                        <div class="text-right shrink-0">
                            @php
                                $statusColors = [
                                    'active'    => 'bg-green-100 text-green-800',
                                    'pending'   => 'bg-yellow-100 text-yellow-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    'draft'     => 'bg-gray-100 text-gray-600',
                                ];
                                $cls = $statusColors[$enrollment->status] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium {{ $cls }}">
                                {{ ucfirst($enrollment->status) }}
                            </span>

                            @if($enrollment->payment)
                                @php
                                    $pColors = [
                                        'paid'      => 'text-green-600',
                                        'completed' => 'text-green-600',
                                        'pending'   => 'text-yellow-600',
                                        'failed'    => 'text-red-600',
                                    ];
                                    $pc = $pColors[$enrollment->payment->status] ?? 'text-gray-500';
                                @endphp
                                <p class="text-xs {{ $pc }} mt-1">
                                    Payment: {{ ucfirst($enrollment->payment->status) }}
                                </p>
                                @if(in_array($enrollment->payment->status, ['paid', 'completed']) && Route::has('payment.receipt'))
                                    <a href="{{ route('payment.receipt', $enrollment->payment) }}"
                                       class="text-xs text-brandMaroon-600 hover:underline">
                                        View receipt
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>

                    @if($enrollment->status === 'pending' && $enrollment->payment && $enrollment->payment->status === 'initiated')
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <p class="text-xs text-yellow-700">Payment not completed yet.</p>
                        </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
