@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-3xl">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.enrollments.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm">← Enrollments</a>
        <span class="text-gray-400">/</span>
        <span class="text-gray-700 text-sm">Enrollment #{{ $enrollment->id }}</span>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="card p-6 mb-6">
        <h1 class="text-xl font-bold text-gray-900 mb-4">Enrollment Details</h1>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-gray-500 font-medium">Student</dt>
                <dd class="text-gray-900 font-semibold">{{ $enrollment->student?->full_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 font-medium">Course</dt>
                <dd class="text-gray-900">{{ $enrollment->course?->title ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 font-medium">Enrollment Status</dt>
                <dd>
                    @php
                        $sc = match($enrollment->status) {
                            'active'   => 'bg-green-100 text-green-800',
                            'pending'  => 'bg-amber-100 text-amber-800',
                            'rejected' => 'bg-red-100 text-red-800',
                            default    => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $sc }}">
                        {{ ucfirst($enrollment->status) }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 font-medium">Payment Status</dt>
                <dd>
                    @php
                        $pc = match($enrollment->payment_status) {
                            'confirmed'    => 'bg-green-100 text-green-800',
                            'required'     => 'bg-amber-100 text-amber-800',
                            'not_required' => 'bg-gray-100 text-gray-600',
                            default        => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $pc }}">
                        {{ ucwords(str_replace('_', ' ', $enrollment->payment_status ?? 'N/A')) }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 font-medium">Enrolled at</dt>
                <dd class="text-gray-900">{{ $enrollment->enrolled_at?->format('d M Y, H:i') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 font-medium">Registered by</dt>
                <dd class="text-gray-900">{{ $enrollment->creator?->name ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Student Info --}}
    @if($enrollment->student)
    <div class="card p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-800 mb-3">Student Profile</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-gray-500">Full name</dt>
                <dd class="text-gray-900">{{ $enrollment->student->full_name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Date of birth</dt>
                <dd class="text-gray-900">{{ $enrollment->student->dob?->format('d M Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Gender</dt>
                <dd class="text-gray-900">{{ ucfirst($enrollment->student->gender ?? '—') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">National ID</dt>
                <dd class="text-gray-900">{{ $enrollment->student->national_id ?? '—' }}</dd>
            </div>
        </dl>
        @if($enrollment->student->guardians->isNotEmpty())
            <div class="mt-4">
                <p class="text-xs font-medium text-gray-500 uppercase mb-2">Guardians</p>
                @foreach($enrollment->student->guardians as $g)
                    <p class="text-sm text-gray-800">{{ $g->name }} <span class="text-gray-400">({{ $g->pivot->relationship ?? 'guardian' }})</span></p>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- Payment Info --}}
    @if($enrollment->payment)
    <div class="card p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-800 mb-3">Payment</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-gray-500">Reference</dt>
                <dd class="text-gray-900 font-mono text-xs break-all">{{ $enrollment->payment->merchant_reference }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Amount</dt>
                <dd class="text-gray-900 font-semibold">
                    {{ number_format($enrollment->payment->amount, 2) }} {{ $enrollment->payment->currency }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd>
                    @php
                        $ps = match($enrollment->payment->status) {
                            'confirmed' => 'bg-green-100 text-green-800',
                            'failed','cancelled','expired' => 'bg-red-100 text-red-800',
                            default => 'bg-amber-100 text-amber-800',
                        };
                    @endphp
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $ps }}">
                        {{ ucfirst($enrollment->payment->status) }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Paid at</dt>
                <dd class="text-gray-900">{{ $enrollment->payment->paid_at?->format('d M Y, H:i') ?? '—' }}</dd>
            </div>
        </dl>
    </div>
    @endif

    {{-- Actions --}}
    <div class="card p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4">Actions</h2>
        <div class="flex flex-wrap gap-3">
            @if($enrollment->status !== 'active')
                <form method="POST" action="{{ route('admin.enrollments.activate', $enrollment) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-primary text-sm"
                            onclick="return confirm('Activate this enrollment?')">
                        Activate enrollment
                    </button>
                </form>
            @endif
            @if($enrollment->status !== 'rejected')
                <form method="POST" action="{{ route('admin.enrollments.reject', $enrollment) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="bg-red-600 text-white text-sm py-2 px-4 rounded hover:bg-red-700"
                            onclick="return confirm('Reject this enrollment?')">
                        Reject enrollment
                    </button>
                </form>
            @endif
        </div>
    </div>

</div>
@endsection
