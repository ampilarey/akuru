@php $record = $record ?? null; @endphp

<div class="grid gap-3 md:grid-cols-2">
    <div class="col-md-6 mb-3">
        <label for="student_id" class="form-label block mb-1">{{ __('quran.qp_student') }}</label>
        <select class="form-input w-full form-select" id="student_id" name="student_id" required>
            <option value="">{{ __('quran.qp_select_student') }}</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected(old('student_id', $record?->student_id) == $student->id)>
                    {{ $student->full_name }} ({{ $student->student_id }})
                </option>
            @endforeach
        </select>
        @error('student_id')<div class="text-danger">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="teacher_id" class="form-label block mb-1">{{ __('quran.qp_teacher') }}</label>
        <select class="form-input w-full form-select" id="teacher_id" name="teacher_id" required>
            <option value="">{{ __('quran.qp_select_teacher') }}</option>
            @foreach($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected(old('teacher_id', $record?->teacher_id) == $teacher->id)>
                    {{ $teacher->full_name }}
                </option>
            @endforeach
        </select>
        @error('teacher_id')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="grid gap-3 md:grid-cols-2">
    <div class="col-md-6 mb-3">
        <label for="surah_name" class="form-label block mb-1">{{ __('quran.qp_surah_name_en') }}</label>
        <input type="text" class="form-input w-full form-control" id="surah_name" name="surah_name" value="{{ old('surah_name', $record?->surah_name) }}" required>
        @error('surah_name')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="surah_name_arabic" class="form-label block mb-1">{{ __('quran.qp_surah_name_ar') }}</label>
        <input type="text" class="form-input w-full form-control arabic-text" lang="ar" dir="rtl" id="surah_name_arabic" name="surah_name_arabic" value="{{ old('surah_name_arabic', $record?->surah_name_arabic) }}" required>
        @error('surah_name_arabic')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="grid gap-3 md:grid-cols-2">
    <div class="col-md-4 mb-3">
        <label for="surah_number" class="form-label block mb-1">{{ __('quran.qp_surah_number') }}</label>
        <input type="number" class="form-input w-full form-control" id="surah_number" name="surah_number" value="{{ old('surah_number', $record?->surah_number) }}" min="1" max="114" required>
        @error('surah_number')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="from_ayah" class="form-label block mb-1">{{ __('quran.qp_from_ayah') }}</label>
        <input type="number" class="form-input w-full form-control" id="from_ayah" name="from_ayah" value="{{ old('from_ayah', $record?->from_ayah) }}" min="1">
        @error('from_ayah')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="to_ayah" class="form-label block mb-1">{{ __('quran.qp_to_ayah') }}</label>
        <input type="number" class="form-input w-full form-control" id="to_ayah" name="to_ayah" value="{{ old('to_ayah', $record?->to_ayah) }}" min="1">
        @error('to_ayah')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="grid gap-3 md:grid-cols-2">
    <div class="col-md-6 mb-3">
        <label for="type" class="form-label block mb-1">{{ __('quran.qp_type') }}</label>
        <select class="form-input w-full form-select" id="type" name="type" required>
            <option value="">{{ __('quran.qp_select_type') }}</option>
            @foreach(['memorization', 'recitation', 'revision'] as $value)
                <option value="{{ $value }}" @selected(old('type', $record?->type) === $value)>{{ __('quran.qp_type_'.$value) }}</option>
            @endforeach
        </select>
        @error('type')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="status" class="form-label block mb-1">{{ __('quran.qp_status') }}</label>
        <select class="form-input w-full form-select" id="status" name="status" required>
            <option value="">{{ __('quran.qp_select_status') }}</option>
            @foreach(['in_progress', 'completed', 'needs_revision'] as $value)
                <option value="{{ $value }}" @selected(old('status', $record?->status) === $value)>{{ __('quran.qp_status_'.$value) }}</option>
            @endforeach
        </select>
        @error('status')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label for="accuracy_percentage" class="form-label block mb-1">{{ __('quran.qp_accuracy_percent') }}</label>
    <input type="number" class="form-input w-full form-control" id="accuracy_percentage" name="accuracy_percentage" value="{{ old('accuracy_percentage', $record?->accuracy_percentage) }}" min="0" max="100">
    @error('accuracy_percentage')<div class="text-danger">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="teacher_notes" class="form-label block mb-1">{{ __('quran.qp_notes_en') }}</label>
    <textarea class="form-input w-full form-control" id="teacher_notes" name="teacher_notes" rows="3">{{ old('teacher_notes', $record?->teacher_notes) }}</textarea>
    @error('teacher_notes')<div class="text-danger">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="teacher_notes_arabic" class="form-label block mb-1">{{ __('quran.qp_notes_ar') }}</label>
    <textarea class="form-input w-full form-control arabic-text" lang="ar" dir="rtl" id="teacher_notes_arabic" name="teacher_notes_arabic" rows="3">{{ old('teacher_notes_arabic', $record?->teacher_notes_arabic) }}</textarea>
    @error('teacher_notes_arabic')<div class="text-danger">{{ $message }}</div>@enderror
</div>

<div class="d-flex justify-content-between">
    <a href="{{ route('quran-progress.index') }}" class="btn btn-secondary">{{ __('quran.qp_cancel') }}</a>
    <button type="submit" class="btn btn-primary">{{ __('quran.qp_save') }}</button>
</div>
