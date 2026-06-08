@extends('layouts.app')
@section('title', 'Hifz Dean Dashboard')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
@include('hifz.partials.alerts')
<h1 class="text-2xl font-bold mb-6">Hifz Dean Dashboard</h1>
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
@foreach($cards as $label => $value)
@php $labels = ['active_students'=>'Active Students','active_programs'=>'Programs','supervisors'=>'Supervisors','teachers'=>'Teachers','sessions_today'=>'Sessions Today','pending_supervisor_review'=>'Pending Reviews','absent_today'=>'Absent Today','parent_attention'=>'Parent Attention','juz_this_month'=>'Juz This Month','missing_teachers'=>'Missing Records']; @endphp
<div class="card p-4"><p class="text-gray-500 text-sm">{{ $labels[$label] ?? $label }}</p><p class="text-2xl font-bold">{{ $value }}</p></div>
@endforeach
</div>
<div class="grid lg:grid-cols-2 gap-6 mb-6">
<div class="card p-4"><h3 class="font-semibold mb-3">High Haraka Mistakes</h3>
@foreach($harakaLeaders->take(8) as $row)<div class="text-sm py-1 border-b flex justify-between"><span>{{ $row->student->full_name ?? '' }}</span><span>{{ $row->total_haraka }}</span></div>@endforeach
</div>
<div class="card p-4"><h3 class="font-semibold mb-3">Milestones Awaiting Approval</h3>
@foreach($pendingMilestones as $m)
<div class="text-sm py-2 border-b flex justify-between items-center">
<span>{{ $m->student->full_name }} — {{ str_replace('_',' ',$m->type->value) }}</span>
<form method="POST" action="{{ route('hifz.milestones.approve', $m) }}">@csrf<button class="text-green-600 text-xs">Approve</button></form>
</div>
@endforeach
</div>
</div>
<div class="flex flex-wrap gap-2">
<a href="{{ route('hifz.programs.index') }}" class="btn btn-primary">Programs</a>
<a href="{{ route('hifz.reports.index') }}" class="btn btn-secondary">Reports</a>
<a href="{{ route('hifz.quran.mushafs.index') }}" class="btn btn-secondary">Quran Source</a>
</div>
</div></div>
@endsection
