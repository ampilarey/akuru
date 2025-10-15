@extends('layouts.app')

@section('title', 'Edit Student')
@section('page-title', 'Edit Student')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    @if(app()->getLocale() === 'ar')
                        تعديل الطالب
                    @elseif(app()->getLocale() === 'dv')
                        ރަގަޅު ބަދަލުކުރުން
                    @else
                        Edit Student
                    @endif
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('students.update', $student) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
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
                                       id="name" name="name" value="{{ old('name', $student->user->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email', $student->user->email) }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">
                                @if(app()->getLocale() === 'ar')
                                    معلومات الطالب
                                @elseif(app()->getLocale() === 'dv')
                                    ރަގަޅު ތަފްސީލް
                                @else
                                    Student Information
                                @endif
                            </h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        رقم الطالب
                                    @elseif(app()->getLocale() === 'dv')
                                        ރަގަޅު ނަމްބަރު
                                    @else
                                        Student ID
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('student_id') is-invalid @enderror" 
                                       id="student_id" name="student_id" value="{{ old('student_id', $student->student_id) }}" required>
                                @error('student_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="class_id" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        الصف
                                    @elseif(app()->getLocale() === 'dv')
                                        ގަރަޑު
                                    @else
                                        Class
                                    @endif
                                </label>
                                <select class="form-select @error('class_id') is-invalid @enderror" 
                                        id="class_id" name="class_id" required>
                                    <option value="">
                                        @if(app()->getLocale() === 'ar')
                                            اختر الصف
                                        @elseif(app()->getLocale() === 'dv')
                                            ގަރަޑު ހިމްނުން
                                        @else
                                            Select Class
                                        @endif
                                    </option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}" {{ old('class_id', $student->class_id) == $class->id ? 'selected' : '' }}>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')
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
                                       id="first_name" name="first_name" value="{{ old('first_name', $student->first_name) }}" required>
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
                                       id="last_name" name="last_name" value="{{ old('last_name', $student->last_name) }}" required>
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        تاريخ الميلاد
                                    @elseif(app()->getLocale() === 'dv')
                                        އުފަން ދުވަހު
                                    @else
                                        Date of Birth
                                    @endif
                                </label>
                                <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" 
                                       id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $student->date_of_birth->format('Y-m-d')) }}" required>
                                @error('date_of_birth')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        الجنس
                                    @elseif(app()->getLocale() === 'dv')
                                        ހުށަހަޅާ
                                    @else
                                        Gender
                                    @endif
                                </label>
                                <select class="form-select @error('gender') is-invalid @enderror" 
                                        id="gender" name="gender" required>
                                    <option value="">
                                        @if(app()->getLocale() === 'ar')
                                            اختر الجنس
                                        @elseif(app()->getLocale() === 'dv')
                                            ހުށަހަޅާ ހިމްނުން
                                        @else
                                            Select Gender
                                        @endif
                                    </option>
                                    <option value="male" {{ old('gender', $student->gender) == 'male' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            ذكر
                                        @elseif(app()->getLocale() === 'dv')
                                            ފިރިހެނު
                                        @else
                                            Male
                                        @endif
                                    </option>
                                    <option value="female" {{ old('gender', $student->gender) == 'female' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            أنثى
                                        @elseif(app()->getLocale() === 'dv')
                                            އަންހެނު
                                        @else
                                            Female
                                        @endif
                                    </option>
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admission_date" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        تاريخ القبول
                                    @elseif(app()->getLocale() === 'dv')
                                        ގަހުން ދުވަހު
                                    @else
                                        Admission Date
                                    @endif
                                </label>
                                <input type="date" class="form-control @error('admission_date') is-invalid @enderror" 
                                       id="admission_date" name="admission_date" value="{{ old('admission_date', $student->admission_date->format('Y-m-d')) }}" required>
                                @error('admission_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        رقم الهاتف
                                    @elseif(app()->getLocale() === 'dv')
                                        ފޯނު ނަމްބަރު
                                    @else
                                        Phone Number
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                       id="phone" name="phone" value="{{ old('phone', $student->phone) }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        العنوان
                                    @elseif(app()->getLocale() === 'dv')
                                        ރާޖި
                                    @else
                                        Address
                                    @endif
                                </label>
                                <textarea class="form-control @error('address') is-invalid @enderror" 
                                          id="address" name="address" rows="3">{{ old('address', $student->address) }}</textarea>
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('students.index') }}" class="btn btn-secondary me-2">
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
                                تحديث الطالب
                            @elseif(app()->getLocale() === 'dv')
                                ރަގަޅު ބަދަލުކުރުން
                            @else
                                Update Student
                            @endif
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
