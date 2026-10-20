@extends('layouts.app')

@section('title', __('quran.qp_details'))

@section('content')
<div class="p-4">
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1 class="h5 mb-0">{{ $quranProgress->surah_name }} ({{ $quranProgress->surah_number }})</h1>
                <div>
                    @can('manage_quran_progress')
                    <a href="{{ route('quran-progress.edit', $quranProgress) }}" class="btn btn-sm btn-primary">{{ __('quran.qp_edit') }}</a>
                    @endcan
                    <a href="{{ route('quran-progress.index') }}" class="btn btn-sm btn-secondary">{{ __('quran.qp_back') }}</a>
                </div>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('quran.qp_student') }}</dt>
                    <dd class="col-sm-8">{{ $quranProgress->student?->full_name ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('quran.qp_teacher') }}</dt>
                    <dd class="col-sm-8">{{ $quranProgress->teacher?->full_name ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('quran.qp_type') }}</dt>
                    <dd class="col-sm-8">{{ __('quran.qp_type_'.$quranProgress->type) }}</dd>

                    <dt class="col-sm-4">{{ __('quran.qp_status') }}</dt>
                    <dd class="col-sm-8">{{ __('quran.qp_status_'.$quranProgress->status) }}</dd>

                    <dt class="col-sm-4">{{ __('quran.qp_ayah_range') }}</dt>
                    <dd class="col-sm-8">{{ $quranProgress->from_ayah ?? '—' }} – {{ $quranProgress->to_ayah ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('quran.qp_accuracy') }}</dt>
                    <dd class="col-sm-8">{{ $quranProgress->accuracy_percentage !== null ? $quranProgress->accuracy_percentage.'%' : '—' }}</dd>

                    @if($quranProgress->teacher_notes)
                    <dt class="col-sm-4">{{ __('quran.qp_notes') }}</dt>
                    <dd class="col-sm-8">{{ $quranProgress->teacher_notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
