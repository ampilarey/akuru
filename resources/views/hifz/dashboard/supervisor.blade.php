@extends('layouts.app')
@section('title', 'Hifz Supervisor Dashboard')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
@include('hifz.partials.alerts')
<h1 class="text-2xl font-bold mb-6">Hifz Supervisor Dashboard</h1>
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
@foreach(['programs'=>'Programs','teachers'=>'Teachers','sessions_today'=>'Sessions Today','pending_review'=>'Pending Review','missing_teachers'=>'Teachers Missing Records','needs_supervisor'=>'Needs Supervisor Review','parent_attention'=>'Parent Attention'] as $key => $label)
<div class="card p-4"><p class="text-gray-500 text-sm">{{ $label }}</p><p class="text-2xl font-bold">{{ $cards[$key] ?? 0 }}</p></div>
@endforeach
</div>
<div class="grid lg:grid-cols-2 gap-6">
<div class="card p-4">
<h3 class="font-semibold mb-3">Haraka Mistake Alerts</h3>
@forelse($harakaLeaders->take(5) as $row)
<div class="flex justify-between border-b py-2 text-sm"><span>{{ $row->student->full_name ?? 'Student' }}</span><span class="text-red-600 font-medium">{{ $row->total_haraka }} haraka</span></div>
@empty<p class="text-gray-500 text-sm">No haraka issues this period.</p>@endforelse
</div>
<div class="card p-4">
<h3 class="font-semibold mb-3">Weak Students</h3>
@forelse($weakStudents->take(5) as $row)
<div class="flex justify-between border-b py-2 text-sm"><span>{{ $row->student->full_name ?? 'Student' }}</span><span>{{ $row->weak_count }} weak sessions</span></div>
@empty<p class="text-gray-500 text-sm">No weak students flagged.</p>@endforelse
</div>
</div>
<div class="mt-6 flex gap-3">
<a href="{{ route('hifz.reports.index') }}" class="btn btn-secondary">Reports</a>
<a href="{{ route('hifz.milestones.index') }}" class="btn btn-secondary">Milestones</a>
</div>
</div></div>
@endsection
