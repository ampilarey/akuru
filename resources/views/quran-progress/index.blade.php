@extends('layouts.app')

@section('title', 'Quran Progress')
@section('page-title', 'Quran Progress')

@section('page-actions')
@can('create_quran_progress')
<a href="{{ route('quran-progress.create') }}" class="btn btn-primary">
    <i class="fas fa-plus me-2"></i>
    @if(app()->getLocale() === 'ar')
        إضافة تقدم جديد
    @elseif(app()->getLocale() === 'dv')
        ތަފްސީލް އެހިގަނޑު
    @else
        Add Progress
    @endif
</a>
@endcan
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    @if(app()->getLocale() === 'ar')
                        تقدم القرآن الكريم
                    @elseif(app()->getLocale() === 'dv')
                        ޤުރުއާން ތަފްސީލް
                    @else
                        Quran Progress Records
                    @endif
                </h6>
            </div>
            <div class="card-body">
                @if($progress->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            الطالب
                                        @elseif(app()->getLocale() === 'dv')
                                            ރަގަޅު
                                        @else
                                            Student
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            السورة
                                        @elseif(app()->getLocale() === 'dv')
                                            ސޫރަތު
                                        @else
                                            Surah
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            النوع
                                        @elseif(app()->getLocale() === 'dv')
                                            ގަސް
                                        @else
                                            Type
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            الحالة
                                        @elseif(app()->getLocale() === 'dv')
                                            ހާލަތު
                                        @else
                                            Status
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            الدقة
                                        @elseif(app()->getLocale() === 'dv')
                                            ހަރުކާތް
                                        @else
                                            Accuracy
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            التاريخ
                                        @elseif(app()->getLocale() === 'dv')
                                            ތާރީޚު
                                        @else
                                            Date
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            الإجراءات
                                        @elseif(app()->getLocale() === 'dv')
                                            ކުރެވިދާނެ ކަމުތައް
                                        @else
                                            Actions
                                        @endif
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($progress as $record)
                                <tr>
                                    <td>
                                        @if($record->student)
                                            {{ $record->student->full_name }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $record->surah_name }}</strong>
                                            <br>
                                            <span class="arabic-text">{{ $record->surah_name_arabic }}</span>
                                        </div>
                                        @if($record->from_ayah && $record->to_ayah)
                                            <small class="text-muted">Ayah {{ $record->from_ayah }}-{{ $record->to_ayah }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            {{ ucfirst($record->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $record->status === 'completed' ? 'success' : ($record->status === 'in_progress' ? 'warning' : 'danger') }}">
                                            {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($record->accuracy_percentage)
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" style="width: {{ $record->accuracy_percentage }}%">
                                                    {{ $record->accuracy_percentage }}%
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $record->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('quran-progress.show', $record) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @can('edit_quran_progress')
                                            <a href="{{ route('quran-progress.edit', $record) }}" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('delete_quran_progress')
                                            <form action="{{ route('quran-progress.destroy', $record) }}" method="POST" style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
                    <div class="text-center py-4">
                        <i class="fas fa-quran fa-3x text-muted mb-3"></i>
                        <p class="text-muted">
                            @if(app()->getLocale() === 'ar')
                                لا توجد سجلات تقدم
                            @elseif(app()->getLocale() === 'dv')
                                ތަފްސީލް ރެކޯޑް ނެތް
                            @else
                                No progress records found
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
