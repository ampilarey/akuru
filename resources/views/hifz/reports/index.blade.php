@extends('layouts.app')
@section('title', 'Hifz Reports')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-4xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Hifz Reports</h1>
<div class="grid sm:grid-cols-2 gap-4">
<a href="{{ route('hifz.reports.weak-students') }}" class="card p-4 hover:shadow-md">Weak Students</a>
<a href="{{ route('hifz.reports.haraka-mistakes') }}" class="card p-4 hover:shadow-md">Haraka Mistake Report</a>
<a href="{{ route('hifz.reports.parent-follow-up') }}" class="card p-4 hover:shadow-md">Parent Follow-up</a>
<a href="{{ route('hifz.reports.teacher-completion') }}" class="card p-4 hover:shadow-md">Teacher Completion</a>
<a href="{{ route('hifz.reports.milestones') }}" class="card p-4 hover:shadow-md">Milestone Approval</a>
@can('export_hifz_reports')<a href="{{ route('hifz.reports.export', ['type'=>'sessions']) }}" class="card p-4 hover:shadow-md">Export Sessions CSV</a>@endcan
</div></div></div>
@endsection
