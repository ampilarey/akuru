@extends('layouts.app')
@section('title', 'Hifz Teacher Dashboard')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
@include('hifz.partials.alerts')
<h1 class="text-2xl font-bold mb-6">Hifz Teacher Dashboard</h1>
<div class="grid md:grid-cols-3 gap-4 mb-6">
    <div class="card p-4"><p class="text-gray-500 text-sm">Assigned Students</p><p class="text-3xl font-bold">{{ $enrollments->count() }}</p></div>
    <div class="card p-4"><p class="text-gray-500 text-sm">Programs</p><p class="text-3xl font-bold">{{ $programs->count() }}</p></div>
    <div class="card p-4"><p class="text-gray-500 text-sm">Today's Session</p><p class="text-lg font-semibold">{{ $todaySession ? 'Active' : 'Not started' }}</p></div>
</div>
@if($programs->isNotEmpty())
@foreach($programs as $program)
<div class="card p-4 mb-4 flex justify-between items-center">
    <div><h3 class="font-semibold">{{ $program->name }}</h3></div>
    <div class="flex gap-2">
        @if($todaySession && $todaySession->hifz_program_id === $program->id)
        <a href="{{ route('hifz.sessions.edit', $todaySession) }}" class="btn btn-primary">Continue Today's Session</a>
        @else
        <form method="POST" action="{{ route('hifz.sessions.create') }}">@csrf<input type="hidden" name="hifz_program_id" value="{{ $program->id }}"><button class="btn btn-primary">Start Today's Session</button></form>
        @endif
    </div>
</div>
@endforeach
@endif
<a href="{{ route('hifz.sessions.index') }}" class="text-brandMaroon-600">View all sessions →</a>
</div></div>
@endsection
