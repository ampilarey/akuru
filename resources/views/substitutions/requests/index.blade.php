@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ __('Substitution Requests') }}
                    </h1>
                    <p class="mt-2 text-gray-600">
                        {{ __('Manage teacher substitution requests') }}
                    </p>
                </div>
                
                @can('create', App\Models\SubstitutionRequest::class)
                <div class="flex space-x-3">
                    <a href="{{ route('substitutions.requests.create') }}" 
                       class="bg-brandBlue-500 hover:bg-brandBlue-600 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        {{ __('New Request') }}
                    </a>
                </div>
                @endcan
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" action="{{ route('substitutions.requests.index') }}" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                    <select name="status" class="form-select w-full">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>{{ __('Open') }}</option>
                        <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>{{ __('Assigned') }}</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('Closed') }}</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                    </select>
                </div>
                
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date') }}</label>
                    <input type="date" name="date" value="{{ request('date') }}" class="form-input w-full">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-search mr-2"></i>
                        {{ __('Filter') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Substitution Requests -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            @if($requests->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Date & Time') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Absent Teacher') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Subject & Class') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Status') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Substitute') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($requests as $request)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $request->date->format('M d, Y') }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $request->period->name ?? 'N/A' }} 
                                        ({{ $request->period->start_time ?? '' }} - {{ $request->period->end_time ?? '' }})
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-brandBlue-100 flex items-center justify-center">
                                                <i class="fas fa-user text-brandBlue-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $request->absentTeacher->user->name ?? 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $request->subject->name ?? 'N/A' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $request->classroom->name ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'open' => 'bg-yellow-100 text-yellow-800',
                                            'assigned' => 'bg-blue-100 text-blue-800',
                                            'closed' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800'
                                        ];
                                    @endphp
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$request->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($request->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($request->assignment)
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $request->assignment->substituteTeacher->user->name ?? 'N/A' }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ __('Assigned') }} {{ $request->assignment->assigned_at->format('M d') }}
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400">{{ __('Not assigned') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        @if($request->status === 'open' && auth()->user()->hasRole('teacher') && !$request->assignment)
                                            <form method="POST" action="{{ route('substitutions.requests.take', $request) }}" class="inline">
                                                @csrf
                                                <button type="submit" 
                                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs"
                                                        onclick="return confirm('{{ __('Are you sure you want to take this substitution?') }}')">
                                                    <i class="fas fa-hand-paper mr-1"></i>
                                                    {{ __('Take') }}
                                                </button>
                                            </form>
                                        @endif
                                        
                                        <a href="{{ route('substitutions.requests.show', $request) }}" 
                                           class="bg-brandBlue-500 hover:bg-brandBlue-600 text-white px-3 py-1 rounded text-xs">
                                            <i class="fas fa-eye mr-1"></i>
                                            {{ __('View') }}
                                        </a>
                                        
                                        @if($request->status === 'open')
                                            <a href="{{ route('substitutions.requests.edit', $request) }}" 
                                               class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-xs">
                                                <i class="fas fa-edit mr-1"></i>
                                                {{ __('Edit') }}
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
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No substitution requests') }}</h3>
                    <p class="text-gray-500 mb-6">{{ __('There are no substitution requests matching your criteria.') }}</p>
                    @can('create', App\Models\SubstitutionRequest::class)
                    <a href="{{ route('substitutions.requests.create') }}" 
                       class="bg-brandBlue-500 hover:bg-brandBlue-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>
                        {{ __('Create First Request') }}
                    </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
