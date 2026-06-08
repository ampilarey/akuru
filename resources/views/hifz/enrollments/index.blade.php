@extends('layouts.app')
@section('title', 'Enrollments')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-5xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Enrollments — {{ $program->name }}</h1>
<a href="{{ route('hifz.enrollments.create', $program) }}" class="btn btn-primary mb-4">Add Student</a>
<div class="card overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="px-4 py-2 text-left">Student</th><th>Teacher</th><th>Status</th></tr></thead>
<tbody>@foreach($enrollments as $e)<tr class="border-t"><td class="px-4 py-3">{{ $e->student->full_name }}</td><td>{{ $e->teacher->full_name ?? '—' }}</td><td>{{ $e->status->value }}</td></tr>@endforeach</tbody></table></div>
{{ $enrollments->links() }}
</div></div>
@endsection
