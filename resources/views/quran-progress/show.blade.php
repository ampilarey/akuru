@extends('layouts.app')

@section('title', 'Quran Progress Details')
@section('page-title', 'Quran Progress Details')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $quranProgress->surah_name }} ({{ $quranProgress->surah_number }})</h5>
                <div>
                    <a href="{{ route('quran-progress.edit', $quranProgress) }}" class="btn btn-sm btn-primary">Edit</a>
                    <a href="{{ route('quran-progress.index') }}" class="btn btn-sm btn-secondary">Back</a>
                </div>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Student</dt>
                    <dd class="col-sm-8">{{ $quranProgress->student?->full_name ?? '—' }}</dd>

                    <dt class="col-sm-4">Teacher</dt>
                    <dd class="col-sm-8">{{ $quranProgress->teacher?->full_name ?? '—' }}</dd>

                    <dt class="col-sm-4">Type</dt>
                    <dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $quranProgress->type)) }}</dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $quranProgress->status)) }}</dd>

                    <dt class="col-sm-4">Ayah range</dt>
                    <dd class="col-sm-8">{{ $quranProgress->from_ayah ?? '—' }} – {{ $quranProgress->to_ayah ?? '—' }}</dd>

                    <dt class="col-sm-4">Accuracy</dt>
                    <dd class="col-sm-8">{{ $quranProgress->accuracy_percentage !== null ? $quranProgress->accuracy_percentage.'%' : '—' }}</dd>

                    @if($quranProgress->teacher_notes)
                    <dt class="col-sm-4">Notes</dt>
                    <dd class="col-sm-8">{{ $quranProgress->teacher_notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
