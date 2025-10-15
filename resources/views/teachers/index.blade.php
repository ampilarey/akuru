@extends('layouts.app')

@section('title', 'Teachers')
@section('page-title', 'Teachers Management')

@section('page-actions')
    @can('create_teachers')
        <a href="{{ route('teachers.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            @if(app()->getLocale() === 'ar')
                إضافة معلم
            @elseif(app()->getLocale() === 'dv')
                އުސްތާދު ހިމްނުން
            @else
                Add Teacher
            @endif
        </a>
    @endcan
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    @if(app()->getLocale() === 'ar')
                        قائمة المعلمين
                    @elseif(app()->getLocale() === 'dv')
                        އުސްތާދުތަކުގެ ލިސްޓް
                    @else
                        Teachers List
                    @endif
                </h5>
            </div>
            <div class="card-body">
                @if($teachers->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            الاسم
                                        @elseif(app()->getLocale() === 'dv')
                                            ނަން
                                        @else
                                            Name
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            رقم المعلم
                                        @elseif(app()->getLocale() === 'dv')
                                            އުސްތާދު ނަމްބަރު
                                        @else
                                            Teacher ID
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            التخصص
                                        @elseif(app()->getLocale() === 'dv')
                                            ހާދިސާ
                                        @else
                                            Specialization
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            المؤهلات
                                        @elseif(app()->getLocale() === 'dv')
                                            ގުޅުންހުރި
                                        @else
                                            Qualification
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
                                            الإجراءات
                                        @elseif(app()->getLocale() === 'dv')
                                            ކުރިން
                                        @else
                                            Actions
                                        @endif
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($teachers as $teacher)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($teacher->photo)
                                                    <img src="{{ asset('storage/' . $teacher->photo) }}" 
                                                         alt="{{ $teacher->full_name }}" 
                                                         class="rounded-circle me-2" 
                                                         width="40" height="40">
                                                @else
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 40px; height: 40px;">
                                                        {{ substr($teacher->first_name, 0, 1) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="fw-bold">{{ $teacher->full_name }}</div>
                                                    <small class="text-muted">{{ $teacher->user->email }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $teacher->teacher_id }}</td>
                                        <td>{{ $teacher->specialization }}</td>
                                        <td>{{ $teacher->qualification }}</td>
                                        <td>
                                            <span class="badge bg-{{ $teacher->status === 'active' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($teacher->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('teachers.show', $teacher) }}" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                @can('edit_teachers')
                                                    <a href="{{ route('teachers.edit', $teacher) }}" 
                                                       class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">
                            @if(app()->getLocale() === 'ar')
                                لا يوجد معلمون مسجلون
                            @elseif(app()->getLocale() === 'dv')
                                އުސްތާދުތައް ނެތް
                            @else
                                No teachers found
                            @endif
                        </h5>
                        @can('create_teachers')
                            <a href="{{ route('teachers.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>
                                @if(app()->getLocale() === 'ar')
                                    إضافة أول معلم
                                @elseif(app()->getLocale() === 'dv')
                                    އުސްތާދު ހިމްނުން
                                @else
                                    Add First Teacher
                                @endif
                            </a>
                        @endcan
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
