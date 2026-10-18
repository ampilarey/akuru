@extends('layouts.app')

@section('title', __('substitutions.show_title').' - '.config('app.name', 'Akuru LMS'))

@section('content')
@php
    $statusColors = [
        'open' => 'bg-yellow-100 text-yellow-800',
        'assigned' => 'bg-blue-100 text-blue-800',
        'closed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
    ];
@endphp
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <a href="{{ route('substitutions.requests.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 flex items-center mb-4">
                <i class="fas fa-arrow-left me-2 rtl:rotate-180"></i>
                {{ __('substitutions.back') }}
            </a>
            <div class="flex justify-between items-start gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ __('substitutions.show_title') }}
                    </h1>
                    <p class="mt-2 text-gray-600">
                        {{ $request->date->translatedFormat('j M Y') }} · {{ $request->period->name ?? '—' }}
                    </p>
                </div>
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$request->status] ?? 'bg-gray-100 text-gray-800' }}" data-testid="request-status">
                    {{ array_key_exists($request->status, $statusColors) ? __('substitutions.status_'.$request->status) : $request->status }}
                </span>
            </div>
        </div>

        {{-- The app layout prints no flash: what Take, Assign and Save said was said nowhere (slice TS1). --}}
        @foreach(['success' => 'green', 'error' => 'red'] as $key => $tone)
            @if(session($key))
                <div role="alert" class="mb-6 rounded border border-{{ $tone }}-200 bg-{{ $tone }}-50 px-4 py-3 text-sm text-{{ $tone }}-800" data-testid="flash-{{ $key }}">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">{{ __('substitutions.field_absent') }}</h3>
                        <p class="text-lg font-medium text-gray-900">
                            {{ $request->absentTeacher->user->name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">{{ __('substitutions.field_subject') }}</h3>
                        <p class="text-lg font-medium text-gray-900">
                            {{ $request->subject->name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">{{ __('substitutions.field_class') }}</h3>
                        <p class="text-lg font-medium text-gray-900">
                            {{ $request->classroom->name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">{{ __('substitutions.field_period') }}</h3>
                        <p class="text-lg font-medium text-gray-900">
                            {{ $request->period->name ?? '—' }}
                            @if($request->period)
                                <span dir="ltr">({{ $request->period->start_time }} – {{ $request->period->end_time }})</span>
                            @endif
                        </p>
                    </div>
                </div>

                @if($request->assignment)
                    <div class="border-t pt-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">{{ __('substitutions.covered_by') }}</h3>
                        <p class="font-medium text-gray-900" data-testid="substitute">
                            {{ $request->assignment->substituteTeacher->user->name ?? '—' }}
                        </p>
                        <p class="text-sm text-gray-500">
                            @if($request->assignment->assignedBy)
                                {{ __('substitutions.assigned_at_by', ['date' => $request->assignment->assigned_at->translatedFormat('j M Y, H:i'), 'name' => $request->assignment->assignedBy->name]) }}
                            @else
                                {{ __('substitutions.assigned_on', ['date' => $request->assignment->assigned_at->translatedFormat('j M Y, H:i')]) }}
                            @endif
                        </p>
                    </div>
                @endif

                @if($request->notes)
                    <div class="border-t pt-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">{{ __('substitutions.field_notes') }}</h3>
                        <p class="text-gray-700 whitespace-pre-wrap">{{ $request->notes }}</p>
                    </div>
                @endif

                {{-- The office's Assign had a route and no form (slice TS1). --}}
                @if($request->status === 'open' && $teachers->isNotEmpty())
                    <form method="POST" action="{{ route('substitutions.requests.assign', $request) }}" class="border-t pt-6 space-y-4" data-testid="assign-form">
                        @csrf
                        <h3 class="text-sm font-medium text-gray-500">{{ __('substitutions.assign_title') }}</h3>
                        <div>
                            <label for="substitute_teacher_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.assign_teacher') }} <span class="text-red-500">*</span></label>
                            <select name="substitute_teacher_id" id="substitute_teacher_id" required class="form-select w-full rounded-md">
                                <option value="">{{ __('substitutions.choose_teacher') }}</option>
                                @foreach($teachers as $teacher)
                                    @continue((int) $teacher->id === (int) $request->absent_teacher_id)
                                    <option value="{{ $teacher->id }}" @selected(old('substitute_teacher_id') == $teacher->id)>{{ $teacher->user->name ?? $teacher->full_name }}</option>
                                @endforeach
                            </select>
                            @error('substitute_teacher_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="assign_notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.assign_notes') }}</label>
                            <textarea name="notes" id="assign_notes" rows="2" class="form-input w-full rounded-md">{{ old('notes') }}</textarea>
                            @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="bg-brandBlue-500 hover:bg-brandBlue-600 text-white px-4 py-2 rounded-lg" data-testid="assign">{{ __('substitutions.assign') }}</button>
                    </form>
                @endif

                <div class="border-t pt-6 flex flex-wrap gap-3">
                    @if($request->status === 'open' && auth()->user()->hasRole('teacher') && !$request->assignment)
                        <form method="POST" action="{{ route('substitutions.requests.take', $request) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg"
                                    onclick="return confirm(@js(__('substitutions.take_confirm')))" data-testid="take">
                                <i class="fas fa-hand-paper me-2"></i>
                                {{ __('substitutions.take_this') }}
                            </button>
                        </form>
                    @endif

                    {{-- The office ends a request: covered (closed), or no longer needed (cancelled). --}}
                    @if($teachers->isNotEmpty() && $request->status === 'assigned')
                        <form method="POST" action="{{ route('substitutions.requests.finish', $request) }}" class="inline">
                            @csrf
                            <input type="hidden" name="outcome" value="closed">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg" data-testid="close-request">{{ __('substitutions.mark_covered') }}</button>
                        </form>
                    @endif
                    @if($teachers->isNotEmpty() && in_array($request->status, ['open', 'assigned'], true))
                        <form method="POST" action="{{ route('substitutions.requests.finish', $request) }}" class="inline">
                            @csrf
                            <input type="hidden" name="outcome" value="cancelled">
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg" onclick="return confirm(@js(__('substitutions.cancel_confirm')))" data-testid="cancel-request">{{ __('substitutions.cancel_request') }}</button>
                        </form>
                    @endif

                    @if($request->status === 'open')
                        <a href="{{ route('substitutions.requests.edit', $request) }}"
                           class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg inline-flex items-center" data-testid="edit">
                            <i class="fas fa-edit me-2"></i>
                            {{ __('substitutions.edit') }}
                        </a>
                    @endif

                    <a href="{{ route('substitutions.requests.index') }}"
                       class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg inline-flex items-center">
                        <i class="fas fa-list me-2"></i>
                        {{ __('substitutions.back_to_list') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
