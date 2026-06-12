@extends('layouts.app')
@section('title', $program->name)
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-7xl mx-auto px-4">
@include('hifz.partials.alerts')
<h1 class="text-2xl font-bold mb-2">{{ $program->name }}</h1>
<p class="text-gray-600 mb-6">{{ $program->description }}</p>
<div class="flex gap-2 mb-6">
@can('update', $program)<a href="{{ route('hifz.programs.edit', $program) }}" class="btn btn-secondary">Edit</a>@endcan
<a href="{{ route('hifz.enrollments.create', $program) }}" class="btn btn-primary">Enroll Student</a>
</div>
@can('assignSupervisor', App\Domains\Hifz\Models\HifzProgram::class)
<form method="POST" action="{{ route('hifz.programs.assign-supervisor', $program) }}" class="card p-4 mb-6 flex gap-2 items-end">@csrf
<select name="supervisor_id" class="form-input">@foreach(\App\Models\User::role('supervisor')->get() as $s)<option value="{{ $s->id }}" @selected($program->supervisor_id==$s->id)>{{ $s->name }}</option>@endforeach</select>
<button class="btn btn-secondary">Assign Supervisor</button>
</form>
@endcan
<div class="card overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="px-4 py-2 text-left">Student</th><th>Teacher</th><th>Status</th><th>Page</th><th></th></tr></thead>
<tbody>@foreach($program->enrollments as $e)<tr class="border-t"><td class="px-4 py-3">{{ $e->student->full_name }}</td><td>{{ $e->teacher->full_name ?? '—' }}</td><td>{{ $e->status->value }}</td><td>{{ $e->current_page ?? '—' }}</td><td><a href="{{ route('hifz.students.history', $e->student) }}">History</a></td></tr>@endforeach</tbody></table></div>
</div></div>
@endsection
