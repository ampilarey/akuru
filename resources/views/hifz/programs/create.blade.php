@extends('layouts.app')
@section('title', 'Create Hifz Program')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-2xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Create Hifz Program</h1>
<form method="POST" action="{{ route('hifz.programs.store') }}" class="card p-6 space-y-4">@csrf
<div><label class="block text-sm font-medium mb-1">Name</label><input name="name" class="form-input w-full" required></div>
<div><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" class="form-input w-full" rows="3"></textarea></div>
<div><label class="block text-sm font-medium mb-1">Class</label><select name="class_id" class="form-input w-full"><option value="">—</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
<div><label class="block text-sm font-medium mb-1">Supervisor</label><select name="supervisor_id" class="form-input w-full"><option value="">—</option>@foreach($supervisors as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
<div><label class="block text-sm font-medium mb-1">Default Teacher</label><select name="default_teacher_id" class="form-input w-full"><option value="">—</option>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->full_name }}</option>@endforeach</select></div>
<button type="submit" class="btn btn-primary">Create</button>
</form></div></div>
@endsection
