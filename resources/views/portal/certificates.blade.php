@extends('portal.layout')
@section('title', 'My Certificates')

@section('portal-content')
<h1 class="text-2xl font-bold text-brandMaroon-900 mb-2">My Certificates</h1>
<p class="text-gray-500 text-sm mb-6">Certificates will be available here once courses are completed.</p>

@if($activeEnrollments->isEmpty())
    <div class="card p-8 text-center text-gray-500">
        <svg class="w-14 h-14 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
        </svg>
        <p class="font-medium mb-1">No active enrollments</p>
        <p class="text-sm">Enroll in a course to earn a certificate upon completion.</p>
        <a href="{{ route('public.courses.index') }}" class="mt-4 inline-block btn-primary text-sm">Browse courses</a>
    </div>
@else
    <div class="space-y-4">
        @foreach($activeEnrollments as $enrollment)
        <div class="card p-5 flex items-center justify-between gap-4 opacity-70">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-brandBeige-100 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-brandMaroon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-900">{{ $enrollment->course?->title ?? '—' }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">In progress — certificate available on completion</p>
                </div>
            </div>
            <span class="shrink-0 text-xs px-2 py-1 bg-gray-100 text-gray-500 rounded font-medium">Coming soon</span>
        </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-400 mt-4 text-center">Certificate generation will be enabled when connected to the learning management system.</p>
@endif
@endsection
