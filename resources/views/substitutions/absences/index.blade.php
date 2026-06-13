@extends('layouts.app')

@section('title', 'Teacher Absences')
@section('page-title', 'Teacher Absences')

@section('page-actions')
    <a href="{{ route('absences.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Record Absence
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                @if($absences->count())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Dates</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($absences as $absence)
                            <tr>
                                <td>{{ $absence->teacher?->full_name ?? '—' }}</td>
                                <td>{{ $absence->from_date->format('d M Y') }} – {{ $absence->to_date->format('d M Y') }}</td>
                                <td>{{ Str::limit($absence->reason, 60) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst($absence->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('absences.edit', $absence) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $absences->links() }}</div>
                @else
                <div class="p-5 text-center text-muted">No teacher absences recorded yet.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
