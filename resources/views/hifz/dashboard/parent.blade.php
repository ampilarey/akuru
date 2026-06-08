@extends('layouts.app')
@section('title', 'Child Hifz Progress')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-4xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Hifz Progress</h1>
@if($children->count() > 1)
<form method="GET" class="mb-4">
<select name="student_id" onchange="this.form.submit()" class="form-input">
@foreach($children as $child)<option value="{{ $child->id }}" @selected($selectedChild->id === $child->id)>{{ $child->full_name }}</option>@endforeach
</select>
</form>
@endif
<h2 class="text-lg font-semibold mb-4">{{ $selectedChild->full_name }}</h2>
@if($todayRecord)
<div class="card p-4 mb-4 @if($todayRecord->requires_parent_attention) border-l-4 border-red-500 @endif">
<p><strong>Today:</strong> {{ $todayRecord->attendance_status?->value }} · {{ $todayRecord->overall_status?->value ?? 'Recorded' }}</p>
@if($todayRecord->parent_visible_note)<p class="mt-2">{{ $todayRecord->parent_visible_note }}</p>@endif
@if($todayRecord->next_target)<p class="mt-2 text-sm"><strong>Next:</strong> {{ $todayRecord->next_target }}</p>@endif
@if($todayRecord->requires_parent_attention)<p class="text-red-600 font-medium mt-2">Parent attention required</p>@endif
</div>
@else<p class="text-gray-500 mb-4">No Hifz record for today yet.</p>@endif
<h3 class="font-semibold mb-2">This Week</h3>
@foreach($weeklyRecords as $r)
<div class="text-sm border-b py-2">{{ $r->session->session_date->format('D d M') }} — {{ $r->overall_status?->value ?? '—' }}</div>
@endforeach
</div></div>
@endsection
