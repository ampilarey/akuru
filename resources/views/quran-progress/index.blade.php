@extends('layouts.app')

@section('title', __('quran.qp_title'))

@section('content')
<div class="p-4">
{{-- OB2 (STATUS §5te): in the page's language only — it printed all three side by
     side with the Dhivehi wrong (*ރަގަޅު*, "good", for *Student*). Its heading and
     Add sat in sections the layout never prints, behind permissions that do not
     exist, so nobody could add, change or remove a record from here. --}}
<div class="row">
    <div class="col-12">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h1 class="h4 m-0">{{ __('quran.qp_records') }}</h1>
            @can('manage_quran_progress')
                <a href="{{ route('quran-progress.create') }}" class="btn btn-primary" data-testid="qp-add">
                    <i class="fas fa-plus me-2" aria-hidden="true"></i>{{ __('quran.qp_add') }}
                </a>
            @endcan
        </div>

        @foreach(['success' => 'success', 'error' => 'danger'] as $key => $tone)
            @if(session($key))
                <div class="alert alert-{{ $tone }}" role="alert" data-testid="flash-{{ $key }}">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="card">
            <div class="card-body">
                @if($progress->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-stack">
                            <thead>
                                <tr>
                                    <th>{{ __('quran.qp_student') }}</th>
                                    <th>{{ __('quran.qp_surah') }}</th>
                                    <th>{{ __('quran.qp_type') }}</th>
                                    <th>{{ __('quran.qp_status') }}</th>
                                    <th>{{ __('quran.qp_accuracy') }}</th>
                                    <th>{{ __('quran.qp_date') }}</th>
                                    <th><span class="sr-only">{{ __('quran.qp_actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($progress as $record)
                                <tr data-testid="qp-row">
                                    <td data-label="{{ __('quran.qp_student') }}">{{ $record->student?->full_name ?? '—' }}</td>
                                    <td data-label="{{ __('quran.qp_surah') }}">
                                        <div>
                                            <strong>{{ $record->surah_name }}</strong>
                                            <span class="arabic-text block" lang="ar" dir="rtl">{{ $record->surah_name_arabic }}</span>
                                        </div>
                                        @if($record->from_ayah && $record->to_ayah)
                                            <small class="text-muted">{{ __('quran.qp_ayahs', ['from' => $record->from_ayah, 'to' => $record->to_ayah]) }}</small>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('quran.qp_type') }}">
                                        <span class="badge bg-info">{{ __('quran.qp_type_'.$record->type) }}</span>
                                    </td>
                                    <td data-label="{{ __('quran.qp_status') }}">
                                        <span class="badge bg-{{ $record->status === 'completed' ? 'success' : ($record->status === 'in_progress' ? 'warning' : 'danger') }}">
                                            {{ __('quran.qp_status_'.$record->status) }}
                                        </span>
                                    </td>
                                    <td data-label="{{ __('quran.qp_accuracy') }}">{{ $record->accuracy_percentage !== null ? $record->accuracy_percentage.'%' : '—' }}</td>
                                    <td data-label="{{ __('quran.qp_date') }}">{{ $record->created_at->translatedFormat('j M Y') }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('quran-progress.show', $record) }}" class="btn btn-sm btn-outline-primary">{{ __('quran.qp_view') }}</a>
                                            @can('manage_quran_progress')
                                                <a href="{{ route('quran-progress.edit', $record) }}" class="btn btn-sm btn-outline-warning">{{ __('quran.qp_edit') }}</a>
                                                <form action="{{ route('quran-progress.destroy', $record) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm(@js(__('quran.qp_delete_confirm')))">{{ __('quran.qp_delete') }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted py-4 text-center" data-testid="qp-none">{{ __('quran.qp_none') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
</div>
@endsection
