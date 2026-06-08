@extends('layouts.app')
@section('title', 'Edit Program')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-2xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Edit {{ $program->name }}</h1>
<form method="POST" action="{{ route('hifz.programs.update', $program) }}" class="card p-6 space-y-4">@csrf @method('PUT')
<div><label class="block text-sm font-medium mb-1">Name</label><input name="name" value="{{ $program->name }}" class="form-input w-full" required></div>
<div><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" class="form-input w-full" rows="3">{{ $program->description }}</textarea></div>
<div><label class="block text-sm font-medium mb-1">Status</label><select name="status" class="form-input w-full">@foreach(['active','inactive','completed'] as $s)<option value="{{ $s }}" @selected($program->status->value===$s)>{{ $s }}</option>@endforeach</select></div>
<div><label class="block text-sm font-medium mb-1">Supervisor</label><select name="supervisor_id" class="form-input w-full"><option value="">—</option>@foreach($supervisors as $s)<option value="{{ $s->id }}" @selected($program->supervisor_id==$s->id)>{{ $s->name }}</option>@endforeach</select></div>
<button type="submit" class="btn btn-primary">Save</button>
</form></div></div>
@endsection
