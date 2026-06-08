@extends('layouts.app')
@section('title', 'Teacher Completion')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-4xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Teachers Missing Today's Records</h1>
@forelse($missing as $t)<div class="card p-3 mb-2">{{ $t->full_name }}</div>@empty<p class="text-gray-500">All teachers have submitted today.</p>@endforelse
</div></div>
@endsection
