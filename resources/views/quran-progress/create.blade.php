@extends('layouts.app')

@section('title', 'Add Quran Progress')
@section('page-title', 'Add Quran Progress')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    @if(app()->getLocale() === 'ar')
                        إضافة تقدم جديد في القرآن الكريم
                    @elseif(app()->getLocale() === 'dv')
                        ޤުރުއާން ތަފްސީލް އެހިގަނޑު
                    @else
                        Add New Quran Progress
                    @endif
                </h6>
            </div>
            <div class="card-body">
                <form action="{{ route('quran-progress.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    الطالب
                                @elseif(app()->getLocale() === 'dv')
                                    ރަގަޅު
                                @else
                                    Student
                                @endif
                            </label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                        {{ $student->full_name }} ({{ $student->student_id }})
                                    </option>
                                @endforeach
                            </select>
                            @error('student_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="teacher_id" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    المعلم
                                @elseif(app()->getLocale() === 'dv')
                                    އުސްތާދު
                                @else
                                    Teacher
                                @endif
                            </label>
                            <select class="form-select" id="teacher_id" name="teacher_id" required>
                                <option value="">Select Teacher</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('teacher_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="surah_name" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    اسم السورة (الإنجليزية)
                                @elseif(app()->getLocale() === 'dv')
                                    ސޫރަތުގެ ނަން (އިނގިރޭސި)
                                @else
                                    Surah Name (English)
                                @endif
                            </label>
                            <input type="text" class="form-control" id="surah_name" name="surah_name" value="{{ old('surah_name') }}" required>
                            @error('surah_name')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="surah_name_arabic" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    اسم السورة (العربية)
                                @elseif(app()->getLocale() === 'dv')
                                    ސޫރަތުގެ ނަން (ޢަރަބި)
                                @else
                                    Surah Name (Arabic)
                                @endif
                            </label>
                            <input type="text" class="form-control arabic-text" id="surah_name_arabic" name="surah_name_arabic" value="{{ old('surah_name_arabic') }}" required>
                            @error('surah_name_arabic')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="surah_number" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    رقم السورة
                                @elseif(app()->getLocale() === 'dv')
                                    ސޫރަތުގެ ނަމްބަރު
                                @else
                                    Surah Number
                                @endif
                            </label>
                            <input type="number" class="form-control" id="surah_number" name="surah_number" value="{{ old('surah_number') }}" min="1" max="114" required>
                            @error('surah_number')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="from_ayah" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    من الآية
                                @elseif(app()->getLocale() === 'dv')
                                    އާއިތު ފަށާ
                                @else
                                    From Ayah
                                @endif
                            </label>
                            <input type="number" class="form-control" id="from_ayah" name="from_ayah" value="{{ old('from_ayah') }}" min="1">
                            @error('from_ayah')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="to_ayah" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    إلى الآية
                                @elseif(app()->getLocale() === 'dv')
                                    އާއިތު ފަށާ
                                @else
                                    To Ayah
                                @endif
                            </label>
                            <input type="number" class="form-control" id="to_ayah" name="to_ayah" value="{{ old('to_ayah') }}" min="1">
                            @error('to_ayah')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    النوع
                                @elseif(app()->getLocale() === 'dv')
                                    ގަސް
                                @else
                                    Type
                                @endif
                            </label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Select Type</option>
                                <option value="memorization" {{ old('type') == 'memorization' ? 'selected' : '' }}>
                                    @if(app()->getLocale() === 'ar')
                                        حفظ
                                    @elseif(app()->getLocale() === 'dv')
                                        ހަފްޒު
                                    @else
                                        Memorization
                                    @endif
                                </option>
                                <option value="recitation" {{ old('type') == 'recitation' ? 'selected' : '' }}>
                                    @if(app()->getLocale() === 'ar')
                                        تلاوة
                                    @elseif(app()->getLocale() === 'dv')
                                        ތިލާވަތް
                                    @else
                                        Recitation
                                    @endif
                                </option>
                                <option value="revision" {{ old('type') == 'revision' ? 'selected' : '' }}>
                                    @if(app()->getLocale() === 'ar')
                                        مراجعة
                                    @elseif(app()->getLocale() === 'dv')
                                        ރެވިޒަން
                                    @else
                                        Revision
                                    @endif
                                </option>
                            </select>
                            @error('type')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">
                                @if(app()->getLocale() === 'ar')
                                    الحالة
                                @elseif(app()->getLocale() === 'dv')
                                    ހާލަތު
                                @else
                                    Status
                                @endif
                            </label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Select Status</option>
                                <option value="in_progress" {{ old('status') == 'in_progress' ? 'selected' : '' }}>
                                    @if(app()->getLocale() === 'ar')
                                        قيد التقدم
                                    @elseif(app()->getLocale() === 'dv')
                                        ތަފްސީލުގައި
                                    @else
                                        In Progress
                                    @endif
                                </option>
                                <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>
                                    @if(app()->getLocale() === 'ar')
                                        مكتمل
                                    @elseif(app()->getLocale() === 'dv')
                                        ފުރިހަމަ
                                    @else
                                        Completed
                                    @endif
                                </option>
                                <option value="needs_revision" {{ old('status') == 'needs_revision' ? 'selected' : '' }}>
                                    @if(app()->getLocale() === 'ar')
                                        يحتاج مراجعة
                                    @elseif(app()->getLocale() === 'dv')
                                        ރެވިޒަން ހުށަހަޅާ
                                    @else
                                        Needs Revision
                                    @endif
                                </option>
                            </select>
                            @error('status')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="accuracy_percentage" class="form-label">
                            @if(app()->getLocale() === 'ar')
                                نسبة الدقة (%)
                            @elseif(app()->getLocale() === 'dv')
                                ހަރުކާތު ރޭޓް (%)
                            @else
                                Accuracy Percentage (%)
                            @endif
                        </label>
                        <input type="number" class="form-control" id="accuracy_percentage" name="accuracy_percentage" value="{{ old('accuracy_percentage') }}" min="0" max="100">
                        @error('accuracy_percentage')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="teacher_notes" class="form-label">
                            @if(app()->getLocale() === 'ar')
                                ملاحظات المعلم (الإنجليزية)
                            @elseif(app()->getLocale() === 'dv')
                                އުސްތާދުގެ ނޮޓު (އިނގިރޭސި)
                            @else
                                Teacher Notes (English)
                            @endif
                        </label>
                        <textarea class="form-control" id="teacher_notes" name="teacher_notes" rows="3">{{ old('teacher_notes') }}</textarea>
                        @error('teacher_notes')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="teacher_notes_arabic" class="form-label">
                            @if(app()->getLocale() === 'ar')
                                ملاحظات المعلم (العربية)
                            @elseif(app()->getLocale() === 'dv')
                                އުސްތާދުގެ ނޮޓު (ޢަރަބި)
                            @else
                                Teacher Notes (Arabic)
                            @endif
                        </label>
                        <textarea class="form-control arabic-text" id="teacher_notes_arabic" name="teacher_notes_arabic" rows="3">{{ old('teacher_notes_arabic') }}</textarea>
                        @error('teacher_notes_arabic')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('quran-progress.index') }}" class="btn btn-secondary">
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
                                حفظ
                            @elseif(app()->getLocale() === 'dv')
                                ރަގަޅު
                            @else
                                Save
                            @endif
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
