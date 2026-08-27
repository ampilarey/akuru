@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold">Prayer broadcasts</h1>
            <a href="{{ route('admin.prayer-times.islands') }}" class="text-sm text-brandBlue-600">Islands →</a>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.prayer-times.broadcasts.export', request()->only(['status','mode'])) }}" class="btn-secondary">Export CSV</a>
            <a href="{{ route('admin.prayer-times.broadcasts.create') }}" class="btn-primary">New broadcast</a>
        </div>
    </div>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    <form method="GET" class="flex flex-wrap gap-3 mb-4">
        <select name="mode" class="form-input"><option value="">All modes</option>@foreach(['daily','range','change_only'] as $mode)<option value="{{ $mode }}" @selected(($filters['mode'] ?? '')===$mode)>{{ $mode }}</option>@endforeach</select>
        <select name="status" class="form-input"><option value="">All statuses</option>@foreach(['draft','previewed','queued','completed','failed','cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ $status }}</option>@endforeach</select>
        <button class="btn-secondary">Filter</button>
    </form>
    <table class="min-w-full bg-white border">
        <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="p-2">ID</th><th class="p-2">Mode</th><th class="p-2">Status</th><th class="p-2">Island</th><th class="p-2">Sent</th><th class="p-2">Cost</th></tr></thead>
        <tbody>
        @forelse($broadcasts as $row)
            <tr class="border-t">
                <td class="p-2"><a class="text-brandBlue-700" href="{{ route('admin.prayer-times.broadcasts.edit', $row) }}">{{ $row->id }}</a></td>
                <td class="p-2">{{ $row->mode->value }}</td>
                <td class="p-2">{{ $row->status->value }}</td>
                <td class="p-2">{{ $row->island?->name_latin }}</td>
                <td class="p-2">{{ $row->sent_count }}/{{ $row->failed_count }}</td>
                <td class="p-2">{{ $row->estimated_cost }}</td>
            </tr>
        @empty
            <tr><td class="p-4" colspan="6">No broadcasts.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $broadcasts->links() }}</div>
</div>
@endsection
