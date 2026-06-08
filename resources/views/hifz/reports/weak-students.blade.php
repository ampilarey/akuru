@extends('layouts.app')
@section('title', 'Weak Students Report')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-4xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6"><a href="{{ route('hifz.reports.index') }}" class="text-gray-500">←</a> Weak Students</h1>
<div class="card p-4">@forelse($rows as $row)<div class="py-2 border-b flex justify-between"><span>{{ $row->student->full_name ?? 'Student #'.$row->student_id }}</span><span>{{ $row->weak_count }} weak sessions</span></div>@empty<p class="text-gray-500">No data.</p>@endforelse</div>
</div></div>
@endsection
