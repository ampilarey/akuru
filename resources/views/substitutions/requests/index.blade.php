@extends('layouts.app')

@section('title', __('substitutions.requests_title').' - '.config('app.name', 'Akuru LMS'))

@section('content')
@php
    $statusColors = [
        'open' => 'bg-yellow-100 text-yellow-800',
        'assigned' => 'bg-blue-100 text-blue-800',
        'closed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
    ];
    $statuses = array_keys($statusColors);
@endphp
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ __('substitutions.requests_title') }}
                    </h1>
                    <p class="mt-2 text-gray-600">
                        {{ __('substitutions.requests_intro') }}
                    </p>
                </div>

                {{-- Anyone the routes let in may make a request; the button asked a policy nobody wrote, so it showed to nobody (slice TS1). --}}
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('absences.index') }}" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg" data-testid="absences-link">
                        {{ __('substitutions.teacher_absences_link') }}
                    </a>
                    <a href="{{ route('substitutions.requests.create') }}"
                       class="bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white px-4 py-2 rounded-lg flex items-center" data-testid="new-request">
                        <i class="fas fa-plus me-2"></i>
                        {{ __('substitutions.new_request') }}
                    </a>
                </div>
            </div>
        </div>

        @foreach(['success' => 'green', 'error' => 'red'] as $key => $tone)
            @if(session($key))
                <div role="alert" class="mb-6 rounded border border-{{ $tone }}-200 bg-{{ $tone }}-50 px-4 py-3 text-sm text-{{ $tone }}-800" data-testid="flash-{{ $key }}">{{ session($key) }}</div>
            @endif
        @endforeach

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" action="{{ route('substitutions.requests.index') }}" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-48">
                    <label for="filter-status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.filter_status') }}</label>
                    <select id="filter-status" name="status" class="form-select w-full">
                        <option value="">{{ __('substitutions.all_statuses') }}</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ __('substitutions.status_'.$status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 min-w-48">
                    <label for="filter-date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('substitutions.filter_date') }}</label>
                    <input id="filter-date" type="date" name="date" value="{{ request('date') }}" class="form-input w-full">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-search me-2"></i>
                        {{ __('substitutions.filter') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Substitution Requests -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            @if($requests->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table-stack min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('substitutions.col_when') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('substitutions.col_absent') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('substitutions.col_subject_class') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('substitutions.col_status') }}</th>
                                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('substitutions.col_substitute') }}</th>
                                <th class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('substitutions.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($requests as $request)
                            <tr class="hover:bg-gray-50" data-testid="request-row" data-status="{{ $request->status }}">
                                <td class="px-6 py-4 sm:whitespace-nowrap" data-label="{{ __('substitutions.col_when') }}">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $request->date->translatedFormat('j M Y') }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $request->period->name ?? '—' }}
                                        @if($request->period)
                                            <span dir="ltr">({{ $request->period->start_time }} – {{ $request->period->end_time }})</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 sm:whitespace-nowrap" data-label="{{ __('substitutions.col_absent') }}">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $request->absentTeacher->user->name ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 sm:whitespace-nowrap" data-label="{{ __('substitutions.col_subject_class') }}">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $request->subject->name ?? '—' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $request->classroom->name ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 sm:whitespace-nowrap" data-label="{{ __('substitutions.col_status') }}">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$request->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ in_array($request->status, $statuses, true) ? __('substitutions.status_'.$request->status) : $request->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 sm:whitespace-nowrap" data-label="{{ __('substitutions.col_substitute') }}">
                                    @if($request->assignment)
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $request->assignment->substituteTeacher->user->name ?? '—' }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ __('substitutions.assigned_on', ['date' => $request->assignment->assigned_at->translatedFormat('j M')]) }}
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400">{{ __('substitutions.not_assigned') }}</span>
                                    @endif
                                </td>
                                <td class="table-actions px-6 py-4 text-sm font-medium sm:whitespace-nowrap sm:text-end">
                                    <div class="flex justify-end gap-2">
                                        @if($request->status === 'open' && auth()->user()->hasRole('teacher') && !$request->assignment)
                                            <form method="POST" action="{{ route('substitutions.requests.take', $request) }}" class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs"
                                                        onclick="return confirm(@js(__('substitutions.take_confirm')))" data-testid="take">
                                                    <i class="fas fa-hand-paper me-1"></i>
                                                    {{ __('substitutions.take') }}
                                                </button>
                                            </form>
                                        @endif

                                        <a href="{{ route('substitutions.requests.show', $request) }}"
                                           class="bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white px-3 py-1 rounded text-xs" data-testid="view">
                                            <i class="fas fa-eye me-1"></i>
                                            {{ __('substitutions.view') }}
                                        </a>

                                        @if($request->status === 'open')
                                            <a href="{{ route('substitutions.requests.edit', $request) }}"
                                               class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-xs">
                                                <i class="fas fa-edit me-1"></i>
                                                {{ __('substitutions.edit') }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-6 py-3 border-t border-gray-200">
                    {{ $requests->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <div class="w-24 h-24 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-clipboard-list text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('substitutions.empty_title') }}</h3>
                    <p class="text-gray-500 mb-6">{{ __('substitutions.empty_body') }}</p>
                    <a href="{{ route('substitutions.requests.create') }}"
                       class="bg-brandMaroon-600 hover:bg-brandMaroon-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus me-2"></i>
                        {{ __('substitutions.create_first') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
