@extends('layouts.app')

@section('title', 'Add Teacher')
@section('page-title', 'Add New Teacher')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    @if(app()->getLocale() === 'ar')
                        إضافة معلم جديد
                    @elseif(app()->getLocale() === 'dv')
                        އުސްތާދު ހިމްނުން
                    @else
                        Add New Teacher
                    @endif
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('teachers.store') }}" method="POST">
                    @csrf
                    
                    <!-- User Account Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">
                                @if(app()->getLocale() === 'ar')
                                    معلومات الحساب
                                @elseif(app()->getLocale() === 'dv')
                                    އެކައުންޓު ތަފްސީލް
                                @else
                                    Account Information
                                @endif
                            </h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        الاسم الكامل
                                    @elseif(app()->getLocale() === 'dv')
                                        ފުރި ނަން
                                    @else
                                        Full Name
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email') }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        كلمة المرور
                                    @elseif(app()->getLocale() === 'dv')
                                        ރެސްވޯޑް
                                    @else
                                        Password
                                    @endif
                                </label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        تأكيد كلمة المرور
                                    @elseif(app()->getLocale() === 'dv')
                                        ރެސްވޯޑް ތަފްސީލް
                                    @else
                                        Confirm Password
                                    @endif
                                </label>
                                <input type="password" class="form-control" 
                                       id="password_confirmation" name="password_confirmation" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Teacher Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">
                                @if(app()->getLocale() === 'ar')
                                    معلومات المعلم
                                @elseif(app()->getLocale() === 'dv')
                                    އުސްތާދު ތަފްސީލް
                                @else
                                    Teacher Information
                                @endif
                            </h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        رقم المعلم
                                    @elseif(app()->getLocale() === 'dv')
                                        އުސްތާދު ނަމްބަރު
                                    @else
                                        Teacher ID
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('teacher_id') is-invalid @enderror" 
                                       id="teacher_id" name="teacher_id" value="{{ old('teacher_id') }}" required>
                                @error('teacher_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="school_id" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        المدرسة
                                    @elseif(app()->getLocale() === 'dv')
                                        ސްކޫލު
                                    @else
                                        School
                                    @endif
                                </label>
                                <select class="form-select @error('school_id') is-invalid @enderror" 
                                        id="school_id" name="school_id" required>
                                    <option value="">
                                        @if(app()->getLocale() === 'ar')
                                            اختر المدرسة
                                        @elseif(app()->getLocale() === 'dv')
                                            ސްކޫލު ހިމްނުން
                                        @else
                                            Select School
                                        @endif
                                    </option>
                                    @foreach($schools as $school)
                                        <option value="{{ $school->id }}" {{ old('school_id') == $school->id ? 'selected' : '' }}>
                                            {{ $school->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('school_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        الاسم الأول
                                    @elseif(app()->getLocale() === 'dv')
                                        ފުރި ނަން
                                    @else
                                        First Name
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('first_name') is-invalid @enderror" 
                                       id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                                @error('first_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        اسم العائلة
                                    @elseif(app()->getLocale() === 'dv')
                                        ގަރަޑު ނަން
                                    @else
                                        Last Name
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('last_name') is-invalid @enderror" 
                                       id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="qualification" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        المؤهلات
                                    @elseif(app()->getLocale() === 'dv')
                                        ގުޅުންހުރި
                                    @else
                                        Qualification
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('qualification') is-invalid @enderror" 
                                       id="qualification" name="qualification" value="{{ old('qualification') }}" required>
                                @error('qualification')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="specialization" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        التخصص
                                    @elseif(app()->getLocale() === 'dv')
                                        ހާދިސާ
                                    @else
                                        Specialization
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('specialization') is-invalid @enderror" 
                                       id="specialization" name="specialization" value="{{ old('specialization') }}" required>
                                @error('specialization')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="joining_date" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        تاريخ الانضمام
                                    @elseif(app()->getLocale() === 'dv')
                                        ގަހުން ދުވަހު
                                    @else
                                        Joining Date
                                    @endif
                                </label>
                                <input type="date" class="form-control @error('joining_date') is-invalid @enderror" 
                                       id="joining_date" name="joining_date" value="{{ old('joining_date') }}" required>
                                @error('joining_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="salary" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        الراتب
                                    @elseif(app()->getLocale() === 'dv')
                                        ރަޖަހު
                                    @else
                                        Salary
                                    @endif
                                </label>
                                <input type="number" step="0.01" class="form-control @error('salary') is-invalid @enderror" 
                                       id="salary" name="salary" value="{{ old('salary') }}">
                                @error('salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <!-- Subjects -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">
                                @if(app()->getLocale() === 'ar')
                                    المواد التي يدرسها
                                @elseif(app()->getLocale() === 'dv')
                                    ދަރުސް ކުރާ ބާވަތް
                                @else
                                    Subjects to Teach
                                @endif
                            </h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <div class="row">
                                    @foreach($subjects as $subject)
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="subjects[]" value="{{ $subject->id }}" 
                                                       id="subject_{{ $subject->id }}"
                                                       {{ in_array($subject->id, old('subjects', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subject_{{ $subject->id }}">
                                                    {{ $subject->name }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('teachers.index') }}" class="btn btn-secondary me-2">
                            @if(app()->getLocale() === 'ar')
                                إلغاء
                            @elseif(app()->getLocale() === 'dv')
                                ކުރަން
                            @else
                                Cancel
                            @endif
                        </a>
                        <button type="submit" class="btn btn-primary">
                            @if(app()->getLocale() === 'ar')
                                إضافة المعلم
                            @elseif(app()->getLocale() === 'dv')
                                އުސްތާދު ހިމްނުން
                            @else
                                Add Teacher
                            @endif
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
