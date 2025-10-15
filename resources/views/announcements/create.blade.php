@extends('layouts.app')

@section('title', 'Create Announcement')
@section('page-title', 'Create New Announcement')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    @if(app()->getLocale() === 'ar')
                        إنشاء إعلان جديد
                    @elseif(app()->getLocale() === 'dv')
                        އިލާނު ހިމްނުން
                    @else
                        Create New Announcement
                    @endif
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('announcements.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="title" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        العنوان
                                    @elseif(app()->getLocale() === 'dv')
                                        ތާރީޚު
                                    @else
                                        Title
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                       id="title" name="title" value="{{ old('title') }}" required>
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        نوع الإعلان
                                    @elseif(app()->getLocale() === 'dv')
                                        އިލާނު ބާވަތް
                                    @else
                                        Announcement Type
                                    @endif
                                </label>
                                <select class="form-select @error('type') is-invalid @enderror" 
                                        id="type" name="type" required>
                                    <option value="">
                                        @if(app()->getLocale() === 'ar')
                                            اختر النوع
                                        @elseif(app()->getLocale() === 'dv')
                                            ބާވަތް ހިމްނުން
                                        @else
                                            Select Type
                                        @endif
                                    </option>
                                    <option value="general" {{ old('type') == 'general' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            عام
                                        @elseif(app()->getLocale() === 'dv')
                                            ގެނެވިފައި
                                        @else
                                            General
                                        @endif
                                    </option>
                                    <option value="academic" {{ old('type') == 'academic' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            أكاديمي
                                        @elseif(app()->getLocale() === 'dv')
                                            އެކެޑެމިކް
                                        @else
                                            Academic
                                        @endif
                                    </option>
                                    <option value="quran" {{ old('type') == 'quran' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            قرآني
                                        @elseif(app()->getLocale() === 'dv')
                                            ޤުރުއާން
                                        @else
                                            Quran
                                        @endif
                                    </option>
                                    <option value="event" {{ old('type') == 'event' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            حدث
                                        @elseif(app()->getLocale() === 'dv')
                                            ހާދިސާ
                                        @else
                                            Event
                                        @endif
                                    </option>
                                    <option value="holiday" {{ old('type') == 'holiday' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            عطلة
                                        @elseif(app()->getLocale() === 'dv')
                                            ހުށަހަޅާ
                                        @else
                                            Holiday
                                        @endif
                                    </option>
                                    <option value="emergency" {{ old('type') == 'emergency' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            طوارئ
                                        @elseif(app()->getLocale() === 'dv')
                                            ހަލާތު
                                        @else
                                            Emergency
                                        @endif
                                    </option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        الأولوية
                                    @elseif(app()->getLocale() === 'dv')
                                        ފުރަތުކުރުން
                                    @else
                                        Priority
                                    @endif
                                </label>
                                <select class="form-select @error('priority') is-invalid @enderror" 
                                        id="priority" name="priority" required>
                                    <option value="">
                                        @if(app()->getLocale() === 'ar')
                                            اختر الأولوية
                                        @elseif(app()->getLocale() === 'dv')
                                            ފުރަތުކުރުން ހިމްނުން
                                        @else
                                            Select Priority
                                        @endif
                                    </option>
                                    <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            منخفضة
                                        @elseif(app()->getLocale() === 'dv')
                                            ކުޑަ
                                        @else
                                            Low
                                        @endif
                                    </option>
                                    <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            متوسطة
                                        @elseif(app()->getLocale() === 'dv')
                                            މެދު
                                        @else
                                            Medium
                                        @endif
                                    </option>
                                    <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            عالية
                                        @elseif(app()->getLocale() === 'dv')
                                            ބޮޑު
                                        @else
                                            High
                                        @endif
                                    </option>
                                    <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            عاجل
                                        @elseif(app()->getLocale() === 'dv')
                                            ހަލާތު
                                        @else
                                            Urgent
                                        @endif
                                    </option>
                                </select>
                                @error('priority')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="publish_date" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        تاريخ النشر
                                    @elseif(app()->getLocale() === 'dv')
                                        ފެންވަތު
                                    @else
                                        Publish Date
                                    @endif
                                </label>
                                <input type="datetime-local" class="form-control @error('publish_date') is-invalid @enderror" 
                                       id="publish_date" name="publish_date" value="{{ old('publish_date') }}" required>
                                @error('publish_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expiry_date" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        تاريخ الانتهاء
                                    @elseif(app()->getLocale() === 'dv')
                                        ނިމުނީ
                                    @else
                                        Expiry Date
                                    @endif
                                </label>
                                <input type="datetime-local" class="form-control @error('expiry_date') is-invalid @enderror" 
                                       id="expiry_date" name="expiry_date" value="{{ old('expiry_date') }}">
                                @error('expiry_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="target_audience" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        الجمهور المستهدف
                                    @elseif(app()->getLocale() === 'dv')
                                        ގެންނަ
                                    @else
                                        Target Audience
                                    @endif
                                </label>
                                <select class="form-select @error('target_audience') is-invalid @enderror" 
                                        id="target_audience" name="target_audience[]" multiple>
                                    <option value="students" {{ in_array('students', old('target_audience', [])) ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            الطلاب
                                        @elseif(app()->getLocale() === 'dv')
                                            ރަގަޅުތައް
                                        @else
                                            Students
                                        @endif
                                    </option>
                                    <option value="teachers" {{ in_array('teachers', old('target_audience', [])) ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            المعلمون
                                        @elseif(app()->getLocale() === 'dv')
                                            އުސްތާދުތައް
                                        @else
                                            Teachers
                                        @endif
                                    </option>
                                    <option value="parents" {{ in_array('parents', old('target_audience', [])) ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            أولياء الأمور
                                        @elseif(app()->getLocale() === 'dv')
                                            ވަގުތުތައް
                                        @else
                                            Parents
                                        @endif
                                    </option>
                                    <option value="all" {{ in_array('all', old('target_audience', [])) ? 'selected' : '' }}>
                                        @if(app()->getLocale() === 'ar')
                                            الجميع
                                        @elseif(app()->getLocale() === 'dv')
                                            ހުރިހާ
                                        @else
                                            All
                                        @endif
                                    </option>
                                </select>
                                @error('target_audience')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="content" class="form-label">
                                    @if(app()->getLocale() === 'ar')
                                        المحتوى
                                    @elseif(app()->getLocale() === 'dv')
                                        ކޮންޓެންޓް
                                    @else
                                        Content
                                    @endif
                                </label>
                                <textarea class="form-control @error('content') is-invalid @enderror" 
                                          id="content" name="content" rows="6" required>{{ old('content') }}</textarea>
                                @error('content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('announcements.index') }}" class="btn btn-secondary me-2">
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
                                إنشاء الإعلان
                            @elseif(app()->getLocale() === 'dv')
                                އިލާނު ހިމްނުން
                            @else
                                Create Announcement
                            @endif
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
