@php $record = $record ?? null; @endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="student_id" class="form-label">Student</label>
        <select class="form-select" id="student_id" name="student_id" required>
            <option value="">Select Student</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected(old('student_id', $record?->student_id) == $student->id)>
                    {{ $student->full_name }} ({{ $student->student_id }})
                </option>
            @endforeach
        </select>
        @error('student_id')<div class="text-danger">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="teacher_id" class="form-label">Teacher</label>
        <select class="form-select" id="teacher_id" name="teacher_id" required>
            <option value="">Select Teacher</option>
            @foreach($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected(old('teacher_id', $record?->teacher_id) == $teacher->id)>
                    {{ $teacher->full_name }}
                </option>
            @endforeach
        </select>
        @error('teacher_id')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="surah_name" class="form-label">Surah Name (English)</label>
        <input type="text" class="form-control" id="surah_name" name="surah_name" value="{{ old('surah_name', $record?->surah_name) }}" required>
        @error('surah_name')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="surah_name_arabic" class="form-label">Surah Name (Arabic)</label>
        <input type="text" class="form-control arabic-text" id="surah_name_arabic" name="surah_name_arabic" value="{{ old('surah_name_arabic', $record?->surah_name_arabic) }}" required>
        @error('surah_name_arabic')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="surah_number" class="form-label">Surah Number</label>
        <input type="number" class="form-control" id="surah_number" name="surah_number" value="{{ old('surah_number', $record?->surah_number) }}" min="1" max="114" required>
        @error('surah_number')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="from_ayah" class="form-label">From Ayah</label>
        <input type="number" class="form-control" id="from_ayah" name="from_ayah" value="{{ old('from_ayah', $record?->from_ayah) }}" min="1">
        @error('from_ayah')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="to_ayah" class="form-label">To Ayah</label>
        <input type="number" class="form-control" id="to_ayah" name="to_ayah" value="{{ old('to_ayah', $record?->to_ayah) }}" min="1">
        @error('to_ayah')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="type" class="form-label">Type</label>
        <select class="form-select" id="type" name="type" required>
            <option value="">Select Type</option>
            @foreach(['memorization' => 'Memorization', 'recitation' => 'Recitation', 'revision' => 'Revision'] as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $record?->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="status" class="form-label">Status</label>
        <select class="form-select" id="status" name="status" required>
            <option value="">Select Status</option>
            @foreach(['in_progress' => 'In Progress', 'completed' => 'Completed', 'needs_revision' => 'Needs Revision'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $record?->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label for="accuracy_percentage" class="form-label">Accuracy Percentage (%)</label>
    <input type="number" class="form-control" id="accuracy_percentage" name="accuracy_percentage" value="{{ old('accuracy_percentage', $record?->accuracy_percentage) }}" min="0" max="100">
    @error('accuracy_percentage')<div class="text-danger">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="teacher_notes" class="form-label">Teacher Notes (English)</label>
    <textarea class="form-control" id="teacher_notes" name="teacher_notes" rows="3">{{ old('teacher_notes', $record?->teacher_notes) }}</textarea>
    @error('teacher_notes')<div class="text-danger">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="teacher_notes_arabic" class="form-label">Teacher Notes (Arabic)</label>
    <textarea class="form-control arabic-text" id="teacher_notes_arabic" name="teacher_notes_arabic" rows="3">{{ old('teacher_notes_arabic', $record?->teacher_notes_arabic) }}</textarea>
    @error('teacher_notes_arabic')<div class="text-danger">{{ $message }}</div>@enderror
</div>

<div class="d-flex justify-content-between">
    <a href="{{ route('quran-progress.index') }}" class="btn btn-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">Save</button>
</div>
