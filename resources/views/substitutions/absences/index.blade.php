@extends('layouts.app')

@section('title', __('substitutions.absences_title').' - '.config('app.name', 'Akuru LMS'))

{{-- The app layout yields only `content`: this page's heading and its Record button sat in sections it never prints (slice TS1). --}}
@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h1 class="h3 mb-0">{{ __('substitutions.absences_title') }}</h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('substitutions.requests.index') }}" class="btn btn-outline-secondary">{{ __('substitutions.requests_link') }}</a>
                <a href="{{ route('absences.create') }}" class="btn btn-primary" data-testid="record-absence">
                    <i class="fas fa-plus me-2"></i>{{ __('substitutions.record_absence') }}
                </a>
            </div>
        </div>

        @foreach(['success' => 'success', 'error' => 'danger'] as $key => $tone)
            @if(session($key))
                <div class="alert alert-{{ $tone }}" role="alert" data-testid="flash-{{ $key }}">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="card">
            <div class="card-body p-0">
                @if($absences->count())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('substitutions.col_teacher') }}</th>
                                <th>{{ __('substitutions.col_dates') }}</th>
                                <th>{{ __('substitutions.col_reason') }}</th>
                                <th>{{ __('substitutions.col_status') }}</th>
                                <th><span class="visually-hidden">{{ __('substitutions.col_actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($absences as $absence)
                            <tr data-testid="absence-row">
                                <td>{{ $absence->teacher?->full_name ?? '—' }}</td>
                                <td>{{ $absence->from_date->translatedFormat('j M Y') }} – {{ $absence->to_date->translatedFormat('j M Y') }}</td>
                                <td>{{ Str::limit($absence->reason, 60) }}</td>
                                <td><span class="badge bg-secondary">{{ in_array($absence->status, ['pending', 'approved', 'rejected'], true) ? __('substitutions.absence_status_'.$absence->status) : $absence->status }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('absences.edit', $absence) }}" class="btn btn-sm btn-outline-primary">{{ __('substitutions.edit') }}</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $absences->links() }}</div>
                @else
                <div class="p-5 text-center text-muted">{{ __('substitutions.absences_empty') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
