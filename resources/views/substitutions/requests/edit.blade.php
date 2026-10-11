@extends('layouts.app')

@section('title', __('substitutions.edit_title').' - '.config('app.name', 'Akuru LMS'))

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <a href="{{ route('substitutions.requests.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 flex items-center mb-4">
                <i class="fas fa-arrow-left me-2 rtl:rotate-180"></i>
                {{ __('substitutions.back') }}
            </a>
            <h1 class="text-3xl font-bold text-gray-900">
                {{ __('substitutions.edit_title') }}
            </h1>
            <p class="mt-2 text-gray-600">
                {{ __('substitutions.edit_intro', ['date' => $request->date->translatedFormat('j M Y')]) }}
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" action="{{ route('substitutions.requests.update', $request) }}" data-testid="request-form">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.field_date') }} <span class="text-red-500">*</span></label>
                        <input type="date" name="date" id="date" value="{{ old('date', $request->date->format('Y-m-d')) }}" required
                               min="{{ date('Y-m-d') }}"
                               class="form-input w-full rounded-md @error('date') border-red-500 @enderror">
                        @error('date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="absent_teacher_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.field_absent') }} <span class="text-red-500">*</span></label>
                        <select name="absent_teacher_id" id="absent_teacher_id" required
                                class="form-select w-full rounded-md @error('absent_teacher_id') border-red-500 @enderror">
                            <option value="">{{ __('substitutions.choose_teacher') }}</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" {{ old('absent_teacher_id', $request->absent_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->user->name ?? $teacher->full_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('absent_teacher_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.field_subject') }} <span class="text-red-500">*</span></label>
                        <select name="subject_id" id="subject_id" required
                                class="form-select w-full rounded-md @error('subject_id') border-red-500 @enderror">
                            <option value="">{{ __('substitutions.choose_subject') }}</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" {{ old('subject_id', $request->subject_id) == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('subject_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="classroom_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.field_class') }} <span class="text-red-500">*</span></label>
                        <select name="classroom_id" id="classroom_id" required
                                class="form-select w-full rounded-md @error('classroom_id') border-red-500 @enderror">
                            <option value="">{{ __('substitutions.choose_class') }}</option>
                            @foreach($classrooms as $classroom)
                                <option value="{{ $classroom->id }}" {{ old('classroom_id', $request->classroom_id) == $classroom->id ? 'selected' : '' }}>
                                    {{ $classroom->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('classroom_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="period_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.field_period') }} <span class="text-red-500">*</span></label>
                        <select name="period_id" id="period_id" required
                                class="form-select w-full rounded-md @error('period_id') border-red-500 @enderror">
                            <option value="">{{ __('substitutions.choose_period') }}</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}" {{ old('period_id', $request->period_id) == $period->id ? 'selected' : '' }}>
                                    {{ $period->name ?? '' }} ({{ $period->start_time }}–{{ $period->end_time }})
                                </option>
                            @endforeach
                        </select>
                        @error('period_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.field_notes') }}</label>
                    <textarea name="notes" id="notes" rows="4"
                              class="form-input w-full rounded-md @error('notes') border-red-500 @enderror"
                              placeholder="{{ __('substitutions.notes_placeholder') }}">{{ old('notes', $request->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <a href="{{ route('substitutions.requests.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        {{ __('substitutions.cancel') }}
                    </a>
                    <button type="submit" class="px-4 py-2 bg-brandBlue-500 hover:bg-brandBlue-600 text-white rounded-lg" data-testid="request-save">
                        <i class="fas fa-save me-2"></i>
                        {{ __('substitutions.save_changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
