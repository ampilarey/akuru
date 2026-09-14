@extends('layouts.app')

@section('title', 'Supervisor Dashboard')
@section('page-title', 'Supervisor Dashboard')

@section('content')
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
