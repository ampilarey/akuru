@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <a href="{{ route('substitutions.requests.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 flex items-center mb-4">
                <i class="fas fa-arrow-left mr-2"></i>
                {{ __('Back to Substitution Requests') }}
            </a>
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ __('Substitution Request') }}
                    </h1>
                    <p class="mt-2 text-gray-600">
                        {{ $request->date->format('M d, Y') }} · {{ $request->period->name ?? $request->period->start_time ?? 'N/A' }}
                    </p>
                </div>
                @php
                    $statusColors = [
                        'open' => 'bg-yellow-100 text-yellow-800',
                        'assigned' => 'bg-blue-100 text-blue-800',
                        'closed' => 'bg-green-100 text-green-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ];
                @endphp
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$request->status] ?? 'bg-gray-100 text-gray-800' }}">
                    {{ ucfirst($request->status) }}
                </span>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">{{ __('Absent Teacher') }}</h3>
                        <p class="text-lg font-medium text-gray-900">
                            {{ $request->absentTeacher->user->name ?? 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">{{ __('Subject') }}</h3>
                        <p class="text-lg font-medium text-gray-900">
                            {{ $request->subject->name ?? 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">{{ __('Class') }}</h3>
                        <p class="text-lg font-medium text-gray-900">
                            {{ $request->classroom->name ?? 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">{{ __('Period') }}</h3>
                        <p class="text-lg font-medium text-gray-900">
                            {{ $request->period->name ?? $request->period->start_time ?? 'N/A' }}
                            @if($request->period)
                                ({{ $request->period->start_time }} - {{ $request->period->end_time }})
                            @endif
                        </p>
                    </div>
                </div>

                @if($request->assignment)
                    <div class="border-t pt-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">{{ __('Assigned To') }}</h3>
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-brandBlue-100 flex items-center justify-center mr-4">
                                <i class="fas fa-user text-brandBlue-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">
                                    {{ $request->assignment->substituteTeacher->user->name ?? 'N/A' }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ __('Assigned') }} {{ $request->assignment->assigned_at->format('M d, Y g:i A') }}
                                    @if($request->assignment->assignedBy)
                                        {{ __('by') }} {{ $request->assignment->assignedBy->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($request->notes)
                    <div class="border-t pt-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-2">{{ __('Notes') }}</h3>
                        <p class="text-gray-700 whitespace-pre-wrap">{{ $request->notes }}</p>
                    </div>
                @endif

                <div class="border-t pt-6 flex flex-wrap gap-3">
                    @if($request->status === 'open' && auth()->user()->hasRole('teacher') && !$request->assignment)
                        <form method="POST" action="{{ route('substitutions.requests.take', $request) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg"
                                    onclick="return confirm('{{ __('Are you sure you want to take this substitution?') }}')">
                                <i class="fas fa-hand-paper mr-2"></i>
                                {{ __('Take This Substitution') }}
                            </button>
                        </form>
                    @endif

                    @if($request->status === 'open')
                        <a href="{{ route('substitutions.requests.edit', $request) }}"
                           class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg inline-flex items-center">
                            <i class="fas fa-edit mr-2"></i>
                            {{ __('Edit') }}
                        </a>
                    @endif

                    <a href="{{ route('substitutions.requests.index') }}"
                       class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg inline-flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        {{ __('Back to List') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
