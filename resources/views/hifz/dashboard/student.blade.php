@extends('layouts.app')
@section('title', 'My Hifz Progress')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-4xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">My Hifz Progress</h1>
@if($enrollment)
<div class="card p-4 mb-6">
<p><strong>Program:</strong> {{ $enrollment->program->name }}</p>
<p><strong>Current:</strong> Juz {{ $enrollment->current_juz ?? '—' }} · Page {{ $enrollment->current_page ?? '—' }}</p>
@if($recentRecords->first()?->next_target)<p class="mt-2"><strong>Next target:</strong> {{ $recentRecords->first()->next_target }}</p>@endif
</div>
@endif
<h3 class="font-semibold mb-3">Recent Sessions</h3>
@foreach($recentRecords as $r)
<div class="card p-3 mb-2 text-sm">
<span class="font-medium">{{ $r->session->session_date->format('d M Y') }}</span>
@if($r->overall_status)<span class="ml-2 px-2 py-0.5 rounded bg-gray-100">{{ $r->overall_status->value }}</span>@endif
@if($r->teacher_note)<p class="mt-1 text-gray-600">{{ $r->teacher_note }}</p>@endif
</div>
@endforeach
<h3 class="font-semibold mt-6 mb-3">Approved Milestones</h3>
@forelse($milestones as $m)<div class="text-sm py-1">{{ $m->title ?? str_replace('_',' ',$m->type->value) }} — {{ $m->approved_at?->format('d M Y') }}</div>@empty<p class="text-gray-500">No milestones yet.</p>@endforelse
</div></div>
@endsection
