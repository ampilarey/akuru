@extends('layouts.app')
@section('title', 'Milestone Report')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-5xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Milestone Report</h1>
<div class="card overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="px-4 py-2 text-left">Student</th><th>Program</th><th>Type</th><th>Status</th></tr></thead>
<tbody>@foreach($milestones as $m)<tr class="border-t"><td class="px-4 py-3">{{ $m->student->full_name }}</td><td>{{ $m->program->name }}</td><td>{{ $m->type->value }}</td><td>{{ $m->status->value }}</td></tr>@endforeach</tbody></table></div>
{{ $milestones->links() }}
</div></div>
@endsection
