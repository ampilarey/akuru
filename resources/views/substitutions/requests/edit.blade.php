@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <a href="{{ route('substitutions.requests.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 flex items-center mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                {{ __('Back to Substitution Requests') }}
            </a>
            <h1 class="text-3xl font-bold text-gray-900">
                {{ __('Edit Substitution Request') }}
            </h1>
            <p class="mt-2 text-gray-600">
                {{ __('Edit substitution request for') }} {{ $request->date->format('M d, Y') }}
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" action="{{ route('substitutions.requests.update', $request) }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date') }} <span class="text-red-500">*</span></label>
                        <input type="date" name="date" id="date"
                               value="{{ old('date', $request->date->format('Y-m-d')) }}" required
                               min="{{ date('Y-m-d') }}"
                               class="form-input w-full rounded-md @error('date') border-red-500 @enderror">
                        @error('date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="absent_teacher_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Absent Teacher') }} <span class="text-red-500">*</span></label>
                        <select name="absent_teacher_id" id="absent_teacher_id" required
                                class="form-select w-full rounded-md @error('absent_teacher_id') border-red-500 @enderror">
                            <option value="">{{ __('Select Teacher') }}</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" {{ old('absent_teacher_id', $request->absent_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->user->name ?? 'N/A' }}
                                </option>
                            @endforeach
                        </select>
                        @error('absent_teacher_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                        <select name="subject_id" id="subject_id" required
                                class="form-select w-full rounded-md @error('subject_id') border-red-500 @enderror">
                            <option value="">{{ __('Select Subject') }}</option>
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
                        <label for="classroom_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class') }} <span class="text-red-500">*</span></label>
                        <select name="classroom_id" id="classroom_id" required
                                class="form-select w-full rounded-md @error('classroom_id') border-red-500 @enderror">
                            <option value="">{{ __('Select Class') }}</option>
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
                        <label for="period_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Period') }} <span class="text-red-500">*</span></label>
                        <select name="period_id" id="period_id" required
                                class="form-select w-full rounded-md @error('period_id') border-red-500 @enderror">
                            <option value="">{{ __('Select Period') }}</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}" {{ old('period_id', $request->period_id) == $period->id ? 'selected' : '' }}>
                                    {{ $period->name ?? $period->start_time }} - {{ $period->end_time ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('period_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" id="notes" rows="4"
                              class="form-input w-full rounded-md @error('notes') border-red-500 @enderror"
                              placeholder="{{ __('Optional instructions for the substitute teacher...') }}">{{ old('notes', $request->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-8 flex justify-end space-x-3">
                    <a href="{{ route('substitutions.requests.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="px-4 py-2 bg-brandBlue-500 hover:bg-brandBlue-600 text-white rounded-lg">
                        <i class="fas fa-save mr-2"></i>
                        {{ __('Update Request') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
