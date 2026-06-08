@extends('layouts.app')
@section('title', 'Enroll Student')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-2xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Enroll Student — {{ $program->name }}</h1>
<form method="POST" action="{{ route('hifz.enrollments.store', $program) }}" class="card p-6 space-y-4">@csrf
<div><label class="block text-sm font-medium mb-1">Student</label><select name="student_id" class="form-input w-full" required>@foreach($students as $s)<option value="{{ $s->id }}">{{ $s->full_name }}</option>@endforeach</select></div>
<div><label class="block text-sm font-medium mb-1">Teacher</label><select name="teacher_id" class="form-input w-full"><option value="">Default</option>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->full_name }}</option>@endforeach</select></div>
<div><label class="block text-sm font-medium mb-1">Start Date</label><input type="date" name="start_date" value="{{ date('Y-m-d') }}" class="form-input w-full" required></div>
<div><label class="block text-sm font-medium mb-1">Current Page</label><input type="number" name="current_page" class="form-input w-full" min="1" max="604"></div>
<button type="submit" class="btn btn-primary">Enroll</button>
</form></div></div>
@endsection
