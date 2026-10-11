@php $absence = $absence ?? null; @endphp

<div class="mb-3">
    <label for="teacher_id" class="form-label">{{ __('substitutions.field_teacher') }}</label>
    <select class="form-select" id="teacher_id" name="teacher_id" required>
        <option value="">{{ __('substitutions.choose_teacher') }}</option>
        @foreach($teachers as $teacher)
            <option value="{{ $teacher->id }}" @selected(old('teacher_id', $absence?->teacher_id) == $teacher->id)>
                {{ $teacher->full_name }}
            </option>
        @endforeach
    </select>
    @error('teacher_id')<div class="text-danger">{{ $message }}</div>@enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="from_date" class="form-label">{{ __('substitutions.field_from') }}</label>
        <input type="date" class="form-control" id="from_date" name="from_date" value="{{ old('from_date', $absence?->from_date?->format('Y-m-d')) }}" required>
        @error('from_date')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="to_date" class="form-label">{{ __('substitutions.field_to') }}</label>
        <input type="date" class="form-control" id="to_date" name="to_date" value="{{ old('to_date', $absence?->to_date?->format('Y-m-d')) }}" required>
        @error('to_date')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label for="reason" class="form-label">{{ __('substitutions.field_reason') }}</label>
    <textarea class="form-control" id="reason" name="reason" rows="3" required>{{ old('reason', $absence?->reason) }}</textarea>
    @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="note" class="form-label">{{ __('substitutions.field_note') }}</label>
    <textarea class="form-control" id="note" name="note" rows="2">{{ old('note', $absence?->note) }}</textarea>
    @error('note')<div class="text-danger">{{ $message }}</div>@enderror
</div>

@if($absence)
<div class="mb-3">
    <label for="status" class="form-label">{{ __('substitutions.field_status') }}</label>
    <select class="form-select" id="status" name="status" required>
        @foreach(['pending', 'approved', 'rejected'] as $status)
            <option value="{{ $status }}" @selected(old('status', $absence->status) === $status)>{{ __('substitutions.absence_status_'.$status) }}</option>
        @endforeach
    </select>
    @error('status')<div class="text-danger">{{ $message }}</div>@enderror
</div>
@endif

<div class="d-flex justify-content-between">
    <a href="{{ route('absences.index') }}" class="btn btn-secondary">{{ __('substitutions.cancel') }}</a>
    <button type="submit" class="btn btn-primary" data-testid="absence-save">{{ __('substitutions.save') }}</button>
</div>
