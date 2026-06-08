@extends('layouts.app')
@section('title', 'Parent Follow-up')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-4xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Parent Follow-up Cases</h1>
@foreach($records as $r)<div class="card p-4 mb-3"><p class="font-medium">{{ $r->student->full_name }}</p><p class="text-sm text-gray-600">{{ $r->session->session_date->format('d M Y') }}</p><p>{{ $r->parent_visible_note }}</p></div>@endforeach
</div></div>
@endsection
