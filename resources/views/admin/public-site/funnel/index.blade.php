@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold text-gray-900">Course funnel</h1>
            <a href="{{ route('admin.leads.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">
                Leads →
            </a>
            <a href="{{ route('admin.courses.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">
                Manage Courses →
            </a>
        </div>
        <a href="{{ route('admin.funnel.export', request()->only(['course_id'])) }}" class="btn-secondary">
            Export CSV
        </a>
    </div>

    <p class="text-sm text-gray-600 mb-4">
        Decision rule (ADR-022): iterate W1 content from this funnel — hero, urgency, outcomes, sticky CTA, checkout, or payment copy — when a stage is stuck.
    </p>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Course id</label>
            <input type="number" name="course_id" min="1" value="{{ $courseId }}" class="form-input rounded-md" placeholder="All">
        </div>
        <button type="submit" class="btn-primary">Filter</button>
        @if($courseId)
            <a href="{{ route('admin.funnel.index') }}" class="text-sm text-gray-500 hover:text-gray-800">Clear</a>
        @endif
    </form>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Register clicks</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WhatsApp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Syllabus</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">View → click</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decision</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($reports as $report)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $report['course_title'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $report['counts']['course_view'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $report['counts']['register_click'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $report['counts']['registration_started'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $report['counts']['payment_completed'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $report['counts']['whatsapp_click'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $report['counts']['syllabus_download'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $report['rates']['view_to_register'] === null ? '—' : number_format($report['rates']['view_to_register'] * 100, 1).'%' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $report['decision'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-gray-500">No funnel events yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
