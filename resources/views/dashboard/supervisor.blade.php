@extends('layouts.app')

@section('title', 'Supervisor Dashboard')
@section('page-title', 'Supervisor Dashboard')

@section('content')
{{-- The numbers are here; things are managed in the Admin panel (the owner,
     2026-09-26: "I don't understand what's happening sometimes, /dashboard or /admin"). --}}
<p class="mb-3 flex flex-wrap items-center gap-3 text-xs text-gray-500" data-testid="dashboard-hint">
    <a href="{{ route('admin.index') }}" data-testid="open-admin-panel" class="rounded bg-[#7C2D37] px-3 py-1.5 text-sm font-semibold text-white no-underline">🛠️ Admin panel →</a>
    <span>This dashboard is today's numbers. To manage enrolments and instructors, open the Admin panel.</span>
</p>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-1">Students on the roll</h6>
                <p class="h3 mb-0">{{ $stats['students_on_roll'] }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-1">Teachers on staff</h6>
                <p class="h3 mb-0">{{ $stats['teachers_teaching'] }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-1">Quran Progress Today</h6>
                <p class="h3 mb-0">{{ $stats['quran_progress_today'] }}</p>
            </div>
        </div>
    </div>
</div>

@can('view_hifz_programs')
<div class="card">
    <div class="card-body">
        <h6 class="font-weight-bold text-primary mb-3">Hifz Progress</h6>
        <a href="{{ route('hifz.supervisor.dashboard') }}" class="btn btn-primary">Open Hifz Supervisor Dashboard</a>
    </div>
</div>
@endcan
@endsection
