@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Enrollments</h1>
            <div class="flex gap-4 mt-1 text-sm">
                <span class="font-semibold text-brandMaroon-700">Enrollments</span>
                <a href="{{ route('admin.enrollments.payments') }}" class="text-brandBlue-600 hover:text-brandBlue-800">Payments →</a>
            </div>
        </div>
        <a href="{{ route('admin.enrollments.export', request()->query()) }}"
           class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="card p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, mobile, email…"
                   class="border rounded px-3 py-2 text-sm w-52">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Course</label>
            <select name="course_id" class="border rounded px-3 py-2 text-sm">
                <option value="">All courses</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->title }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select name="status" class="border rounded px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Payment</label>
            <select name="payment_status" class="border rounded px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="required" @selected(request('payment_status') === 'required')>Required</option>
                <option value="confirmed" @selected(request('payment_status') === 'confirmed')>Confirmed</option>
                <option value="not_required" @selected(request('payment_status') === 'not_required')>Not required</option>
            </select>
        </div>
        <button type="submit" class="btn-primary text-sm py-2 px-4">Filter</button>
        @if(request()->hasAny(['search','course_id','status','payment_status']))
            <a href="{{ route('admin.enrollments.index') }}" class="text-sm text-gray-500 hover:underline py-2">Clear</a>
        @endif
    </form>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($enrollments as $e)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-sm font-medium text-gray-900">
                                {{ $e->student?->full_name ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700">{{ $e->course?->title ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $sc = match($e->status) {
                                        'active'   => 'bg-green-100 text-green-800',
                                        'pending'  => 'bg-amber-100 text-amber-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        default    => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $sc }}">
                                    {{ ucfirst($e->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                @php
                                    $pc = match($e->payment_status) {
                                        'confirmed'    => 'bg-green-100 text-green-800',
                                        'required'     => 'bg-amber-100 text-amber-800',
                                        'not_required' => 'bg-gray-100 text-gray-600',
                                        default        => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $pc }}">
                                    {{ ucwords(str_replace('_', ' ', $e->payment_status ?? 'N/A')) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500">
                                {{ $e->created_at->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3 text-right text-sm">
                                <a href="{{ route('admin.enrollments.show', $e) }}" class="text-brandBlue-600 hover:text-brandBlue-900">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-gray-400 text-sm">No enrollments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($enrollments->hasPages())
            <div class="px-5 py-4 border-t">{{ $enrollments->links() }}</div>
        @endif
    </div>
</div>
@endsection
