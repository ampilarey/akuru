@extends('layouts.app')
@section('title', 'Hifz Sessions')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-7xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Hifz Sessions</h1>
<div class="card overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="px-4 py-2 text-left">Date</th><th>Program</th><th>Teacher</th><th>Status</th><th></th></tr></thead>
<tbody>@foreach($sessions as $s)<tr class="border-t"><td class="px-4 py-3">{{ $s->session_date->format('d M Y') }}</td><td>{{ $s->program->name }}</td><td>{{ $s->teacher->full_name }}</td><td>{{ $s->status->value }}</td><td><a href="{{ route('hifz.sessions.edit', $s) }}">Open</a></td></tr>@endforeach</tbody></table></div>
{{ $sessions->links() }}
</div></div>
@endsection
