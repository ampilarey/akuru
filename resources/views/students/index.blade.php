@extends('layouts.app')

@section('title', 'Students')
@section('page-title', 'Students Management')

@section('page-actions')
    @can('create_students')
        <a href="{{ route('students.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            @if(app()->getLocale() === 'ar')
                إضافة طالب
            @elseif(app()->getLocale() === 'dv')
                ރަގަޅު ހިމްނުން
            @else
                Add Student
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
                        قائمة الطلاب
                    @elseif(app()->getLocale() === 'dv')
                        ރަގަޅުތަކުގެ ލިސްޓް
                    @else
                        Students List
                    @endif
                </h5>
            </div>
            <div class="card-body">
                @if($students->count() > 0)
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
                                            رقم الطالب
                                        @elseif(app()->getLocale() === 'dv')
                                            ރަގަޅު ނަމްބަރު
                                        @else
                                            Student ID
                                        @endif
                                    </th>
                                    <th>
                                        @if(app()->getLocale() === 'ar')
                                            الصف
                                        @elseif(app()->getLocale() === 'dv')
                                            ގަރަޑު
                                        @else
                                            Class
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
                                @foreach($students as $student)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($student->photo)
                                                    <img src="{{ asset('storage/' . $student->photo) }}" 
                                                         alt="{{ $student->full_name }}" 
                                                         class="rounded-circle me-2" 
                                                         width="40" height="40">
                                                @else
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 40px; height: 40px;">
                                                        {{ substr($student->first_name, 0, 1) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="fw-bold">{{ $student->full_name }}</div>
                                                    <small class="text-muted">{{ $student->user->email }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $student->student_id }}</td>
                                        <td>{{ $student->classRoom->name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($student->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('students.show', $student) }}" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                @can('edit_students')
                                                    <a href="{{ route('students.edit', $student) }}" 
                                                       class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                @endcan
                                                @can('view_quran_progress')
                                                    <a href="{{ route('students.quran-progress', $student) }}" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-quran"></i>
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
                        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">
                            @if(app()->getLocale() === 'ar')
                                لا توجد طلاب مسجلين
                            @elseif(app()->getLocale() === 'dv')
                                ރަގަޅުތައް ނެތް
                            @else
                                No students found
                            @endif
                        </h5>
                        @can('create_students')
                            <a href="{{ route('students.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>
                                @if(app()->getLocale() === 'ar')
                                    إضافة أول طالب
                                @elseif(app()->getLocale() === 'dv')
                                    ރަގަޅު ހިމްނުން
                                @else
                                    Add First Student
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
