@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold text-gray-900">Leads</h1>
            <a href="{{ route('admin.courses.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">
                Manage Courses →
            </a>
        </div>
        <a href="{{ route('admin.leads.export', request()->only(['source', 'status', 'course_id'])) }}" class="btn-secondary">
            Export CSV
        </a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Source</label>
            <select name="source" class="form-input rounded-md">
                <option value="">All</option>
                @foreach(['syllabus' => 'Syllabus', 'waiting_list' => 'Waiting list', 'callback' => 'Callback'] as $val => $label)
                    <option value="{{ $val }}" @selected(($filters['source'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>
            <select name="status" class="form-input rounded-md">
                <option value="">All</option>
                @foreach(['new' => 'New', 'contacted' => 'Contacted', 'converted' => 'Converted', 'closed' => 'Closed'] as $val => $label)
                    <option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
    </form>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">When</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($leads as $lead)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $lead['created_at'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $lead['course_title'] }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $lead['name'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $lead['mobile'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $lead['email'] ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $lead['source'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $lead['status'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">No leads yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
