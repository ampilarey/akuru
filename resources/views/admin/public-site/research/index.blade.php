@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold text-gray-900">Research posts</h1>
            <a href="{{ route('admin.daily-content.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Daily content →</a>
            <a href="{{ route('admin.leads.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Leads →</a>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.research.export', request()->only(['year', 'instructor_id', 'q'])) }}" class="btn-secondary">Export CSV</a>
            <a href="{{ route('admin.research.create') }}" class="btn-primary">New research</a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Year</label>
            <select name="year" class="form-input rounded-md">
                <option value="">All</option>
                @foreach($years as $year)
                    <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Author</label>
            <select name="instructor_id" class="form-input rounded-md">
                <option value="">All</option>
                @foreach($instructors as $instructor)
                    <option value="{{ $instructor['id'] }}" @selected((string) ($filters['instructor_id'] ?? '') === (string) $instructor['id'])>{{ $instructor['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Search</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input rounded-md">
        </div>
        <button type="submit" class="btn-primary">Filter</button>
    </form>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Authors</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Published</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($posts as $post)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $post['title'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $post['year'] ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $post['authors_label'] !== '' ? $post['authors_label'] : '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $post['is_published'] ? 'yes' : 'no' }}</td>
                            <td class="px-6 py-4 text-sm"><a href="{{ route('admin.research.edit', $post['id']) }}" class="text-brandBlue-600">Edit</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">No research posts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
